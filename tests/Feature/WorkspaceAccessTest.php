<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FollowUp;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\SystemNotification;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceProvisioner;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    private function add(User $user, Workspace $workspace, array $roles, array $attributes = []): Membership
    {
        $member = Membership::updateOrCreate(['user_id' => $user->id, 'workspace_id' => $workspace->id], ['scope' => $workspace->kind === 'personal' ? 'own' : 'organisation', ...$attributes]);
        $member->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $member->load(['workspace.agency', 'roles.permissions']);
    }

    public function test_one_identity_can_be_owner_investor_and_agent_in_separate_contexts(): void
    {
        $user = User::factory()->create();
        $personal = app(WorkspaceProvisioner::class)->personal($user);
        $organisation = Workspace::factory()->create();
        $this->add($user, $personal, ['buyer', 'owner', 'investor']);
        $this->add($user, $organisation, ['agent']);
        $this->actingAs($user)->post('/workspaces/'.$organisation->id.'/switch')->assertRedirect('/dashboard');
        $this->get('/panels/organisation')->assertOk()->assertSee('My Follow-Ups')->assertDontSee('Investment Watchlist');
        $this->post('/workspaces/'.$personal->id.'/switch')->assertRedirect('/dashboard');
        $this->get('/panels/personal')->assertOk()->assertSee('Investor')->assertDontSee('My Follow-Ups');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_membership_roles_are_unioned_only_inside_the_selected_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $member = $this->add($user, $workspace, ['agent', 'accountant']);
        $user->setRelation('workspaceMembership', $member);
        $this->assertTrue($user->hasPermission('followups.create'));
        $this->assertTrue($user->hasPermission('payments.create'));
        $this->assertFalse($user->hasPermission('users.manage'));
        $this->actingAs($user)->post('/workspaces/'.app(WorkspaceProvisioner::class)->personal($user)->id.'/switch');
        $this->get('/payments')->assertForbidden();
    }

    public function test_forged_and_inactive_workspace_switches_are_denied(): void
    {
        $user = User::factory()->create();
        $foreign = Workspace::factory()->create();
        $this->actingAs($user)->post('/workspaces/'.$foreign->id.'/switch')->assertForbidden();
        $this->add($user, $foreign, ['agent'], ['is_active' => false]);
        $this->post('/workspaces/'.$foreign->id.'/switch')->assertForbidden();
    }

    public function test_suspended_organisation_is_unavailable_even_with_a_valid_membership(): void
    {
        $user = User::factory()->role('agency_admin')->create();
        $workspace = $user->currentWorkspace();
        $workspace->agency->update(['status' => 'suspended']);
        $this->actingAs($user)->post('/workspaces/'.$workspace->id.'/switch')->assertForbidden();
        $this->get('/workspaces')->assertOk()->assertDontSee('action="'.route('workspaces.switch', $workspace->id).'"', false);
        $this->get('/users')->assertForbidden();
    }

    public function test_disabled_module_is_hidden_and_its_get_and_post_routes_are_blocked(): void
    {
        $user = User::factory()->role('agency_admin')->create();
        $workspace = $user->currentWorkspace();
        $workspace->update(['enabled_modules' => array_values(array_diff($workspace->enabled_modules, ['payments']))]);
        $this->actingAs($user)->get('/payments')->assertForbidden();
        $this->post('/payments', [])->assertForbidden();
        $this->get('/panels/organisation')->assertOk()->assertDontSee('href="'.route('payments.index').'"', false);
    }

    public function test_platform_admin_has_no_implicit_read_access_to_organisation_payments(): void
    {
        $admin = User::factory()->role('platform_admin')->create();
        $payment = Payment::factory()->create();
        $this->actingAs($admin)->get('/payments')->assertOk()->assertDontSee($payment->reference);
        $this->post('/workspaces/'.$payment->workspace_id.'/switch')->assertForbidden();
        $this->assertSame(0, app(WorkspaceAccess::class)->records(Payment::query(), $admin, 'payments.view')->count());
    }

    public function test_same_account_in_two_organisations_cannot_mix_financial_records(): void
    {
        $user = User::factory()->role('accountant')->create();
        $first = $user->currentWorkspace();
        $second = Workspace::factory()->create();
        $this->add($user, $second, ['accountant']);
        $one = Payment::factory()->create(['workspace_id' => $first->id, 'user_id' => $user->id]);
        $two = Payment::factory()->create(['workspace_id' => $second->id, 'user_id' => $user->id]);
        $this->actingAs($user)->post('/workspaces/'.$second->id.'/switch');
        $this->get('/payments')->assertOk()->assertSee($two->reference)->assertDontSee($one->reference);
    }

    public function test_branch_team_assigned_and_own_scopes_are_applied_to_real_queries(): void
    {
        $user = User::factory()->role('accountant')->create();
        $workspace = $user->currentWorkspace();
        $branch = Branch::factory()->create(['workspace_id' => $workspace->id]);
        $team = Team::factory()->create(['workspace_id' => $workspace->id, 'branch_id' => $branch->id]);
        $mine = Payment::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
        $assigned = Payment::factory()->create(['workspace_id' => $workspace->id, 'assigned_to' => $user->id]);
        $teamRecord = Payment::factory()->create(['workspace_id' => $workspace->id, 'branch_id' => $branch->id, 'team_id' => $team->id]);
        $branchRecord = Payment::factory()->create(['workspace_id' => $workspace->id, 'branch_id' => $branch->id]);
        foreach (['own' => [$mine->id], 'assigned' => [$mine->id, $assigned->id], 'team' => [$teamRecord->id], 'branch' => [$teamRecord->id, $branchRecord->id]] as $scope => $expected) {
            $member = $this->add($user, $workspace, ['accountant'], ['scope' => $scope, 'branch_id' => $branch->id, 'team_id' => $team->id]);
            $user->setRelation('workspaceMembership', $member);
            $actual = app(WorkspaceAccess::class)->records(Payment::query(), $user, 'payments.view')->orderBy('id')->pluck('id')->all();
            sort($expected);
            $this->assertSame($expected, $actual, $scope);
        }
    }

    public function test_auditor_can_read_only_its_review_scope_and_cannot_write_or_export(): void
    {
        $user = User::factory()->role('auditor')->create();
        $this->actingAs($user)->get('/payments')->assertOk()->assertDontSee('New payment record');
        $this->post('/payments', [])->assertForbidden();
        $this->post('/workspace/members', [])->assertForbidden();
        $this->assertFalse($user->hasPermission('payments.export'));
    }

    public function test_support_lookup_exposes_only_limited_metadata_and_no_financial_access(): void
    {
        $support = User::factory()->role('support_agent')->create();
        $target = User::factory()->create();
        $this->actingAs($support)->get('/account/lookup?email='.urlencode($target->email))->assertOk()->assertSee($target->email)->assertDontSee($target->password);
        $this->get('/payments')->assertForbidden();
        $this->get('/workspace/modules/documents')->assertForbidden();
        $this->post('/users', [])->assertForbidden();
    }

    public function test_revoked_membership_does_not_continue_authorising_a_previous_session(): void
    {
        $user = User::factory()->role('accountant')->create();
        $membership = $user->currentMembership();
        $this->actingAs($user)->get('/payments')->assertOk();
        $membership->update(['is_active' => false]);
        $this->get('/payments')->assertForbidden();
    }

    public function test_legacy_role_columns_cannot_override_membership_authorisation(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role_id' => Role::where('name', 'platform_admin')->sole()->id])->save();
        $this->actingAs($user)->get('/organisations')->assertForbidden();
        $this->get('/panels/platform')->assertForbidden();
    }

    public function test_every_documented_role_has_a_working_contextual_dashboard(): void
    {
        foreach (array_keys(config('workspaces.roles')) as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get('/panels/'.$user->homePanel())->assertOk();
        }
    }

    public function test_edit_scope_is_enforced_even_when_view_scope_is_broader(): void
    {
        $admin = User::factory()->role('agency_admin')->create();
        $workspace = $admin->currentWorkspace();
        $permission = Permission::where('name', 'followups.edit')->sole();
        Role::where('name', 'agency_admin')->sole()->permissions()->updateExistingPivot($permission->id, ['scope' => 'own']);
        $other = User::factory()->role('agent')->create(['agency_id' => $admin->agency_id]);
        $record = FollowUp::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $other->id, 'created_by' => $admin->id, 'agency_id' => $admin->agency_id]);
        $this->actingAs($admin)->get('/follow-ups')->assertOk()->assertSee($record->subject);
        $this->patch('/follow-ups/'.$record->id.'/cancel')->assertNotFound();
        $this->assertSame('pending', $record->fresh()->status);
    }

    public function test_creation_scope_cannot_be_widened_by_user_directory_permissions(): void
    {
        $admin = User::factory()->role('agency_admin')->create();
        $workspace = $admin->currentWorkspace();
        $branch = Branch::factory()->create(['workspace_id' => $workspace->id]);
        $foreign = Branch::factory()->create(['workspace_id' => $workspace->id]);
        $other = User::factory()->create(['agency_id' => $admin->agency_id]);
        Membership::where('workspace_id', $workspace->id)->where('user_id', $other->id)->update(['branch_id' => $foreign->id]);
        $member = $admin->currentMembership();
        $member->update(['branch_id' => $branch->id]);
        $permission = Permission::where('name', 'payments.create')->sole();
        Role::where('name', 'agency_admin')->sole()->permissions()->updateExistingPivot($permission->id, ['scope' => 'branch']);
        $this->actingAs($admin)->post('/payments', ['reference' => (string) Str::uuid(), 'user_id' => $other->id, 'provider' => 'manual', 'amount' => '100', 'currency' => 'NGN', 'purpose' => 'Test'])->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_workspace_specific_notifications_are_not_exposed_in_another_workspace(): void
    {
        $user = User::factory()->role('agent')->create();
        $workspace = $user->currentWorkspace();
        $user->notifyNow(new SystemNotification('Organisation-only update', 'Private scoped message', ['database'], $workspace->id));
        $notification = $user->notifications()->sole();
        $this->actingAs($user)->get('/notifications')->assertOk()->assertSee('Private scoped message');
        $personal = app(WorkspaceProvisioner::class)->personal($user);
        $this->post('/workspaces/'.$personal->id.'/switch');
        $this->get('/notifications')->assertOk()->assertDontSee('Private scoped message');
        $this->patch('/notifications/'.$notification->id.'/read')->assertNotFound();
    }
}

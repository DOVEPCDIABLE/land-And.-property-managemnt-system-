<?php

namespace Tests\Feature;

use App\Jobs\SendFollowUp;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\FollowUp;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use App\Notifications\SystemNotification;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WorkspaceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_registration_creates_one_personal_workspace_and_ignores_privileged_fields(): void
    {
        Notification::fake();
        $this->post('/register', ['name' => 'New Person', 'email' => 'NEW@example.test', 'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!', 'role_id' => Role::where('name', 'platform_admin')->sole()->id, 'agency_id' => 999])->assertRedirect('/email/verify');
        $user = User::where('email', 'new@example.test')->sole();
        $this->assertSame(1, $user->memberships()->count());
        $this->assertFalse($user->hasPlatformRole());
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseCount('users', 1);
        Notification::assertSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_personal_roles_can_be_combined_but_platform_and_staff_roles_cannot_be_self_assigned(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/account/profile', ['name' => $user->name, 'roles' => ['buyer', 'owner', 'investor']])->assertSessionHasNoErrors();
        $personal = $user->memberships()->whereHas('workspace', fn ($q) => $q->where('kind', 'personal'))->sole();
        $this->assertSame(3, $personal->roles()->count());
        $this->patch('/account/profile', ['name' => $user->name, 'roles' => ['platform_admin']])->assertSessionHasErrors('roles.0');
    }

    public function test_an_existing_account_can_join_another_workspace_without_duplicate_identity(): void
    {
        $admin = User::factory()->role('agency_admin')->create();
        $target = User::factory()->create();
        $workspace = $admin->currentWorkspace();
        $this->actingAs($admin)->post('/workspace/members', ['email' => $target->email, 'roles' => ['agent', 'owner'], 'scope' => 'organisation'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('users', 2);
        $member = Membership::where('user_id', $target->id)->where('workspace_id', $workspace->id)->sole();
        $this->assertSame(2, $member->roles()->count());
        $this->assertSame('membership.roles_updated', AuditLog::latest('id')->first()->action);
    }

    public function test_an_organisation_admin_cannot_grant_platform_roles_or_foreign_branches(): void
    {
        $admin = User::factory()->role('agency_admin')->create();
        $target = User::factory()->create();
        $branch = Branch::factory()->create();
        $this->actingAs($admin)->post('/workspace/members', ['email' => $target->email, 'roles' => ['platform_admin'], 'scope' => 'organisation'])->assertSessionHasErrors('roles.0');
        $this->post('/workspace/members', ['email' => $target->email, 'roles' => ['agent'], 'scope' => 'branch', 'branch_id' => $branch->id])->assertSessionHasErrors('branch_id');
    }

    public function test_organisation_membership_changes_do_not_modify_global_credentials_or_other_workspaces(): void
    {
        $admin = User::factory()->role('agency_admin')->create();
        $target = User::factory()->create(['agency_id' => $admin->agency_id]);
        $password = $target->password;
        $email = $target->email;
        $this->actingAs($admin)->patch('/users/'.$target->id, ['name' => 'Changed', 'email' => 'changed@example.test', 'password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123', 'role_id' => Role::where('name', 'agent')->sole()->id, 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertSame($password, $target->fresh()->password);
        $this->assertSame($email, $target->fresh()->email);
        $this->assertTrue($target->fresh()->is_active);
        $this->assertFalse($target->memberships()->where('workspace_id', $admin->currentWorkspace()->id)->sole()->is_active);
        $this->assertTrue($target->memberships()->whereHas('workspace', fn ($q) => $q->where('kind', 'personal'))->sole()->is_active);
    }

    public function test_workspace_settings_disable_a_module_on_the_server(): void
    {
        $admin = User::factory()->role('agency_admin')->create();
        $this->actingAs($admin)->patch('/workspace/settings', ['name' => 'Renamed', 'enabled_modules' => ['users', 'followups']])->assertSessionHasNoErrors();
        $this->get('/payments')->assertForbidden();
        $this->get('/workspace/settings')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'workspace.settings_updated']);
    }

    public function test_profession_extensions_use_the_existing_personal_membership(): void
    {
        $user = User::factory()->create();
        $count = $user->memberships()->count();
        $this->actingAs($user)->patch('/account/profile', ['name' => $user->name, 'roles' => ['service_provider'], 'profession' => 'architect'])->assertSessionHasNoErrors();
        $this->get('/panels/personal')->assertOk()->assertSee('Drawings');
        $this->assertSame($count, $user->memberships()->count());
    }

    public function test_password_change_requires_current_password_and_updates_only_the_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $before = $other->password;
        $this->actingAs($user)->patch('/account/password', ['current_password' => 'wrong', 'password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123'])->assertSessionHasErrors('current_password');
        $this->patch('/account/password', ['current_password' => 'TestPassword123!', 'password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('ChangedPassword123', $user->fresh()->password));
        $this->assertSame($before, $other->fresh()->password);
    }

    public function test_notification_preferences_are_honoured_without_disabling_recovery_email(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/account/preferences', ['database' => false, 'mail' => false])->assertSessionHasNoErrors();
        $user = $user->fresh();
        $this->assertSame([], (new SystemNotification('Test', 'Test', ['mail', 'database']))->via($user));
        Notification::fake();
        $user->sendPasswordResetNotification('token');
        Notification::assertSentTo($user, QueuedResetPassword::class);
    }

    public function test_followup_is_cancelled_if_the_senders_membership_is_revoked_before_delivery(): void
    {
        $followup = FollowUp::factory()->create(['status' => 'queued']);
        Membership::where('workspace_id', $followup->workspace_id)->where('user_id', $followup->created_by)->update(['is_active' => false]);
        (new SendFollowUp($followup->id))->handle();
        $this->assertSame('cancelled', $followup->fresh()->status);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_platform_can_create_and_suspend_an_organisation_without_joining_it(): void
    {
        $admin = User::factory()->role('platform_admin')->create();
        $owner = User::factory()->create();
        $this->actingAs($admin)->post('/organisations', ['name' => 'New Agency', 'type' => 'agency', 'admin_email' => $owner->email])->assertSessionHasNoErrors();
        $workspace = Workspace::where('name', 'New Agency')->sole();
        $this->assertFalse($admin->memberships()->where('workspace_id', $workspace->id)->exists());
        $this->assertTrue($owner->memberships()->where('workspace_id', $workspace->id)->exists());
        $this->patch('/organisations/'.$workspace->agency_id, ['status' => 'suspended'])->assertSessionHasNoErrors();
        $this->assertFalse($workspace->fresh()->available());
    }

    public function test_super_admin_can_assign_limited_platform_staff_memberships_to_existing_accounts(): void
    {
        $admin = User::factory()->role('platform_admin')->create();
        $target = User::factory()->create();
        $this->actingAs($admin)->post('/workspace/members', ['email' => $target->email, 'roles' => ['support_agent', 'compliance_officer'], 'scope' => 'platform'])->assertSessionHasNoErrors();
        $member = Membership::where('workspace_id', $admin->currentWorkspace()->id)->where('user_id', $target->id)->sole();
        $this->assertSame(2, $member->roles()->count());
        $this->assertFalse($target->hasPlatformRole());
        $this->assertDatabaseCount('users', 2);
    }

    public function test_platform_staff_editor_cannot_grant_or_remove_super_admin_access(): void
    {
        $admin = User::factory()->role('platform_admin')->create();
        $other = User::factory()->role('platform_admin')->create();
        $target = User::factory()->create();
        $this->actingAs($admin)->post('/workspace/members', ['email' => $target->email, 'roles' => ['platform_admin'], 'scope' => 'platform'])->assertSessionHasErrors('roles.0');
        $this->post('/workspace/members', ['email' => $other->email, 'roles' => ['support_agent'], 'scope' => 'platform'])->assertForbidden();
        $this->assertTrue($other->hasPlatformRole());
    }
}

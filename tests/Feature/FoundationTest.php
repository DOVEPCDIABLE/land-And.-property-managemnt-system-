<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Role;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_guests_are_redirected_to_login_and_login_screen_renders(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('citiESTATE');
    }

    public function test_each_role_lands_on_its_panel_and_cannot_open_other_panels(): void
    {
        foreach (['agency_admin' => 'organisation', 'agent' => 'organisation', 'accountant' => 'organisation', 'client' => 'personal'] as $role => $panel) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get('/dashboard')->assertRedirect('/panels/'.$panel);
            $this->get('/panels/'.$panel)->assertOk();
            $this->get('/panels/platform')->assertForbidden();
        }
    }

    public function test_platform_admin_cannot_open_organisation_panels_without_switching_membership(): void
    {
        $this->actingAs(User::factory()->role('platform_admin')->create())->get('/panels/platform')->assertOk();
        $this->get('/panels/organisation')->assertForbidden();
        $this->get('/panels/personal')->assertForbidden();
    }

    public function test_disabled_accounts_are_denied_and_logged_out(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user)->get('/dashboard')->assertForbidden();
        $this->assertGuest();
    }

    public function test_unverified_accounts_must_verify_first(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->get('/dashboard')->assertRedirect('/email/verify');
    }

    public function test_login_uses_hashed_password_and_rejects_inactive_accounts(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'TestPassword123!'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');
        $user->forceFill(['is_active' => false])->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'TestPassword123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'wrong']);
        } $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_agency_admin_sees_only_their_people(): void
    {
        $admin = User::factory()->role('agency_admin')->create();
        $local = User::factory()->create(['agency_id' => $admin->agency_id]);
        $foreign = User::factory()->create();
        $this->actingAs($admin)->get('/users')->assertOk()->assertSee($local->email)->assertDontSee($foreign->email);
    }

    public function test_clients_cannot_manage_users_or_payments(): void
    {
        $this->actingAs(User::factory()->create())->get('/users')->assertForbidden();
        $this->get('/payments')->assertForbidden();
        $this->get('/integrations')->assertForbidden();
    }

    public function test_user_creation_ignores_an_injected_agency_and_sends_verification(): void
    {
        Notification::fake();
        $admin = User::factory()->role('agency_admin')->create();
        $other = Agency::factory()->create();
        $this->actingAs($admin)->post('/users', ['name' => 'New Agent', 'email' => 'agent@example.test', 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123', 'role_id' => Role::where('name', 'agent')->sole()->id, 'agency_id' => $other->id])->assertSessionHasNoErrors();
        $user = User::where('email', 'agent@example.test')->sole();
        $this->assertNull($user->agency_id);
        $this->assertTrue($user->memberships()->where('workspace_id', $admin->currentWorkspace()->id)->exists());
        $this->assertNull($user->email_verified_at);
        $this->assertNotSame('SecurePassword123', $user->password);
        Notification::assertSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_agency_admin_cannot_assign_platform_role_or_edit_another_agency(): void
    {
        $admin = User::factory()->role('agency_admin')->create();
        $target = User::factory()->create();
        $data = ['name' => 'Changed', 'email' => $target->email, 'role_id' => Role::where('name', 'client')->sole()->id, 'is_active' => true];
        $this->actingAs($admin)->patch('/users/'.$target->id, $data)->assertNotFound();
        $data = ['name' => 'Elevated', 'email' => 'elevated@example.test', 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123', 'role_id' => Role::where('name', 'platform_admin')->sole()->id];
        $this->post('/users', $data)->assertSessionHasErrors('role_id');
    }

    public function test_admin_cannot_disable_their_own_account(): void
    {
        $admin = User::factory()->role('agency_admin')->create();
        $this->actingAs($admin)->patch('/users/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'role_id' => $admin->role_id, 'is_active' => false])->assertUnprocessable();
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_password_reset_changes_credentials_and_consumes_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('ChangedPassword123', $user->fresh()->password));
        $this->assertFalse(Password::tokenExists($user->fresh(), $token));
    }

    public function test_seeding_roles_is_idempotent(): void
    {
        $this->seed(AccessControlSeeder::class);
        $this->assertDatabaseCount('roles', count(config('workspaces.roles')));
        $this->assertSame(6, collect(config('ecosystem.modules'))->where('flagship', true)->count());
    }

    public function test_cli_can_provision_an_agency_administrator_without_default_credentials(): void
    {
        $this->artisan('ecosystem:admin', ['email' => 'owner@example.test', '--name' => 'Agency Owner', '--agency' => 'Test Estates'])
            ->expectsQuestion('Password (12+ characters, mixed case and numbers)', 'StrongPassword123!')
            ->assertSuccessful();
        $user = User::where('email', 'owner@example.test')->sole();
        $this->assertSame('agency_admin', $user->role->name);
        $this->assertSame('Test Estates', $user->agency->name);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('StrongPassword123!', $user->password));
    }

    public function test_cli_rejects_weak_administrator_passwords(): void
    {
        $this->artisan('ecosystem:admin', ['email' => 'owner@example.test'])
            ->expectsQuestion('Password (12+ characters, mixed case and numbers)', 'weak')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_even_a_disabled_platform_administrator_cannot_be_changed_via_the_web(): void
    {
        $admin = User::factory()->role('platform_admin')->create();
        $target = User::factory()->role('platform_admin')->create(['is_active' => false]);
        $agency = Agency::factory()->create();
        $this->actingAs($admin)->patch('/users/'.$target->id, ['name' => $target->name, 'email' => $target->email, 'role_id' => Role::where('name', 'client')->sole()->id, 'agency_id' => $agency->id, 'is_active' => true])->assertForbidden();
        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame('platform_admin', $target->fresh()->role->name);
    }

    public function test_login_success_message_survives_the_panel_redirect(): void
    {
        $user = User::factory()->role('platform_admin')->create();
        $this->followingRedirects()->post('/login', ['email' => $user->email, 'password' => 'TestPassword123!'])
            ->assertOk()
            ->assertSee('toast toast-success', false)
            ->assertSee('Welcome back. You are signed in.');
    }
}

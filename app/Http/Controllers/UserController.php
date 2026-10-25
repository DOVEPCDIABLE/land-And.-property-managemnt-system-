<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\Agency;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Tenancy;
use App\Services\WorkspaceProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request, Tenancy $tenancy): View|RedirectResponse
    {
        if ($request->user()->hasPermission('users.lookup') && ! $request->user()->hasPermission('users.manage')) {
            return redirect()->route('accounts.lookup');
        }
        $workspace = $request->user()->currentWorkspace();
        $allowed = collect(config('workspaces.roles'))->filter(fn ($r) => in_array('organisation', $r['allowed_kinds'] ?? [$r['kind']], true))->keys();

        $users = $tenancy->users($request->user())->latest()->paginate(20);

        return view('users', ['users' => $users, 'roles' => Role::whereIn('name', $allowed)->where('name', '!=', 'client')->get(), 'agencies' => $request->user()->isPlatformAdmin() ? Agency::where('status', 'active')->get() : collect(), 'memberships' => $workspace->memberships()->whereIn('user_id', $users->pluck('id'))->with(['roles', 'branch', 'team'])->get()->keyBy('user_id'), 'workspace' => $workspace, 'branches' => $workspace->branches()->get(), 'teams' => $workspace->teams()->get(), 'staffRoles' => Role::whereIn('name', ['compliance_officer', 'support_agent'])->get()]);
    }

    public function store(StoreUserRequest $request, Tenancy $tenancy, WorkspaceProvisioner $provisioner): RedirectResponse
    {
        $actor = $request->user();
        $data = $request->validated();
        $workspace = $actor->currentWorkspace();
        if ($actor->isPlatformAdmin()) {
            $agency = Agency::where('status', 'active')->findOrFail($data['agency_id']);
            $workspace = $provisioner->organisation($agency);
        }
        abort_unless($workspace->kind === 'organisation', 403);
        $user = DB::transaction(function () use ($actor, $data, $workspace, $tenancy): User {
            $user = new User(collect($data)->only(['name', 'email', 'password'])->all());
            $user->save();
            $membership = Membership::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'scope' => 'organisation']);
            $membership->roles()->attach($data['role_id']);
            $tenancy->audit($actor, 'user.created', (string) $user->id, ['workspace_id' => $workspace->id]);

            return $user;
        });
        $user->sendEmailVerificationNotification();

        return back()->with('status', 'Account created and added to the workspace. Verification email queued.');
    }

    public function update(StoreUserRequest $request, string $user, Tenancy $tenancy): RedirectResponse
    {
        $actor = $request->user();
        $target = $tenancy->users($actor)->findOrFail($user);
        abort_if($target->hasPlatformRole(), 403, 'Platform administrators are managed through the CLI.');
        abort_if($actor->id === $target->id, 422, 'You cannot change your own access here.');
        $data = $request->validated();
        DB::transaction(function () use ($actor, $target, $data, $request, $tenancy): void {
            if ($actor->isPlatformAdmin()) {
                $old = $target->email;
                $target->fill(collect($data)->only(['name', 'email'])->filter(fn ($v) => $v !== null)->all());
                if (! empty($data['password'])) {
                    $target->password = $data['password'];
                    $target->remember_token = null;
                }
                $target->is_active = $request->boolean('is_active');
                if ($old !== $target->email) {
                    $target->email_verified_at = null;
                }
                $target->save();
            } else {
                $member = Membership::where('workspace_id', $actor->currentWorkspace()->id)->where('user_id', $target->id)->lockForUpdate()->firstOrFail();
                $member->is_active = $request->boolean('is_active');
                $member->save();
                $member->roles()->sync([$data['role_id']]);
            }
            $tenancy->audit($actor, 'user.access_updated', (string) $target->id, ['global_restriction' => $actor->isPlatformAdmin()]);
        });

        return back()->with('status', $actor->isPlatformAdmin() ? 'Account access updated.' : 'Membership updated. Other workspaces and account credentials were not changed.');
    }
}

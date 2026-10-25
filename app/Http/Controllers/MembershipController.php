<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Tenancy;
use App\Services\WorkspaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MembershipController extends Controller
{
    public function store(Request $request, Tenancy $tenancy): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace();
        abort_unless(in_array($workspace->kind, ['organisation', 'platform'], true), 403);
        $platform = $workspace->kind === 'platform';
        $allowed = collect(config('workspaces.roles'))->filter(fn ($r, $key) => $platform ? ($r['kind'] === 'platform' && $key !== 'platform_admin') : in_array('organisation', $r['allowed_kinds'] ?? [$r['kind']], true))->keys()->all();
        $data = $request->validate(['email' => 'required|email|exists:users,email', 'roles' => 'required|array|min:1', 'roles.*' => ['required', Rule::in($allowed)], 'scope' => ['required', Rule::in($platform ? ['platform'] : ['own', 'assigned', 'team', 'branch', 'organisation'])], 'branch_id' => $platform ? 'prohibited' : ['nullable', Rule::exists('branches', 'id')->where('workspace_id', $workspace->id)], 'team_id' => $platform ? 'prohibited' : ['nullable', Rule::exists('teams', 'id')->where('workspace_id', $workspace->id)], 'is_active' => 'sometimes|boolean', 'allow_exports' => 'sometimes|boolean']);
        $target = User::where('email', $data['email'])->sole();
        abort_if($target->id === $request->user()->id, 422, 'You cannot change your own access.');
        abort_if($platform && $target->hasPlatformRole(), 403, 'Super administrator assignments are managed through the CLI.');
        if ($data['scope'] === 'branch') {
            abort_unless(! empty($data['branch_id']), 422, 'Select a branch for branch-scoped access.');
        }
        if ($data['scope'] === 'team') {
            abort_unless(! empty($data['team_id']), 422, 'Select a team for team-scoped access.');
        }
        if (! empty($data['team_id'])) {
            $team = Team::findOrFail($data['team_id']);
            abort_unless($team->branch_id === null || $team->branch_id == ($data['branch_id'] ?? null), 422, 'The team must belong to the selected branch.');
        }
        DB::transaction(function () use ($request, $workspace, $target, $data, $tenancy): void {
            $workspace->newQuery()->whereKey($workspace->id)->lockForUpdate()->first();
            $request->user()->unsetRelation('workspaceMembership');
            abort_unless($request->user()->hasPermission('users.manage') && $request->user()->currentWorkspace()->id === $workspace->id, 403);
            $membership = Membership::firstOrNew(['workspace_id' => $workspace->id, 'user_id' => $target->id]);
            $membership->fill(collect($data)->only(['scope', 'branch_id', 'team_id'])->all());
            $membership->is_active = $request->boolean('is_active', true);
            $membership->allow_exports = $request->boolean('allow_exports');
            $membership->save();
            $membership->roles()->sync(Role::whereIn('name', $data['roles'])->pluck('id'));
            $tenancy->audit($request->user(), 'membership.roles_updated', (string) $membership->id, ['roles' => $data['roles'], 'scope' => $membership->scope, 'active' => $membership->is_active]);
        });

        return back()->with('status', 'Workspace membership updated. The account and its other workspaces were not changed.');
    }

    public function branches(Request $request): View
    {
        $workspace = $request->user()->currentWorkspace();

        $membership = $request->user()->currentMembership();
        $scope = app(WorkspaceAccess::class)->scope($request->user(), 'branches.view');
        $branches = $workspace->branches();
        $teams = $workspace->teams();
        if (in_array($scope, ['branch', 'team'], true)) {
            $branches->whereKey($membership->branch_id ?? 0);
            $teams->where('branch_id', $membership->branch_id ?? 0);
            if ($scope === 'team') {
                $teams->whereKey($membership->team_id ?? 0);
            }
        }

        return view('workspaces.branches', ['branches' => $branches->get(), 'teams' => $teams->get()]);
    }

    public function branch(Request $request, Tenancy $tenancy): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'kind' => 'required|in:branch,team', 'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('workspace_id', $request->user()->currentWorkspace()->id)]]);
        $workspace = $request->user()->currentWorkspace();
        abort_unless($workspace->kind === 'organisation', 403);
        $record = $data['kind'] === 'branch' ? Branch::firstOrCreate(['workspace_id' => $workspace->id, 'name' => $data['name']]) : Team::firstOrCreate(['workspace_id' => $workspace->id, 'name' => $data['name']], ['branch_id' => $data['branch_id'] ?? null]);
        $tenancy->audit($request->user(), 'workspace.'.$data['kind'].'_created', (string) $record->id);

        return back()->with('status', ucfirst($data['kind']).' saved.');
    }
}

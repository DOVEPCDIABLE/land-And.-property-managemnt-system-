<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\Tenancy;
use App\Services\WorkspaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function index(Request $request, WorkspaceAccess $access): View
    {
        return view('workspaces.index', ['memberships' => $access->available($request->user())]);
    }

    public function switch(Request $request, string $workspace, WorkspaceAccess $access, Tenancy $tenancy): RedirectResponse
    {
        $membership = $access->available($request->user())->firstWhere('workspace_id', (int) $workspace);
        abort_unless($membership, 403, 'You do not have active access to this workspace.');
        $previous = $request->user()->currentWorkspace()->id;
        $request->session()->put('workspace_id', $membership->workspace_id);
        $request->user()->setRelation('workspaceMembership', $membership->load('roles.permissions'));
        $tenancy->audit($request->user(), 'workspace.switched', (string) $membership->workspace_id, ['from' => $previous]);

        return redirect()->route('dashboard')->with('status', 'Switched to '.$membership->workspace->name.'.');
    }

    public function settings(Request $request): View
    {
        return view('workspaces.settings', ['workspace' => $request->user()->currentWorkspace(), 'modules' => config('workspaces.modules')]);
    }

    public function update(Request $request, Tenancy $tenancy): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'enabled_modules' => 'sometimes|array', 'enabled_modules.*' => ['required', Rule::in(array_keys(config('workspaces.modules')))]]);
        $workspace = $request->user()->currentWorkspace();
        DB::transaction(function () use ($workspace, $data, $request, $tenancy): void {
            $workspace->update(['name' => $data['name'], 'enabled_modules' => array_values(array_unique($data['enabled_modules'] ?? []))]);
            $tenancy->audit($request->user(), 'workspace.settings_updated', (string) $workspace->id);
        });

        return back()->with('status', 'Workspace settings updated. Disabled modules are also blocked on the server.');
    }

    public function audit(Request $request, WorkspaceAccess $access): View
    {
        $query = $access->records(AuditLog::query(), $request->user(), 'audit.view');

        return view('workspaces.audit',['logs' => $query->latest()->paginate(30)]);
    }
}

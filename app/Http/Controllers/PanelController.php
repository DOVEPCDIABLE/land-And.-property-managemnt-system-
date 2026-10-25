<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\Payment;
use App\Services\Tenancy;
use App\Services\WorkspaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PanelController extends Controller
{
    public function home(Request $request): RedirectResponse
    {
        $request->session()->keep(['status', 'success', 'error', 'warning', 'info']);

        return redirect()->route('panels.show', $request->user()->homePanel());
    }

    public function show(Request $request, string $panel, Tenancy $tenancy, WorkspaceAccess $access): View
    {
        $workspace = $request->user()->currentWorkspace();
        $kind = match ($panel) {
            'agency','agent' => 'organisation','client' => 'personal',default => $panel
        };
        abort_unless($kind === $workspace->kind && $request->user()->hasPermission('panel.'.$workspace->kind), 403);
        $roles = $request->user()->currentMembership()->roles->pluck('name');
        $permitted = collect(config('workspaces.modules'))->filter(fn ($definition, $module) => $request->user()->hasPermission($module.'.view'));

        return view('dashboard', ['workspace' => $workspace, 'roles' => $roles, 'users' => $tenancy->users($request->user())->count(), 'followups' => $tenancy->records(FollowUp::query(), $request->user())->where('status', 'pending')->count(), 'payments' => $tenancy->records(Payment::query(), $request->user())->count(), 'modules' => $permitted]);
    }

    public function integrations(): View
    {
        if (auth()->user()->currentWorkspace()->kind !== 'platform') {
            return view('workspaces.integrations');
        }

        return view('integrations');
    }
}

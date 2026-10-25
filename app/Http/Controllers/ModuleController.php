<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Tenancy;
use App\Services\WorkspaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function show(Request $request, string $module, WorkspaceAccess $access): View|RedirectResponse
    {
        $definition = config('workspaces.modules.'.$module);
        abort_unless($definition, 404);
        abort_unless($request->user()->hasPermission($module.'.view'), 403);
        if ($definition['implemented']) {
            return redirect()->to($access->url($module));
        }
        if ($module === 'users' && $request->user()->hasPermission('users.lookup')) {
            return view('workspaces.lookup');
        }

        return view('workspaces.module', ['module' => $module, 'definition' => $definition, 'scope' => $access->scope($request->user(), $module.'.view')]);
    }

    public function lookup(Request $request, Tenancy $tenancy): View
    {
        $query = $request->validate(['email' => 'nullable|email|max:255']);
        $account = isset($query['email']) ? User::where('email', $query['email'])->first(['id', 'name', 'email', 'is_active', 'email_verified_at']) : null;

        if ($account) {
            $tenancy->audit($request->user(), 'support.user_lookup', (string) $account->id);
        }

        return view('workspaces.lookup', compact('account'));
    }
}

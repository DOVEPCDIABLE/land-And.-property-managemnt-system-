<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Tenancy;
use App\Services\WorkspaceProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrganisationController extends Controller
{
    public function index(): View
    {
        return view('workspaces.organisations', ['organisations' => Agency::latest()->paginate(20)]);
    }

    public function store(Request $request, WorkspaceProvisioner $provisioner, Tenancy $tenancy): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'type' => 'required|in:agency,developer,property_management', 'admin_email' => 'required|email|exists:users,email']);
        DB::transaction(function () use ($data, $provisioner, $tenancy, $request): void {
            $agency = Agency::create(['name' => $data['name'], 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)), 'type' => $data['type'], 'status' => 'active']);
            $workspace = $provisioner->organisation($agency);
            $owner = User::where('email', $data['admin_email'])->sole();
            $membership = Membership::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'scope' => 'organisation']);
            $membership->roles()->attach(Role::where('name', $data['type'] === 'developer' ? 'developer_admin' : 'agency_admin')->sole()->id);
            $tenancy->audit($request->user(), 'organisation.created', (string) $agency->id, ['administrator_id' => $owner->id]);
        });

        return back()->with('status', 'Organisation created and its existing account assigned as administrator.');
    }

    public function update(Request $request, string $agency, Tenancy $tenancy): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:active,suspended,pending']);
        $organisation = Agency::findOrFail($agency);
        DB::transaction(function () use ($organisation, $data, $request, $tenancy): void {
            $organisation->update($data);
            $tenancy->audit($request->user(), 'organisation.status_changed', (string) $organisation->id, $data);
        });

        return back()->with('status','Organisation status updated. Suspended organisations cannot be selected or accessed.');
    }
}

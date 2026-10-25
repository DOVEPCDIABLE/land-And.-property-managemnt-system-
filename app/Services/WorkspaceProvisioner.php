<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;

class WorkspaceProvisioner
{
    public function personal(User $user): Workspace
    {
        $workspace = Workspace::firstOrCreate(['owner_user_id' => $user->id], ['name' => $user->name.' · Personal', 'slug' => 'personal-'.$user->id, 'kind' => 'personal', 'enabled_modules' => config('workspaces.default_modules')]);
        $membership = Membership::firstOrCreate(['workspace_id' => $workspace->id, 'user_id' => $user->id], ['scope' => 'own']);
        if (! $membership->roles()->exists()) {
            $membership->roles()->attach(Role::firstOrCreate(['name' => 'buyer'])->id);
        }

        return $workspace;
    }

    public function organisation(Agency $agency): Workspace
    {
        return Workspace::firstOrCreate(['agency_id' => $agency->id], ['name' => $agency->name, 'slug' => 'organisation-'.$agency->id, 'kind' => 'organisation', 'enabled_modules' => config('workspaces.default_modules')]);
    }

    public function platform(): Workspace
    {
        return Workspace::firstOrCreate(['slug' => 'platform'], ['name' => app(PlatformSettings::class)->get('brand_name').' platform', 'kind' => 'platform', 'enabled_modules' => config('workspaces.default_modules')]);
    }

    public function forUser(User $user): void
    {
        $personal = $this->personal($user);
        $role = $user->role;
        if (! $role) {
            return;
        }
        $definition = config('workspaces.roles.'.$role->name);
        if (! $definition) {
            return;
        }
        if ($definition['kind'] === 'platform') {
            $workspace = $this->platform();
        } elseif ($definition['kind'] === 'personal') {
            $workspace = $personal;
        } elseif ($user->agency) {
            $workspace = $this->organisation($user->agency);
        } else {
            return;
        }
        $membership = Membership::firstOrCreate(['workspace_id' => $workspace->id, 'user_id' => $user->id], ['scope' => $workspace->kind === 'organisation' ? 'organisation' : ($workspace->kind === 'platform' ? 'platform' : 'own')]);
        $membership->roles()->syncWithoutDetaching([$role->id]);
        if ($user->agency_id && $workspace->kind === 'personal') {
            $organisation = $this->organisation($user->agency);
            $member = Membership::firstOrCreate(['workspace_id' => $organisation->id, 'user_id' => $user->id], ['scope' => 'organisation']);
            $member->roles()->syncWithoutDetaching([$role->id]);
        }
    }
}

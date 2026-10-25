<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = config('workspaces.roles');
        $now = now();
        $names = collect($definitions)->flatMap(fn ($r) => array_keys($r['permissions']))->unique();
        Permission::insertOrIgnore($names->map(fn ($name) => ['name' => $name, 'created_at' => $now, 'updated_at' => $now])->values()->all());
        Role::insertOrIgnore(collect(array_keys($definitions))->map(fn ($name) => ['name' => $name, 'created_at' => $now, 'updated_at' => $now])->all());
        $permissions = Permission::all()->keyBy('name');
        $roles = Role::all()->keyBy('name');
        foreach ($definitions as $name => $definition) {
            $ids = [];
            foreach ($definition['permissions'] as $permission => $scope) {
                $ids[$permissions[$permission]->id] = ['scope' => $scope];
            }$roles[$name]->permissions()->sync($ids);
        }
    }
}

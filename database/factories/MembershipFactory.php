<?php

namespace Database\Factories;

use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class MembershipFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'user_id' => User::factory(), 'scope' => 'organisation', 'is_active' => true];
    }

    public function roles(array $names): static
    {
        return $this->afterCreating(fn (Membership $m) => $m->roles()->sync(Role::whereIn('name', $names)->pluck('id')));
    }
}

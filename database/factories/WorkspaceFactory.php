<?php

namespace Database\Factories;

use App\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WorkspaceFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->company(), 'slug' => 'workspace-'.Str::uuid(), 'kind' => 'organisation', 'agency_id' => Agency::factory(), 'enabled_modules' => config('workspaces.default_modules'), 'is_active' => true];
    }
}

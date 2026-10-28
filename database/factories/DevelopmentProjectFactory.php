<?php

namespace Database\Factories;

use App\Models\DevelopmentProject;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DevelopmentProject> */
class DevelopmentProjectFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'name' => 'Residential development', 'location' => 'Abuja', 'budget_minor' => 100000000, 'status' => 'planning', 'progress' => 0];
    }
}

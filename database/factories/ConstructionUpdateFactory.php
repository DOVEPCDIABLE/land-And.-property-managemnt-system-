<?php

namespace Database\Factories;

use App\Models\ConstructionUpdate;
use App\Models\DevelopmentProject;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConstructionUpdate> */
class ConstructionUpdateFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'name' => 'Foundation milestone', 'project_id' => DevelopmentProject::factory(), 'recorded_on' => today(), 'progress' => 10, 'description' => 'Foundation works completed.', 'cost_minor' => 10000];
    }
}

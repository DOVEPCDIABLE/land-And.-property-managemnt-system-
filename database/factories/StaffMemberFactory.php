<?php

namespace Database\Factories;

use App\Models\StaffMember;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffMember> */
class StaffMemberFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'name' => fake()->name(), 'position' => 'Property administrator', 'started_on' => today(), 'salary_minor' => 100000, 'status' => 'active'];
    }
}

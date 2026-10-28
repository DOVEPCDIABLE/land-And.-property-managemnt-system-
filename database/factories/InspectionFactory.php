<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\Property;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Inspection> */
class InspectionFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'property_id' => Property::factory(), 'name' => 'Routine inspection', 'inspected_on' => today(), 'status' => 'scheduled'];
    }
}

<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PropertyUnit> */
class PropertyUnitFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'property_id' => Property::factory(), 'name' => fake()->unique()->bothify('Unit ###'), 'price_minor' => 100000, 'status' => 'available'];
    }
}

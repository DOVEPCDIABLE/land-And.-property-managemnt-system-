<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Property> */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'name' => fake()->streetName(), 'slug' => fake()->unique()->slug(), 'location' => 'Abuja', 'type' => 'house', 'purpose' => 'rent', 'description' => fake()->paragraph(), 'price_minor' => 100000, 'currency' => 'NGN', 'minor_unit' => 2, 'amenities' => [], 'status' => 'available', 'publication_status' => 'draft'];
    }
}

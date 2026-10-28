<?php

namespace Database\Factories;

use App\Models\ServiceListing;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceListing> */
class ServiceListingFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'name' => fake()->company(), 'profession' => 'photographer', 'location' => 'Abuja', 'description' => 'Property photography services.', 'fee_minor' => 10000, 'status' => 'draft'];
    }
}

<?php

namespace Database\Factories;

use App\Models\ServiceBooking;
use App\Models\ServiceListing;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceBooking> */
class ServiceBookingFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'name' => 'Property photography request', 'service_listing_id' => ServiceListing::factory(), 'description' => 'Photograph a property.', 'requested_on' => today()->addWeek(), 'status' => 'requested'];
    }
}

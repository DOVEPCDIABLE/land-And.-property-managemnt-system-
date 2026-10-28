<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lease> */
class LeaseFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'property_id' => Property::factory(), 'contact_id' => Contact::factory(), 'name' => 'Annual tenancy', 'starts_on' => today(), 'ends_on' => today()->addYear(), 'rent_minor' => 100000, 'period' => 'annually', 'status' => 'draft'];
    }
}

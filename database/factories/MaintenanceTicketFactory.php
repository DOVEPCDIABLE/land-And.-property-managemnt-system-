<?php

namespace Database\Factories;

use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceTicket> */
class MaintenanceTicketFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'property_id' => Property::factory(), 'name' => 'Repair request', 'description' => 'Inspect and repair the fitting.', 'priority' => 'normal', 'status' => 'open'];
    }
}

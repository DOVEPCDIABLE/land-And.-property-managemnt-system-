<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'name' => 'Property viewing', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'status' => 'scheduled'];
    }
}

<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contact> */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'name' => fake()->name(), 'email' => fake()->safeEmail(), 'type' => 'tenant'];
    }
}

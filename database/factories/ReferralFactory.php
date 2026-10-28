<?php

namespace Database\Factories;

use App\Models\Referral;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Referral> */
class ReferralFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'name' => 'Buyer introduction', 'target_workspace_id' => Workspace::factory(), 'client_name' => fake()->name(), 'terms' => 'Confirm the agreed terms before proceeding.', 'sharing_consent' => true, 'status' => 'submitted'];
    }
}

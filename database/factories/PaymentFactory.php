<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\WorkspaceProvisioner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return ['reference' => (string) Str::uuid(), 'user_id' => User::factory(), 'recorded_by' => fn (array $a) => $a['user_id'], 'agency_id' => fn (array $a) => User::findOrFail($a['user_id'])->agency_id, 'workspace_id' => fn (array $a) => $a['agency_id'] ? app(WorkspaceProvisioner::class)->organisation(Agency::findOrFail($a['agency_id']))->id : app(WorkspaceProvisioner::class)->personal(User::findOrFail($a['user_id']))->id, 'provider' => 'paystack', 'amount_minor' => 250000, 'currency' => 'NGN', 'minor_unit' => fn (array $a) => app(CurrencyService::class)->minorUnit($a['currency']), 'purpose' => 'Platform subscription', 'status' => 'pending'];
    }
}

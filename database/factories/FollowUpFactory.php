<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\User;
use App\Services\WorkspaceProvisioner;
use Illuminate\Database\Eloquent\Factories\Factory;

class FollowUpFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory()->role('agent'), 'created_by' => fn (array $a) => $a['user_id'], 'agency_id' => fn (array $a) => User::findOrFail($a['user_id'])->agency_id, 'workspace_id' => fn (array $a) => $a['agency_id'] ? app(WorkspaceProvisioner::class)->organisation(Agency::findOrFail($a['agency_id']))->id : app(WorkspaceProvisioner::class)->personal(User::findOrFail($a['user_id']))->id, 'subject' => 'Viewing reminder', 'message' => 'Please confirm your viewing.', 'channels' => ['database'], 'due_at' => now()->subMinute(), 'status' => 'pending'];
    }
}

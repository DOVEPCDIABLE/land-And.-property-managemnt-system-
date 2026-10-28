<?php

namespace Database\Factories;

use App\Models\PayrollEntry;
use App\Models\StaffMember;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayrollEntry> */
class PayrollEntryFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'name' => 'Monthly payroll', 'staff_member_id' => StaffMember::factory(), 'period' => today()->format('Y-m'), 'gross_minor' => 100000, 'net_minor' => 100000, 'status' => 'draft'];
    }
}

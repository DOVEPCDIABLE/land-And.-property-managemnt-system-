<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Expense> */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'name' => 'Office expense', 'category' => 'administration', 'incurred_on' => today(), 'amount_minor' => 10000, 'status' => 'recorded'];
    }
}

<?php

namespace App\Models;

class Expense extends OperationalModel
{
    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'minor_unit' => 'integer', 'incurred_on' => 'date'];
    }
}

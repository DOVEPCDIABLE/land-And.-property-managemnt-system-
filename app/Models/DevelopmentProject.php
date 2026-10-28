<?php

namespace App\Models;

class DevelopmentProject extends OperationalModel
{
    protected function casts(): array
    {
        return ['budget_minor' => 'integer', 'progress' => 'integer', 'minor_unit' => 'integer', 'starts_on' => 'date', 'target_date' => 'date'];
    }
}

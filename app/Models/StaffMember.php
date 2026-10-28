<?php

namespace App\Models;

class StaffMember extends OperationalModel
{
    protected function casts(): array
    {
        return ['salary_minor' => 'integer', 'minor_unit' => 'integer', 'started_on' => 'date'];
    }
}

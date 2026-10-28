<?php

namespace App\Models;

class MaintenanceTicket extends OperationalModel
{
    protected function casts(): array
    {
        return ['cost_minor' => 'integer', 'minor_unit' => 'integer', 'due_on' => 'date'];
    }
}

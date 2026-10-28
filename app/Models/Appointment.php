<?php

namespace App\Models;

class Appointment extends OperationalModel
{
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'reminder_at' => 'datetime', 'reminder_queued_at' => 'datetime', 'reminded_at' => 'datetime', 'reminder_version' => 'integer'];
    }
}

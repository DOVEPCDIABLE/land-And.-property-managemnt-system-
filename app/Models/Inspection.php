<?php

namespace App\Models;

class Inspection extends OperationalModel
{
    protected function casts(): array
    {
        return ['inspected_on' => 'date'];
    }
}

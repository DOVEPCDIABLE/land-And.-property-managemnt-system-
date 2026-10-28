<?php

namespace App\Models;

class BusinessDocument extends OperationalModel
{
    protected function casts(): array
    {
        return ['expires_on' => 'date', 'size' => 'integer'];
    }
}

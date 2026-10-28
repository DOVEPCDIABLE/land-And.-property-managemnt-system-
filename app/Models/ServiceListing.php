<?php

namespace App\Models;

class ServiceListing extends OperationalModel
{
    protected function casts(): array
    {
        return ['fee_minor' => 'integer', 'minor_unit' => 'integer'];
    }
}

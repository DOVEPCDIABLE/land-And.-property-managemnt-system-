<?php

namespace App\Models;

class PropertyMedia extends OperationalModel
{
    protected function casts(): array
    {
        return ['is_cover' => 'boolean', 'is_public' => 'boolean'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyUnit extends OperationalModel
{
    protected function casts(): array
    {
        return ['sold_on' => 'date', 'sale_price_minor' => 'integer', 'price_minor' => 'integer', 'minor_unit' => 'integer'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}

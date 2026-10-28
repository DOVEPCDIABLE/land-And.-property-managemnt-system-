<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lease extends OperationalModel
{
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'rent_minor' => 'integer', 'deposit_minor' => 'integer', 'minor_unit' => 'integer'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'unit_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}

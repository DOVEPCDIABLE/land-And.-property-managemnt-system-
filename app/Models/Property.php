<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends OperationalModel
{
    protected function casts(): array
    {
        return ['price_minor' => 'integer', 'minor_unit' => 'integer', 'beds' => 'integer', 'baths' => 'integer', 'furnished' => 'boolean', 'is_concept' => 'boolean', 'amenities' => 'array'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProject::class, 'project_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(PropertyMedia::class)->orderByDesc('is_cover')->orderBy('sort_order');
    }

    public function units(): HasMany
    {
        return $this->hasMany(PropertyUnit::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstructionUpdate extends OperationalModel
{
    protected function casts(): array
    {
        return ['progress' => 'integer', 'cost_minor' => 'integer', 'minor_unit' => 'integer', 'recorded_on' => 'date'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProject::class, 'project_id');
    }
}

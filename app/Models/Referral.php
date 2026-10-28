<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends OperationalModel
{
    protected function casts(): array
    {
        return ['sharing_consent' => 'boolean'];
    }

    public function targetWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'target_workspace_id');
    }
}

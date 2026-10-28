<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class OperationalModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}

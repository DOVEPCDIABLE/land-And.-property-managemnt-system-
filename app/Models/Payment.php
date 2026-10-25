<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = ['workspace_id', 'branch_id', 'team_id', 'assigned_to', 'reference', 'agency_id', 'user_id', 'recorded_by', 'provider', 'amount_minor', 'currency', 'minor_unit', 'purpose', 'status', 'external_reference', 'provider_transaction_id', 'checkout_url', 'instructions', 'paid_at'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'minor_unit' => 'integer', 'instructions' => 'array', 'paid_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

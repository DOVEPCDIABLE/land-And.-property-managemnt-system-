<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends OperationalModel
{
    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'minor_unit' => 'integer', 'received_on' => 'date'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends OperationalModel
{
    protected function casts(): array
    {
        return ['customer_snapshot' => 'array', 'issuer_snapshot' => 'array', 'items' => 'array', 'issued_on' => 'date', 'due_on' => 'date', 'total_minor' => 'integer', 'subtotal_minor' => 'integer', 'tax_minor' => 'integer', 'minor_unit' => 'integer'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function balanceMinor(): int
    {
        return $this->total_minor - (int) $this->receipts()->sum('amount_minor');
    }

    public function paymentState(): string
    {
        if ($this->status !== 'issued') {
            return $this->status;
        }
        $balance = $this->balanceMinor();

        return $balance === 0 ? 'paid' : ($balance < $this->total_minor ? 'part paid' : 'awaiting payment');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBooking extends OperationalModel
{
    protected function casts(): array
    {
        return ['sharing_consent' => 'boolean', 'requester_snapshot' => 'array', 'quote_minor' => 'integer', 'minor_unit' => 'integer', 'requested_on' => 'date'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ServiceListing::class, 'service_listing_id');
    }
}

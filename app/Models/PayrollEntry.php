<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEntry extends OperationalModel
{
    protected function casts(): array
    {
        return ['gross_minor' => 'integer', 'allowances_minor' => 'integer', 'deductions_minor' => 'integer', 'net_minor' => 'integer', 'minor_unit' => 'integer', 'paid_on' => 'date'];
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'staff_member_id');
    }
}

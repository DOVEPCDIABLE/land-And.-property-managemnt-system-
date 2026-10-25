<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = ['base_currency', 'quote_currency', 'rate', 'updated_by'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:12'];
    }
}

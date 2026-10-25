<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'symbol', 'minor_unit', 'is_enabled', 'is_standard'];

    protected function casts(): array
    {
        return ['minor_unit' => 'integer', 'is_enabled' => 'boolean', 'is_standard' => 'boolean'];
    }
}

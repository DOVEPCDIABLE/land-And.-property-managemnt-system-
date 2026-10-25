<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = ['values', 'revision', 'updated_by'];

    protected function casts(): array
    {
        return ['values' => 'array', 'revision' => 'integer'];
    }
}

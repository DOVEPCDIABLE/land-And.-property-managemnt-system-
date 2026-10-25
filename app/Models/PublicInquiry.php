<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicInquiry extends Model
{
    protected $fillable = ['name', 'email', 'intent', 'message', 'preferences', 'status'];

    protected function casts(): array
    {
        return ['preferences' => 'array'];
    }
}

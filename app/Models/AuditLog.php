<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['workspace_id', 'branch_id', 'team_id', 'assigned_to', 'agency_id', 'user_id', 'action', 'subject', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUp extends Model
{
    use HasFactory;

    protected $fillable = ['workspace_id', 'branch_id', 'team_id', 'assigned_to', 'agency_id', 'created_by', 'user_id', 'subject', 'message', 'channels', 'due_at', 'status', 'sent_at'];

    protected function casts(): array
    {
        return ['channels' => 'array', 'due_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

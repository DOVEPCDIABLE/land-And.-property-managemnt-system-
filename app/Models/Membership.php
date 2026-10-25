<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Membership extends Model
{
    use HasFactory;

    protected $fillable = ['workspace_id', 'user_id', 'branch_id', 'team_id', 'scope', 'profession', 'is_active', 'allow_exports'];

    protected $attributes = ['is_active' => true, 'allow_exports' => false];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'allow_exports' => 'boolean'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}

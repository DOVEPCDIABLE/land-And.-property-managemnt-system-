<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'kind', 'agency_id', 'owner_user_id', 'enabled_modules', 'is_active'];

    protected function casts(): array
    {
        return ['enabled_modules' => 'array', 'is_active' => 'boolean'];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function available(): bool
    {
        return $this->is_active && ($this->kind !== 'organisation' || $this->agency?->status === 'active');
    }

    public function moduleEnabled(string $module): bool
    {
        return in_array($module, ['overview', 'notifications', 'profile', 'security', 'preferences', 'settings', 'browse'], true) || in_array($module, $this->enabled_modules ?? [], true);
    }
}

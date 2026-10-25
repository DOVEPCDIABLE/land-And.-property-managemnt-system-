<?php

namespace App\Models;

use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceProvisioner;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $attributes = ['is_active' => true];

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean', 'notification_preferences' => 'array'];
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    protected static function booted(): void
    {
        static::created(fn (User $user) => app(WorkspaceProvisioner::class)->forUser($user));
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function currentMembership(): ?Membership
    {
        return app(WorkspaceAccess::class)->membership($this);
    }

    public function currentWorkspace(): ?Workspace
    {
        return $this->currentMembership()?->workspace;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->is_active && $this->currentWorkspace()?->kind === 'platform' && ($this->currentMembership()?->roles->contains('name', 'platform_admin') ?? false);
    }

    public function hasPlatformRole(): bool
    {
        return $this->memberships()->whereHas('workspace', fn ($q) => $q->where('kind', 'platform'))->whereHas('roles', fn ($q) => $q->where('name', 'platform_admin'))->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return app(WorkspaceAccess::class)->scope($this, $permission) !== null;
    }

    public function homePanel(): string
    {
        return $this->currentWorkspace()?->kind ?? abort(403);
    }

    public function workspaceNotifications(): MorphMany
    {
        $id = $this->currentWorkspace()?->id;

        return $this->notifications()->where(fn ($query) => $query->where('data->workspace_id', $id)->orWhereNull('data->workspace_id'));
    }
}

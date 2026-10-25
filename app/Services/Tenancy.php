<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class Tenancy
{
    public function users(User $actor): Builder
    {
        $access = app(WorkspaceAccess::class);
        $m = $access->membership($actor);
        $query = User::query()->with('role');
        if (! $m) {
            return $query->whereRaw('1=0');
        }
        if ($actor->isPlatformAdmin()) {
            return $query;
        }
        $scope = $access->scope($actor, 'users.view') ?? $access->scope($actor, 'payments.create') ?? $access->scope($actor, 'followups.create') ?? $access->scope($actor, 'notifications.send');
        if (! $scope || $m->workspace->kind === 'personal') {
            return $query->whereKey($actor->id);
        }

        return $query->whereHas('memberships', function (Builder $q) use ($m, $scope): void {
            $q->where('workspace_id', $m->workspace_id)->where('is_active', true);
            if ($scope === 'branch') {
                $m->branch_id ? $q->where('branch_id', $m->branch_id) : $q->whereRaw('1=0');
            } elseif ($scope === 'team') {
                $m->team_id ? $q->where('team_id', $m->team_id) : $q->whereRaw('1=0');
            } elseif (in_array($scope, ['own', 'assigned'], true)) {
                $q->where('user_id', $m->user_id);
            }
        });
    }

    public function recipients(User $actor, string $permission): Builder
    {
        $access = app(WorkspaceAccess::class);
        $member = $access->membership($actor);
        $scope = $access->scope($actor, $permission);
        $query = User::query()->where('is_active', true);
        if (! $member || ! $scope) {
            return $query->whereRaw('1=0');
        }
        if ($scope === 'platform' && in_array($permission, ['payments.create', 'notifications.send'], true)) {
            return $query;
        }
        $query->whereHas('memberships', function (Builder $q) use ($member, $scope, $actor): void {
            $q->where('workspace_id', $member->workspace_id)->where('is_active', true);
            if ($scope === 'branch') {
                $q->where('branch_id', $member->branch_id ?? 0);
            } elseif ($scope === 'team') {
                $q->where('team_id', $member->team_id ?? 0);
            } elseif (in_array($scope, ['own', 'assigned'], true)) {
                $q->where('user_id', $actor->id);
            }
        });

        return $query;
    }

    public function records(Builder $query, User $actor): Builder
    {
        return app(WorkspaceAccess::class)->records($query, $actor, $query->getModel() instanceof Payment ? 'payments.view' : 'followups.view');
    }

    public function audit(User $actor, string $action, string $subject, array $metadata = []): void
    {
        $m = $actor->currentMembership();
        AuditLog::create(['workspace_id' => $m?->workspace_id, 'agency_id' => $m?->workspace->agency_id, 'user_id' => $actor->id, 'action' => $action, 'subject' => $subject, 'metadata' => $metadata ?: null]);
    }

    public function attributes(User $actor, ?User $recipient = null): array
    {
        $m = $actor->currentMembership();
        $target = $recipient?->memberships()->where('workspace_id', $m->workspace_id)->where('is_active', true)->first();

        return ['workspace_id' => $m->workspace_id, 'agency_id' => $m->workspace->agency_id, 'branch_id' => $target?->branch_id ?? $m->branch_id, 'team_id' => $target?->team_id ?? $m->team_id];
    }
}

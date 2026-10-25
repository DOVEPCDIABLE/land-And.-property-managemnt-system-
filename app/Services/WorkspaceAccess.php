<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WorkspaceAccess
{
    private const RANK = ['own' => 0, 'assigned' => 1, 'team' => 2, 'branch' => 3, 'organisation' => 4, 'platform' => 5];

    public function membership(User $user): ?Membership
    {
        if ($user->relationLoaded('workspaceMembership')) {
            return $user->getRelation('workspaceMembership');
        }
        $memberships = $user->memberships()->where('is_active', true)->with(['workspace.agency', 'roles.permissions', 'branch', 'team'])->get()->filter(fn (Membership $m): bool => $m->workspace->available());
        $id = app()->bound('request') && request()->hasSession() ? request()->session()->get('workspace_id') : null;
        $selected = $id ? $memberships->firstWhere('workspace_id', (int) $id) : null;
        if (! $selected) {
            $selected = $memberships->first(fn ($m) => $m->workspace->kind === 'platform') ?? $memberships->first(fn ($m) => $m->workspace->kind === 'organisation' && $m->roles->contains(fn ($r) => config('workspaces.roles.'.$r->name.'.kind') === 'organisation')) ?? $memberships->first();
        }
        $user->setRelation('workspaceMembership', $selected);
        if ($selected && app()->bound('request') && request()->hasSession()) {
            request()->session()->put('workspace_id', $selected->workspace_id);
        }

        return $selected;
    }

    public function available(User $user): Collection
    {
        return $user->memberships()->where('is_active', true)->with(['workspace.agency', 'roles'])->get()->filter(fn ($m) => $m->workspace->available());
    }

    public function scope(User $user, string $permission): ?string
    {
        if (! $user->is_active) {
            return null;
        }
        $membership = $this->membership($user);
        if (! $membership || ! $membership->is_active || ! $membership->workspace->available()) {
            return null;
        }
        $module = explode('.', $permission)[0];
        if ($module === 'finance') {
            $module = 'payments';
        }
        if ($module !== 'panel' && ! $membership->workspace->moduleEnabled($module)) {
            return null;
        }
        if (str_ends_with($permission, '.export') && ! $membership->allow_exports) {
            return null;
        }
        $scopes = [];
        foreach ($membership->roles as $role) {
            $definition = config('workspaces.roles.'.$role->name);
            if (! $definition) {
                continue;
            }
            $kinds = $definition['allowed_kinds'] ?? [$definition['kind']];
            if (! in_array($membership->workspace->kind, $kinds, true)) {
                continue;
            }
            $grant = $role->permissions->firstWhere('name', $permission);
            if (! $grant) {
                continue;
            }
            $scope = $grant->pivot->scope;
            if ($scope === 'platform' && $membership->workspace->kind !== 'platform') {
                continue;
            }
            $rank = min(self::RANK[$scope] ?? 0, self::RANK[$membership->scope] ?? 0);
            if ($membership->workspace->kind === 'personal') {
                $rank = 0;
            }
            $scopes[] = array_search($rank, self::RANK, true);
        }
        usort($scopes, fn ($a, $b) => self::RANK[$b] <=> self::RANK[$a]);

        return $scopes[0] ?? null;
    }

    public function records(Builder $query, User $user, string $permission): Builder
    {
        $m = $this->membership($user);
        $scope = $this->scope($user, $permission);
        if (! $m || ! $scope) {
            return $query->whereRaw('1 = 0');
        }
        $query->where($query->getModel()->qualifyColumn('workspace_id'), $m->workspace_id);

        return match ($scope) {
            'platform','organisation' => $query,
            'branch' => $m->branch_id ? $query->where('branch_id', $m->branch_id) : $query->whereRaw('1 = 0'),
            'team' => $m->team_id ? $query->where('team_id', $m->team_id) : $query->whereRaw('1 = 0'),
            'assigned' => $query->where(fn (Builder $q) => $q->where('user_id', $user->id)->orWhere('assigned_to', $user->id)),
            default => $query->where('user_id', $user->id),
        };
    }

    public function navigation(User $user): array
    {
        $m = $this->membership($user);
        if (! $m) {
            return [];
        }
        $items = [];
        foreach ($m->roles as $role) {
            foreach (config('workspaces.roles.'.$role->name.'.menus', []) as $menu) {
                $key = $menu['module'];
                if (isset($items[$key]) || ! $this->scope($user, $key.'.view')) {
                    continue;
                }
                $items[$key] = ['module' => $key, 'label' => $menu['label'], 'description' => $menu['description'], 'group' => config('workspaces.modules.'.$key.'.group')];
            }
        }
        foreach (['notifications', 'profile', 'security', 'preferences', 'tasks', 'calendar', 'messages', 'support'] as $key) {
            if (! isset($items[$key]) && $this->scope($user, $key.'.view')) {
                $items[$key] = ['module' => $key, 'label' => config('workspaces.modules.'.$key.'.label'), 'group' => config('workspaces.modules.'.$key.'.group')];
            }
        }
        if ($this->scope($user, 'inquiries.view')) {
            $items['inquiries'] = ['module' => 'inquiries', 'label' => 'Public inquiries', 'group' => 'Overview'];
        }

        if ($user->isPlatformAdmin()) {
            $items['system_settings'] = ['module' => 'system_settings', 'label' => 'System settings', 'group' => 'Administration'];
        }

        return collect(config('workspaces.groups'))->mapWithKeys(fn ($group) => [$group => collect($items)->where('group', $group)->values()->all()])->filter()->all();
    }

    public function url(string $module): string
    {
        return match ($module) {
            'system_settings' => route('system.settings'),
            'browse' => route('properties.index', request()->user()?->currentMembership()?->roles->contains('name', 'tenant') && ! request()->user()?->currentMembership()?->roles->contains('name', 'buyer') ? ['purpose' => 'rent'] : []), 'overview' => route('dashboard'),'notifications' => route('notifications.index'),'users' => request()->user()?->hasPermission('users.lookup') && ! request()->user()?->hasPermission('users.manage') ? route('accounts.lookup') : route('users.index'),'payments' => route('payments.index'),'followups' => route('followups.index'),'integrations' => route('integrations.index'),'organisations' => route('organisations.index'),'branches' => route('branches.index'),'settings' => route('workspaces.settings'),'profile' => route('account.profile'),'security' => route('account.security'),'preferences' => route('account.preferences'),'audit' => route('audit.index'),'inquiries' => route('inquiries.index'),default => route('modules.show', $module)
        };
    }
}

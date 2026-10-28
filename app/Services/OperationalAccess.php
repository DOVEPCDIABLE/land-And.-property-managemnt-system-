<?php

namespace App\Services;

use App\Models\OperationalModel;
use App\Models\ServiceListing;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

class OperationalAccess
{
    public function definition(string $entity): array
    {
        $definition = config('operations.'.$entity);
        abort_unless(is_array($definition), 404);

        if ($entity === 'projects' && auth()->user() && ! auth()->user()->hasPermission('construction.view') && auth()->user()->hasPermission('developments.view')) {
            $definition['module'] = 'developments';
        }

        return $definition;
    }

    public function query(string $entity, User $user, string $action = 'view'): Builder
    {
        $d = $this->definition($entity);

        return app(WorkspaceAccess::class)->records($d['model']::query(), $user, $d['module'].'.'.$action);
    }

    public function record(string $entity, int $id, User $user, string $action = 'view'): OperationalModel
    {
        return $this->query($entity, $user, $action)->findOrFail($id);
    }

    public function choices(string $entity, User $actor): array
    {
        $definition = $this->definition($entity);
        $choices = [];
        foreach ($definition['fields'] as $key => $field) {
            if ($field['type'] === 'member') {
                $choices[$key] = $actor->currentWorkspace()->memberships()->where('is_active', true)->with('user')->get()->mapWithKeys(fn ($member) => [$member->user_id => $member->user->name])->all();
            } elseif ($field['type'] === 'reference') {
                $model = $this->definition($field['entity'])['model'];
                $choices[$key] = app(WorkspaceAccess::class)->records($model::query(), $actor, $definition['module'].'.view')->orderBy('name')->limit(1000)->pluck('name', 'id')->all();
            } elseif ($field['type'] === 'organisation') {
                $choices[$key] = Workspace::where('kind', 'organisation')->where('is_active', true)->whereHas('agency', fn ($q) => $q->where('status', 'active'))->whereKeyNot($actor->currentWorkspace()->id)->pluck('name', 'id')->all();
            } elseif ($field['type'] === 'public_service') {
                $choices[$key] = ServiceListing::where('status', 'published')->whereHas('workspace', fn ($q) => $q->where('is_active', true))->pluck('name', 'id')->all();
            }
        }

        return $choices;
    }
}

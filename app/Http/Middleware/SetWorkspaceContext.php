<?php

namespace App\Http\Middleware;

use App\Services\WorkspaceAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetWorkspaceContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user) {
            $user->unsetRelation('workspaceMembership');
            abort_unless(app(WorkspaceAccess::class)->membership($user), 403, 'No active workspace is available.');
        }

        return $next($request);
    }
}

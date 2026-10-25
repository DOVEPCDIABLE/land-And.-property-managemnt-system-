<?php

use App\Http\Middleware\ApplyPlatformSettings;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequireSuperAdministrator;
use App\Http\Middleware\SetWorkspaceContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trimStrings(except: ['thousands_separator']);
        $middleware->web(append: [ApplyPlatformSettings::class]);
        $middleware->alias(['super_admin' => RequireSuperAdministrator::class, 'workspace' => SetWorkspaceContext::class, 'active' => EnsureActiveUser::class, 'permission' => RequirePermission::class]);
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

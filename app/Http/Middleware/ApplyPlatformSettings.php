<?php

namespace App\Http\Middleware;

use App\Services\CurrencyService;
use App\Services\PlatformSettings;
use App\Services\SiteMedia;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyPlatformSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        app(SiteMedia::class)->refresh();
        app(PlatformSettings::class)->refresh();
        app(CurrencyService::class)->refresh();
        app(PlatformSettings::class)->apply();

        return $next($request);
    }
}

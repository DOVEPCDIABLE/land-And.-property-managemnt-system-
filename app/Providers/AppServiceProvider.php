<?php

namespace App\Providers;

use App\Mail\ZeptoMailTransport;
use App\Services\CurrencyService;
use App\Services\PlatformSettings;
use App\Services\PropertyCatalog;
use App\Services\SiteMedia;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Address;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(SiteMedia::class, fn () => new SiteMedia);
        $this->app->scoped(PlatformSettings::class, fn () => new PlatformSettings);
        $this->app->scoped(CurrencyService::class, fn () => new CurrencyService);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view): void {
            $view->with('site', app(PlatformSettings::class))->with('money', app(CurrencyService::class));
        });
        View::composer('marketplace.layout', function ($view): void {
            $view->with('publicProperties', app(PropertyCatalog::class)->properties(is_string(request('currency')) ? request('currency') : null));
        });
        Event::listen(MessageSending::class, function ($event): void {
            $from = $event->message->getFrom();
            if ($from && $from[0]->getAddress() === config('mail.from.address')) {
                $event->message->from(new Address($from[0]->getAddress(), app(PlatformSettings::class)->get('brand_name')));
            }
        });
        Mail::extend('zeptomail', fn () => new ZeptoMailTransport);
    }
}

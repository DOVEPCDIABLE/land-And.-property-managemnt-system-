<?php

namespace App\Providers;

use App\Mail\ZeptoMailTransport;
use App\Services\CurrencyService;
use App\Services\PlatformSettings;
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
        Event::listen(MessageSending::class, function ($event): void {
            $from = $event->message->getFrom();
            if ($from && $from[0]->getAddress() === config('mail.from.address')) {
                $event->message->from(new Address($from[0]->getAddress(), app(PlatformSettings::class)->get('brand_name')));
            }
        });
        Mail::extend('zeptomail', fn () => new ZeptoMailTransport);
    }
}

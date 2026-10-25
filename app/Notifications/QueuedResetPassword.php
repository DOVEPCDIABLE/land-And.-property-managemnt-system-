<?php

namespace App\Notifications;

use App\Services\PlatformSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class QueuedResetPassword extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(string $token)
    {
        parent::__construct($token);
        $this->onQueue('notifications')->afterCommit();
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable->is_active;
    }

    public function toMail($notifiable): MailMessage
    {
        app(PlatformSettings::class)->refresh();
        app(PlatformSettings::class)->apply();

        return parent::toMail($notifiable);
    }
}

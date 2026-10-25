<?php

namespace App\Notifications;

use App\Services\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public string $subject, public string $message, public array $channels = ['database'], public ?int $workspaceId = null)
    {
        $this->onQueue('notifications')->afterCommit();
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function via(object $notifiable): array
    {
        return $notifiable->is_active ? array_values(array_filter($this->channels, fn ($channel) => $notifiable->notification_preferences[$channel] ?? true)) : [];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable->is_active && ($notifiable->notification_preferences[$channel] ?? true) && (! $this->workspaceId || $notifiable->memberships()->where('workspace_id', $this->workspaceId)->where('is_active', true)->whereHas('workspace', fn ($q) => $q->where('is_active', true)->where(fn ($w) => $w->where('kind', '!=', 'organisation')->orWhereHas('agency', fn ($a) => $a->where('status', 'active'))))->exists());
    }

    public function toMail(object $notifiable): MailMessage
    {
        app(PlatformSettings::class)->refresh();
        app(PlatformSettings::class)->apply();

        return (new MailMessage)->subject($this->subject)->greeting('Hello '.$notifiable->name)->line($this->message)->action('Open your workspace', route('dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return ['subject' => $this->subject, 'message' => $this->message, 'workspace_id' => $this->workspaceId];
    }
}

<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\PlatformSettings;
use App\Services\WorkspaceAccess;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SendAppointmentReminder implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $appointmentId, public int $version)
    {
        $this->onQueue('notifications')->afterCommit();
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('appointment:'.$this->appointmentId))->releaseAfter(30)->expireAfter(120)];
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(): void
    {
        app(PlatformSettings::class)->refresh();
        app(PlatformSettings::class)->apply();
        $item = Appointment::find($this->appointmentId);
        if (! $item || $item->status !== 'scheduled' || $item->reminder_version !== $this->version || $item->reminded_at || $item->starts_at->isPast()) {
            return;
        }
        $user = User::find($item->assigned_to);
        $membership = Membership::with(['workspace.agency', 'roles.permissions'])->where('workspace_id', $item->workspace_id)->where('user_id', $item->assigned_to)->where('is_active', true)->first();
        if (! $user || ! $membership) {
            return;
        }
        $user->setRelation('workspaceMembership', $membership);
        if (! app(WorkspaceAccess::class)->records(Appointment::query(), $user, 'calendar.view')->whereKey($item->id)->exists()) {
            return;
        }
        $user->notifyNow(new SystemNotification('Appointment reminder: '.$item->name, 'Starts '.$item->starts_at->timezone(config('ecosystem.display_timezone'))->format('d M Y H:i'), ['database', 'mail'], $item->workspace_id));
        $item->update(['reminded_at' => now()]);
    }
}

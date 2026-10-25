<?php

namespace App\Jobs;

use App\Models\FollowUp;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\WorkspaceAccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class SendFollowUp implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $followUpId)
    {
        $this->onQueue('notifications');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('followup:'.$this->followUpId))->releaseAfter(30)->expireAfter(120)];
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(): void
    {
        $item = FollowUp::findOrFail($this->followUpId);
        if (! in_array($item->status, ['queued', 'failed'], true)) {
            return;
        }
        if (! $item->user?->is_active) {
            $item->update(['status' => 'cancelled']);

            return;
        }
        if ($item->workspace_id) {
            $membership = Membership::with(['workspace.agency', 'roles.permissions'])->where('workspace_id', $item->workspace_id)->where('user_id', $item->created_by)->where('is_active', true)->first();
            $recipientMember = Membership::where('workspace_id', $item->workspace_id)->where('user_id', $item->user_id)->where('is_active', true)->exists();
            $creator = User::find($item->created_by);
            if ($creator) {
                $creator->setRelation('workspaceMembership', $membership);
            }
            if (! $membership || ! $recipientMember || ! $creator?->hasPermission('followups.create') || ! app(WorkspaceAccess::class)->records(FollowUp::query(), $creator, 'followups.view')->whereKey($item->id)->exists()) {
                $item->update(['status' => 'cancelled']);

                return;
            }
        }
        $item->user->notifyNow(new SystemNotification($item->subject, $item->message, $item->channels, $item->workspace_id));
        $item->update(['status' => 'sent', 'sent_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        FollowUp::whereKey($this->followUpId)->where('status', 'queued')->update(['status' => 'failed']);
    }
}

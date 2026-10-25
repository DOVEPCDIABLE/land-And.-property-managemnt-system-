<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Models\Workspace;
use App\Notifications\SystemNotification;
use App\Services\Payments\GatewayManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProcessWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $eventId)
    {
        $this->onQueue('payments');
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(GatewayManager $gateways): void
    {
        $event = WebhookEvent::findOrFail($this->eventId);
        if ($event->status === 'processed' || $event->status === 'ignored') {
            return;
        }
        $payload = $event->payload;
        $data = $payload['data'];
        $eligible = match ($event->provider) {
            'paystack' => ($payload['event'] ?? '') === 'charge.success',
            'flutterwave' => ($payload['event'] ?? '') === 'charge.completed' && ($data['status'] ?? '') === 'successful',
            'eversend' => ($payload['type'] ?? '') === 'transaction.status' && in_array($data['type'] ?? '', ['collection', 'deposit'], true) && ($data['status'] ?? '') === 'successful',
        };
        if (! $eligible) {
            $event->update(['status' => 'ignored', 'processed_at' => now()]);

            return;
        }
        $lookup = match ($event->provider) {
            'paystack' => (string) ($data['reference'] ?? ''),'flutterwave' => (string) ($data['id'] ?? ''),'eversend' => (string) ($data['transactionId'] ?? '')
        };
        if ($lookup === '') {
            $event->update(['status' => 'ignored', 'processed_at' => now()]);

            return;
        }
        $verified = $gateways->driver($event->provider)->verify($lookup);
        if (! $verified->successful || $verified->transactionId === '' || $verified->reference === '') {
            throw new RuntimeException('Provider verification is incomplete or unsuccessful.');
        }
        DB::transaction(function () use ($event, $verified): void {
            $field = $event->provider === 'eversend' ? 'external_reference' : 'reference';
            $payment = Payment::where('provider', $event->provider)->where($field, $verified->reference)->lockForUpdate()->first();
            if (! $payment) {
                throw new RuntimeException('Verified payment has no matching local reference; manual reconciliation required.');
            }
            if ($payment->amount_minor !== $verified->amountMinor || $payment->currency !== $verified->currency) {
                throw new RuntimeException('Verified payment amount or currency does not match.');
            }
            if ($payment->status !== 'paid') {
                $payment->update(['status' => 'paid', 'provider_transaction_id' => $verified->transactionId, 'paid_at' => now()]);
                AuditLog::create(['workspace_id' => $payment->workspace_id, 'agency_id' => $payment->agency_id, 'action' => 'payment.verified', 'subject' => $payment->reference]);
                $payment->user->notify(new SystemNotification('Payment confirmed', 'Your payment reference '.$payment->reference.' has been verified.', ['database', 'mail'], Workspace::find($payment->workspace_id)?->kind === 'platform' ? null : $payment->workspace_id));
            } elseif ($payment->provider_transaction_id !== $verified->transactionId) {
                throw new RuntimeException('A different transaction already settled this reference.');
            }
            $event->update(['status' => 'processed', 'processed_at' => now()]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        WebhookEvent::whereKey($this->eventId)->where('status', 'pending')->update(['status' => 'failed']);
    }
}

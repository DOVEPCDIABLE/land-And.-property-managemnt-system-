<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWebhook;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--reference= : Re-query a Paystack reference}';

    protected $description = 'Queue verification of pending Paystack payments; replay stored gateway events using queue:retry';

    public function handle(): int
    {
        $query = Payment::where('provider', 'paystack')->whereIn('status', ['pending', 'initializing', 'initialization_failed'])->where('created_at', '<=', now()->subMinutes(5));
        if ($reference = $this->option('reference')) {
            $query->where('reference', $reference);
        }
        $query->chunkById(100, function ($payments): void {
            foreach ($payments as $payment) {
                $event = WebhookEvent::create(['provider' => 'paystack', 'digest' => hash('sha256', $payment->reference.':'.Str::uuid()), 'payload' => ['event' => 'charge.success', 'data' => ['reference' => $payment->reference]]]);
                ProcessWebhook::dispatch($event->id);
            }
        });

        return self::SUCCESS;
    }
}

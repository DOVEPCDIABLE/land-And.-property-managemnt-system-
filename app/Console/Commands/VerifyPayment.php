<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWebhook;
use App\Models\WebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class VerifyPayment extends Command
{
    protected $signature = 'payments:verify {provider} {transaction : Paystack reference, Flutterwave ID or Eversend transactionId}';

    protected $description = 'Queue server-side verification when a webhook has been missed';

    public function handle(): int
    {
        $provider = $this->argument('provider');
        $transaction = $this->argument('transaction');
        $payload = match ($provider) {
            'paystack' => ['event' => 'charge.success', 'data' => ['reference' => $transaction]],
            'flutterwave' => ['event' => 'charge.completed', 'data' => ['id' => $transaction, 'status' => 'successful']],
            'eversend' => ['type' => 'transaction.status', 'data' => ['transactionId' => $transaction, 'status' => 'successful', 'type' => 'collection']],
            default => null,
        };
        if (! $payload) {
            $this->error('Unsupported provider.');

            return self::FAILURE;
        }
        $event = WebhookEvent::create(['provider' => $provider, 'digest' => hash('sha256', (string) Str::uuid()), 'payload' => $payload]);
        ProcessWebhook::dispatch($event->id);
        $this->info('Verification queued. No payment is confirmed until provider data matches.');

        return self::SUCCESS;
    }
}

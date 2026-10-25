<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhook;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    public function __invoke(Request $request, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, ['paystack', 'flutterwave', 'eversend'], true), 404);
        $secret = config('payments.'.$provider.'.'.($provider === 'paystack' ? 'secret' : 'webhook_secret'));
        abort_unless(is_string($secret) && $secret !== '', 503, 'Webhook credentials are not configured.');
        $body = $request->getContent();
        abort_if(strlen($body) > 262144, 413);
        [$header,$expected] = match ($provider) {
            'paystack' => ['x-paystack-signature', hash_hmac('sha512', $body, $secret)],
            'eversend' => ['x-eversend-signature', hash_hmac('sha512', $body, $secret)],
            'flutterwave' => ['verif-hash', $secret],
        };
        abort_unless(hash_equals($expected, (string) $request->header($header)), 401, 'Invalid webhook signature.');
        $payload = json_decode($body, true);
        abort_unless(is_array($payload) && is_array($payload['data'] ?? null), 422, 'Invalid webhook body.');
        DB::transaction(function () use ($provider, $body, $payload): void {
            $event = WebhookEvent::firstOrCreate(['provider' => $provider, 'digest' => hash('sha256', $body)], ['payload' => $payload]);
            if ($event->wasRecentlyCreated) {
                ProcessWebhook::dispatch($event->id)->afterCommit();
            }
        });

        return response()->json(['received' => true]);
    }
}

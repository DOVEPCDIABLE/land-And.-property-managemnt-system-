<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\CurrencyService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackGateway implements PaymentGateway
{
    private function client(): PendingRequest
    {
        $secret = config('payments.paystack.secret');
        if (! $secret) {
            throw new RuntimeException('Paystack is not configured.');
        }

        return Http::baseUrl(config('payments.paystack.url'))->withToken($secret)->acceptJson()->connectTimeout(5)->timeout(20);
    }

    public function initialize(Payment $payment): array
    {
        if (! in_array($payment->currency, app(CurrencyService::class)->providerCurrencies('paystack'), true)) {
            throw new RuntimeException('Currency is not enabled for this gateway.');
        }
        $response = $this->client()->post('/transaction/initialize', ['email' => $payment->user->email, 'amount' => $payment->currency === 'XOF' ? $payment->amount_minor * 100 : $payment->amount_minor, 'currency' => $payment->currency, 'reference' => $payment->reference, 'callback_url' => route('payments.return')])->throw()->json();
        if (empty($response['status']) || empty($response['data']['authorization_url'])) {
            throw new RuntimeException('Paystack did not return a checkout URL.');
        }

        return ['checkout_url' => $response['data']['authorization_url'], 'external_reference' => $payment->reference];
    }

    public function verify(string $transactionId): VerifiedPayment
    {
        $result = $this->client()->get('/transaction/verify/'.rawurlencode($transactionId))->throw()->json();
        $data = $result['data'] ?? [];

        $currency = (string) ($data['currency'] ?? 'NGN');
        $raw = Money::minor((string) ($data['amount'] ?? 0), $currency, 0);
        if ($currency === 'XOF' && $raw % 100 !== 0) {
            throw new RuntimeException('Paystack returned a fractional XOF amount.');
        }
        $minor = $currency === 'XOF' ? intdiv($raw, 100) : $raw;

        return new VerifiedPayment(! empty($result['status']) && ($data['status'] ?? '') === 'success', (string) ($data['reference'] ?? ''), (string) ($data['id'] ?? ''), $minor, (string) ($data['currency'] ?? ''));
    }
}

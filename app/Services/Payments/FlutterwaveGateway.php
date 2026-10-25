<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\CurrencyService;
use App\Services\PlatformSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FlutterwaveGateway implements PaymentGateway
{
    private function client(): PendingRequest
    {
        $secret = config('payments.flutterwave.secret');
        if (! $secret) {
            throw new RuntimeException('Flutterwave is not configured.');
        }

        return Http::baseUrl(config('payments.flutterwave.url'))->withToken($secret)->acceptJson()->connectTimeout(5)->timeout(20);
    }

    public function initialize(Payment $payment): array
    {
        if (! in_array($payment->currency, app(CurrencyService::class)->providerCurrencies('flutterwave'), true)) {
            throw new RuntimeException('Currency is not enabled for this gateway.');
        }
        $data = $this->client()->post('/payments', ['tx_ref' => $payment->reference, 'amount' => Money::major($payment->amount_minor, $payment->currency, $payment->minor_unit), 'currency' => $payment->currency, 'redirect_url' => route('payments.return'), 'customer' => ['email' => $payment->user->email, 'name' => $payment->user->name], 'customizations' => ['title' => app(PlatformSettings::class)->get('brand_name')]])->throw()->json();
        if (($data['status'] ?? '') !== 'success' || empty($data['data']['link'])) {
            throw new RuntimeException('Flutterwave did not return a checkout URL.');
        }

        return ['checkout_url' => $data['data']['link'], 'external_reference' => $payment->reference];
    }

    public function verify(string $transactionId): VerifiedPayment
    {
        $result = $this->client()->get('/transactions/'.rawurlencode($transactionId).'/verify')->throw()->json();
        $data = $result['data'] ?? [];

        return new VerifiedPayment(($result['status'] ?? '') === 'success' && ($data['status'] ?? '') === 'successful', (string) ($data['tx_ref'] ?? ''), (string) ($data['id'] ?? ''), Money::minor($data['amount'] ?? 0, (string) ($data['currency'] ?? 'NGN')), (string) ($data['currency'] ?? ''));
    }
}

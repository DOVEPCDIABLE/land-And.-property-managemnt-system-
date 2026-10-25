<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\CurrencyService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EversendGateway implements PaymentGateway
{
    private function client(): PendingRequest
    {
        $id = config('payments.eversend.client_id');
        $secret = config('payments.eversend.client_secret');
        if (! $id || ! $secret) {
            throw new RuntimeException('Eversend is not configured.');
        }
        $response = Http::baseUrl(config('payments.eversend.url'))->acceptJson()->withHeaders(['clientId' => $id, 'clientSecret' => $secret])->connectTimeout(5)->timeout(20)->get('/auth/token')->throw();
        $token = $response->json('token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Eversend did not return an access token.');
        }

        return Http::baseUrl(config('payments.eversend.url'))->withToken($token)->acceptJson()->connectTimeout(5)->timeout(20);
    }

    public function initialize(Payment $payment): array
    {
        if (! in_array($payment->currency, app(CurrencyService::class)->providerCurrencies('eversend'), true)) {
            throw new RuntimeException('Currency is not enabled for this gateway.');
        }
        $response = $this->client()->post('/collections/tempVirtualAccounts', ['currency' => 'NGN', 'amount' => Money::major($payment->amount_minor, $payment->currency, $payment->minor_unit)])->throw();
        $account = $response->json('data.tempBankAccount');
        if (! $response->json('success') || empty($account['reference']) || empty($account['accountNumber'])) {
            throw new RuntimeException('Eversend did not return a temporary virtual account.');
        }

        return ['external_reference' => $account['reference'], 'instructions' => array_intersect_key($account, array_flip(['accountNumber', 'accountName', 'bankName', 'expiresAt', 'reference']))];
    }

    public function verify(string $transactionId): VerifiedPayment
    {
        $result = $this->client()->get('/transactions/'.rawurlencode($transactionId))->throw()->json();
        $rows = $result['data']['transactions'] ?? [];
        $data = collect($rows)->first(fn (array $row): bool => (string) ($row['transactionId'] ?? '') === $transactionId) ?? [];

        return new VerifiedPayment(! empty($result['success']) && in_array($data['type'] ?? '', ['collection', 'deposit'], true) && ($data['status'] ?? '') === 'successful', (string) ($data['transactionRef'] ?? ''), (string) ($data['transactionId'] ?? ''), Money::minor($data['amount'] ?? 0, (string) ($data['currency'] ?? 'NGN')), (string) ($data['currency'] ?? ''));
    }
}

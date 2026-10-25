<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhook;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\SystemNotification;
use App\Services\Payments\GatewayManager;
use App\Services\Payments\Money;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Http::preventStrayRequests();
        config(['payments.paystack.secret' => 'test-secret', 'payments.flutterwave.secret' => 'flw-test', 'payments.flutterwave.webhook_secret' => 'flw-hash', 'payments.eversend.client_id' => 'ev-id', 'payments.eversend.client_secret' => 'ev-secret', 'payments.eversend.webhook_secret' => 'ev-hash']);
    }

    private function recordData(User $user, string $provider = 'manual'): array
    {
        return ['reference' => (string) Str::uuid(), 'user_id' => $user->id, 'provider' => $provider, 'amount' => '2500.25', 'currency' => 'NGN', 'purpose' => 'Platform subscription'];
    }

    private function paystackEvent(Payment $payment, int $amount = 250000, string $status = 'success'): WebhookEvent
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'data' => ['id' => 123, 'reference' => $payment->reference, 'status' => $status, 'amount' => $amount, 'currency' => 'NGN']])]);

        return WebhookEvent::create(['provider' => 'paystack', 'digest' => hash('sha256', (string) Str::uuid()), 'payload' => ['event' => 'charge.success', 'data' => ['reference' => $payment->reference]]]);
    }

    public function test_manual_records_do_not_call_gateways_and_repeated_submission_is_idempotent(): void
    {
        $admin = User::factory()->role('agency_admin')->create();
        $data = $this->recordData($admin);
        $this->actingAs($admin)->post('/payments', $data)->assertSessionHasNoErrors();
        $this->post('/payments', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['status' => 'recorded', 'amount_minor' => 250025, 'provider' => 'manual']);
        Http::assertNothingSent();
    }

    public function test_gateway_processing_is_disabled_by_default(): void
    {
        $admin = User::factory()->role('agency_admin')->create();
        $this->actingAs($admin)->post('/payments', $this->recordData($admin, 'paystack'))->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_gateway_checkout_initializes_using_integer_minor_units(): void
    {
        config(['payments.enabled' => true]);
        $admin = User::factory()->role('agency_admin')->create();
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/test']])]);
        $this->actingAs($admin)->post('/payments', $this->recordData($admin, 'paystack'))->assertSessionHasNoErrors();
        Http::assertSent(fn ($request): bool => $request['amount'] === 250025 && $request['currency'] === 'NGN');
        $this->assertDatabaseHas('payments', ['status' => 'pending', 'checkout_url' => 'https://checkout.paystack.com/test']);
    }

    public function test_cross_agency_payment_recording_is_rejected(): void
    {
        $admin = User::factory()->role('agency_admin')->create();
        $other = User::factory()->create();
        $this->actingAs($admin)->post('/payments', $this->recordData($other))->assertNotFound();
    }

    public function test_payment_list_is_agency_scoped(): void
    {
        $admin = User::factory()->role('agency_admin')->create();
        $other = Payment::factory()->create();
        $this->actingAs($admin)->get('/payments')->assertOk()->assertDontSee($other->reference);
    }

    public function test_webhooks_require_valid_signatures(): void
    {
        $this->postJson('/webhooks/paystack', ['event' => 'charge.success', 'data' => ['reference' => 'fake']])->assertUnauthorized();
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_signed_duplicate_webhooks_create_one_event_and_one_job(): void
    {
        Queue::fake();
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'test-ref']]);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'test-secret')];
        for ($i = 0; $i < 2; $i++) {
            $this->call('POST', '/webhooks/paystack', [], [], [], $headers, $body)->assertOk();
        }
        $this->assertDatabaseCount('webhook_events', 1);
        Queue::assertPushed(ProcessWebhook::class, 1);
    }

    public function test_paystack_verification_marks_payment_paid_once(): void
    {
        Notification::fake();
        $payment = Payment::factory()->create();
        $event = $this->paystackEvent($payment);
        (new ProcessWebhook($event->id))->handle(app(GatewayManager::class));
        (new ProcessWebhook($event->id))->handle(app(GatewayManager::class));
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('processed', $event->fresh()->status);
        Notification::assertSentToTimes($payment->user, SystemNotification::class, 1);
    }

    public function test_verification_rejects_amount_mismatches(): void
    {
        $payment = Payment::factory()->create();
        $event = $this->paystackEvent($payment, 10);
        try {
            (new ProcessWebhook($event->id))->handle(app(GatewayManager::class));
            $this->fail('Expected amount mismatch.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('amount or currency', $exception->getMessage());
        }
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_successful_webhook_cannot_override_unsuccessful_provider_response(): void
    {
        $payment = Payment::factory()->create();
        $event = $this->paystackEvent($payment, 250000, 'failed');
        $this->expectException(\RuntimeException::class);
        (new ProcessWebhook($event->id))->handle(app(GatewayManager::class));
    }

    public function test_flutterwave_checkout_and_verification(): void
    {
        $payment = Payment::factory()->create(['provider' => 'flutterwave']);
        Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/test']]), 'api.flutterwave.com/v3/transactions/42/verify' => Http::response(['status' => 'success', 'data' => ['id' => 42, 'tx_ref' => $payment->reference, 'status' => 'successful', 'amount' => '2500.00', 'currency' => 'NGN']])]);
        $gateway = app(GatewayManager::class)->driver('flutterwave');
        $this->assertSame('https://checkout.flutterwave.com/test', $gateway->initialize($payment)['checkout_url']);
        $verified = $gateway->verify('42');
        $this->assertTrue($verified->successful);
        $this->assertSame(250000, $verified->amountMinor);
    }

    public function test_flutterwave_webhook_uses_the_configured_v3_secret_hash(): void
    {
        Queue::fake();
        $this->postJson('/webhooks/flutterwave', ['event' => 'charge.completed', 'data' => ['id' => 42]], ['verif-hash' => 'wrong'])->assertUnauthorized();
        $this->postJson('/webhooks/flutterwave', ['event' => 'charge.completed', 'data' => ['id' => 42]], ['verif-hash' => 'flw-hash'])->assertOk();
        Queue::assertPushed(ProcessWebhook::class, 1);
    }

    public function test_eversend_initialization_and_verified_reference_matching(): void
    {
        Notification::fake();
        $payment = Payment::factory()->create(['provider' => 'eversend']);
        Http::fake(['api.eversend.co/v1/auth/token' => Http::response(['token' => 'ev-token']), 'api.eversend.co/v1/collections/tempVirtualAccounts' => Http::response(['success' => true, 'data' => ['tempBankAccount' => ['reference' => 'TBA123', 'accountNumber' => '1234567890', 'bankName' => 'Test Bank']]]), 'api.eversend.co/v1/transactions/EV123' => Http::response(['success' => true, 'data' => ['transactions' => [['transactionId' => 'EV123', 'transactionRef' => 'TBA123', 'type' => 'collection', 'status' => 'successful', 'amount' => '2500', 'currency' => 'NGN']]]])]);
        $payment->update(app(GatewayManager::class)->driver('eversend')->initialize($payment));
        $event = WebhookEvent::create(['provider' => 'eversend', 'digest' => hash('sha256', 'ev-test'), 'payload' => ['type' => 'transaction.status', 'data' => ['type' => 'collection', 'status' => 'successful', 'transactionId' => 'EV123']]]);
        (new ProcessWebhook($event->id))->handle(app(GatewayManager::class));
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('EV123', $payment->fresh()->provider_transaction_id);
    }

    public function test_eversend_webhook_requires_hmac_signature(): void
    {
        Queue::fake();
        $body = json_encode(['type' => 'transaction.status', 'data' => ['transactionId' => 'EV123']]);
        $this->call('POST', '/webhooks/eversend', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_EVERSEND_SIGNATURE' => hash_hmac('sha512', $body, 'ev-hash')], $body)->assertOk();
        Queue::assertPushed(ProcessWebhook::class, 1);
    }

    public function test_money_conversion_avoids_float_rounding(): void
    {
        $this->assertSame(29, Money::minor('0.29'));
        $this->assertSame(250025, Money::minor('2500.25'));
        $this->assertSame('2500.25', Money::major(250025));
    }

    public function test_money_rejects_extra_decimal_places(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::minor('1.001');
    }

    public function test_different_events_for_the_same_transaction_confirm_once(): void
    {
        Notification::fake();
        $payment = Payment::factory()->create();
        $first = $this->paystackEvent($payment);
        $second = WebhookEvent::create(['provider' => 'paystack', 'digest' => hash('sha256', 'second-delivery'), 'payload' => $first->payload]);
        (new ProcessWebhook($first->id))->handle(app(GatewayManager::class));
        (new ProcessWebhook($second->id))->handle(app(GatewayManager::class));
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('processed', $second->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 1);
        Notification::assertSentToTimes($payment->user, SystemNotification::class, 1);
    }

    public function test_currency_mismatch_cannot_settle_payment(): void
    {
        $payment = Payment::factory()->create();
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'data' => ['id' => 123, 'reference' => $payment->reference, 'status' => 'success', 'amount' => $payment->amount_minor, 'currency' => 'USD']])]);
        $event = WebhookEvent::create(['provider' => 'paystack', 'digest' => hash('sha256', 'currency-mismatch'), 'payload' => ['event' => 'charge.success', 'data' => ['reference' => $payment->reference]]]);
        try {
            (new ProcessWebhook($event->id))->handle(app(GatewayManager::class));
            $this->fail('Currency mismatch should be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('amount or currency', $exception->getMessage());
        }
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_a_reference_cannot_be_reused_for_different_payment_details(): void
    {
        $admin = User::factory()->role('agency_admin')->create();
        $data = $this->recordData($admin);
        $this->actingAs($admin)->post('/payments', $data)->assertSessionHasNoErrors();
        $data['amount'] = '999.00';
        $this->post('/payments', $data)->assertConflict();
        $this->assertDatabaseCount('payments', 1);
    }
}

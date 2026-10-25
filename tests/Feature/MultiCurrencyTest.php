<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\Payments\GatewayManager;
use App\Services\Payments\Money;
use App\Services\PlatformSettings;
use App\Services\PricingCalculator;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MultiCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    private function settings(array $data): void
    {
        $row = PlatformSetting::find(1);
        $row->update(['values' => [...($row->values ?? []), ...$data]]);
        app(PlatformSettings::class)->refresh();
        app(CurrencyService::class)->refresh();
    }

    private function record(User $user, string $currency, string $amount, string $provider = 'manual'): array
    {
        return ['reference' => (string) Str::uuid(), 'user_id' => $user->id, 'provider' => $provider, 'currency' => $currency, 'amount' => $amount, 'purpose' => 'Currency test'];
    }

    public function test_iso_catalogue_and_zero_two_three_and_four_decimal_units_are_supported_exactly(): void
    {
        $this->assertDatabaseCount('currencies', 165);
        $this->assertSame(100, Money::minor('100', 'JPY'));
        $this->assertSame(12345, Money::minor('123.45', 'USD'));
        $this->assertSame(12345, Money::minor('12.345', 'KWD'));
        $this->assertSame(12345, Money::minor('1.2345', 'CLF'));
        $this->assertSame('12.345', Money::major(12345, 'KWD'));
    }

    public function test_manual_payments_store_currency_specific_precision_and_allow_subunit_amounts(): void
    {
        Http::preventStrayRequests();
        $admin = User::factory()->role('agency_admin')->create();
        $this->actingAs($admin);
        foreach (['JPY' => '100', 'USD' => '1.25', 'KWD' => '0.001', 'CLF' => '1.2345'] as $code => $amount) {
            $this->post('/payments', $this->record($admin, $code, $amount))->assertSessionHasNoErrors();
            $payment = Payment::where('currency', $code)->sole();
            $this->assertSame(app(CurrencyService::class)->minorUnit($code), $payment->minor_unit);
            $this->assertSame(Money::minor($amount, $code), $payment->amount_minor);
        }
        Http::assertNothingSent();
    }

    public function test_unknown_disabled_and_excess_precision_amounts_are_rejected(): void
    {
        $admin = User::factory()->role('agency_admin')->create();
        Currency::whereKey('USD')->update(['is_enabled' => false]);
        $this->actingAs($admin);
        $this->post('/payments', $this->record($admin, 'USD', '10'))->assertSessionHasErrors('currency');
        $this->post('/payments', $this->record($admin, 'ZZZ', '10'))->assertSessionHasErrors('currency');
        $this->post('/payments', $this->record($admin, 'JPY', '1.5'))->assertSessionHasErrors('amount');
        $this->post('/payments', $this->record($admin, 'KWD', '1.2345'))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_malformed_currency_and_provider_inputs_are_validation_errors_not_server_errors(): void
    {
        $admin = User::factory()->role('agency_admin')->create();
        $this->actingAs($admin)->post('/payments', [...$this->record($admin, 'USD', '1'), 'currency' => ['USD']])->assertSessionHasErrors();
        $this->post('/payments', [...$this->record($admin, 'USD', '1'), 'provider' => ['paystack']])->assertSessionHasErrors('provider');
    }

    public function test_payment_limit_and_formatting_read_admin_settings_without_float_rounding(): void
    {
        $this->settings(['max_payment_amount' => '10', 'currency_display' => 'code', 'decimal_separator' => ',', 'thousands_separator' => '.']);
        $admin = User::factory()->role('agency_admin')->create();
        $this->actingAs($admin)->post('/payments', $this->record($admin, 'USD', '10.01'))->assertSessionHasErrors('amount');
        $this->assertSame('USD 1.234,56', app(CurrencyService::class)->formatMinor(123456, 'USD'));
    }

    public function test_listing_conversion_uses_an_explicit_rate_and_preserves_original_price(): void
    {
        $this->settings(['default_currency' => 'USD', 'currency_display' => 'code']);
        $source = config('marketplace.properties')[0];
        $money = app(CurrencyService::class);
        $without = $money->listing($source);
        $this->assertSame('NGN', $without['display_currency']);
        $this->assertFalse($without['conversion_available']);
        $admin = User::factory()->role('platform_admin')->create();
        ExchangeRate::create(['base_currency' => 'NGN', 'quote_currency' => 'USD', 'rate' => '0.001', 'updated_by' => $admin->id]);
        $converted = $money->listing($source);
        $this->assertSame('USD', $converted['display_currency']);
        $this->assertSame('285000.00', $converted['display_amount']);
        $this->assertSame(285000000, $converted['price']);
        $this->get('/properties?currency=USD&max=300000')->assertOk()->assertViewHas('properties', fn ($p) => $p->contains('slug', 'the-palm-residence'));
    }

    public function test_pricing_calculator_uses_configured_percentages_flat_fees_and_tax_on_fees(): void
    {
        $this->settings(['commission_percent' => '5', 'service_fee_percent' => '2', 'tax_percent' => '10', 'flat_fee' => '100', 'pricing_currency' => 'USD']);
        $result = app(PricingCalculator::class)->calculate('1000', 'USD');
        $this->assertSame(5000, $result['commission']);
        $this->assertSame(2000, $result['service_fee']);
        $this->assertSame(10000, $result['flat_fee']);
        $this->assertSame(1700, $result['tax']);
        $this->assertSame(118700, $result['total_amount']);
        $this->post('/pricing-calculator', ['amount' => '1000', 'currency' => 'USD'])->assertOk()->assertSee('Estimated total including price');
    }

    public function test_zero_fees_work_for_zero_decimal_currencies_without_exchange_rates(): void
    {
        $this->settings(['flat_fee' => '0.00', 'pricing_currency' => 'NGN']);
        $result = app(PricingCalculator::class)->calculate('100', 'JPY');
        $this->assertSame(100, $result['total_amount']);
        $this->assertSame(0, $result['total_fees']);
    }

    public function test_cross_currency_flat_fees_require_a_known_rate(): void
    {
        $this->settings(['flat_fee' => '10', 'pricing_currency' => 'USD']);
        $this->post('/pricing-calculator', ['amount' => '100', 'currency' => 'JPY'])->assertSessionHasErrors('amount');
    }

    public function test_gateway_validation_rejects_unsupported_currency_before_contacting_provider(): void
    {
        Http::preventStrayRequests();
        $admin = User::factory()->role('agency_admin')->create();
        $this->settings(['payments_enabled' => true]);
        $this->actingAs($admin)->post('/payments', $this->record($admin, 'JPY', '100', 'paystack'))->assertSessionHasErrors('currency');
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_paystack_xof_wire_units_are_normalised_to_whole_francs_in_the_ledger(): void
    {
        config(['payments.paystack.secret' => 'test-key']);
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/test']]), 'api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'reference' => 'xof-ref', 'id' => 123, 'amount' => 100000, 'currency' => 'XOF']])]);
        $payment = Payment::factory()->create(['currency' => 'XOF', 'amount_minor' => 1000]);
        $gateway = app(GatewayManager::class)->driver('paystack');
        $gateway->initialize($payment);
        Http::assertSent(fn ($r) => $r['amount'] === 100000);
        $verified = $gateway->verify('xof-ref');
        $this->assertSame(1000,$verified->amountMinor);
    }
}

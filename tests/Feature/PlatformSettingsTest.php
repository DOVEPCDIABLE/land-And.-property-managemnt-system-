<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\PlatformSettings;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    private function values(array $extra = []): array
    {
        return [...config('platform'), 'payments_enabled' => false, 'revision' => app(PlatformSettings::class)->revision(), 'enabled_currencies' => array_keys(app(CurrencyService::class)->catalog()), ...$extra];
    }

    public function test_only_active_platform_super_administrators_can_read_or_change_system_settings(): void
    {
        $this->get('/system/settings')->assertRedirect('/login');
        foreach (['agency_admin', 'agent', 'support_agent', 'compliance_officer', 'auditor', 'buyer'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get('/system/settings')->assertForbidden();
            $this->put('/system/settings', [])->assertForbidden();
            $this->post('/system/currencies', [])->assertForbidden();
        }
        $this->actingAs(User::factory()->role('platform_admin')->create())->get('/system/settings')->assertOk()->assertSee('Set up your platform.');
    }

    public function test_branding_changes_are_saved_audited_and_visible_on_public_and_auth_pages(): void
    {
        $admin = User::factory()->role('platform_admin')->create();
        $this->actingAs($admin)->put('/system/settings', $this->values(['brand_name' => 'Atlas Homes', 'company_name' => 'Atlas Property Limited', 'tagline' => 'Places with possibility.', 'support_email' => 'hello@example.test', 'timezone' => 'Europe/London']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.settings_updated']);
        $this->assertSame('Atlas Homes', PlatformSetting::find(1)->values['brand_name']);
        $this->get('/')->assertOk()->assertSee('Atlas Homes')->assertSee('Atlas Property Limited')->assertSee('hello@example.test')->assertSee('PLACES WITH POSSIBILITY.');
        $this->post('/logout');
        $this->get('/login')->assertOk()->assertSee('Atlas Homes');
        $this->assertSame('Europe/London', config('ecosystem.display_timezone'));
    }

    public function test_stale_setting_revisions_do_not_overwrite_newer_changes(): void
    {
        $admin = User::factory()->role('platform_admin')->create();
        $values = $this->values();
        $this->actingAs($admin)->put('/system/settings', [...$values, 'brand_name' => 'First Name'])->assertSessionHasNoErrors();
        $this->put('/system/settings', [...$values, 'brand_name' => 'Stale Name'])->assertSessionHasErrors('revision');
        $this->assertSame('First Name', PlatformSetting::find(1)->values['brand_name']);
    }

    public function test_default_and_fee_currencies_must_remain_enabled(): void
    {
        $this->actingAs(User::factory()->role('platform_admin')->create())->put('/system/settings', $this->values(['enabled_currencies' => ['USD'], 'default_currency' => 'JPY', 'pricing_currency' => 'NGN']))->assertSessionHasErrors(['default_currency', 'pricing_currency']);
    }

    public function test_invalid_currency_arrays_and_unreadable_brand_colours_return_validation_errors(): void
    {
        $admin = User::factory()->role('platform_admin')->create();
        $this->actingAs($admin)->put('/system/settings', $this->values(['enabled_currencies' => 'USD']))->assertSessionHasErrors('enabled_currencies');
        $this->put('/system/settings', $this->values(['text_color' => '#FFFFFF', 'background_color' => '#FFFFFF']))->assertSessionHasErrors('text_color');
        $this->put('/system/settings', $this->values(['primary_color' => 'url(javascript:bad)']))->assertSessionHasErrors('primary_color');
    }

    public function test_logo_upload_is_rendered_and_svg_uploads_are_rejected(): void
    {
        $admin = User::factory()->role('platform_admin')->create();
        $image = UploadedFile::fake()->image('logo.png', 300, 100);
        $this->actingAs($admin)->put('/system/settings', $this->values(['logo_dark' => $image]))->assertSessionHasNoErrors();
        $path = PlatformSetting::find(1)->values['logo_dark'];
        try {
            $this->assertFileExists(public_path($path));
            $this->get('/')->assertOk()->assertSee(asset($path), false);
        } finally {
            if (is_file(public_path($path))) {
                unlink(public_path($path));
            }
        }
        $this->put('/system/settings', $this->values(['logo_dark' => UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')]))->assertSessionHasErrors('logo_dark');
    }

    public function test_iso_precision_is_fixed_and_used_custom_currency_precision_cannot_be_changed(): void
    {
        $admin = User::factory()->role('platform_admin')->create();
        $this->actingAs($admin)->post('/system/currencies', ['code' => 'JPY', 'name' => 'Yen', 'symbol' => '¥', 'minor_unit' => 2])->assertUnprocessable();
        $this->post('/system/currencies', ['code' => 'TOK', 'name' => 'Custom record token', 'symbol' => 'T', 'minor_unit' => 8])->assertSessionHasNoErrors();
        Payment::factory()->create(['currency' => 'TOK', 'amount_minor' => 1]);
        $this->post('/system/currencies', ['code' => 'TOK', 'name' => 'Custom record token', 'symbol' => 'T', 'minor_unit' => 2])->assertUnprocessable();
        $this->assertSame(8, Currency::find('TOK')->minor_unit);
    }

    public function test_manual_rate_is_reciprocal_and_reverse_entry_replaces_it(): void
    {
        $admin = User::factory()->role('platform_admin')->create();
        $this->actingAs($admin)->post('/system/exchange-rates', ['base_currency' => 'NGN', 'quote_currency' => 'USD', 'rate' => '0.01'])->assertSessionHasNoErrors();
        $money = app(CurrencyService::class);
        $this->assertSame('1.00', $money->convert('100', 'NGN', 'USD')['amount']);
        $this->assertSame('100.00', $money->convert('1', 'USD', 'NGN')['amount']);
        $this->post('/system/exchange-rates', ['base_currency' => 'USD', 'quote_currency' => 'NGN', 'rate' => '200'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('exchange_rates', 1);
        $this->assertSame('0.50', $money->convert('100', 'NGN', 'USD')['amount']);
    }

    public function test_disabling_the_calculator_removes_its_link_and_blocks_both_routes(): void
    {
        $admin = User::factory()->role('platform_admin')->create();
        $this->actingAs($admin)->put('/system/settings', $this->values(['pricing_enabled' => false]))->assertSessionHasNoErrors();
        $this->get('/pricing-calculator')->assertNotFound();
        $this->post('/pricing-calculator', ['amount' => '100', 'currency' => 'NGN'])->assertNotFound();
        $this->get('/')->assertDontSee('href="'.route('pricing.show').'"', false);
    }

    public function test_global_default_changes_do_not_rewrite_saved_transaction_currency_or_precision(): void
    {
        $payment = Payment::factory()->create(['currency' => 'JPY', 'amount_minor' => 1000]);
        $admin = User::factory()->role('platform_admin')->create();
        $this->actingAs($admin)->put('/system/settings', $this->values(['default_currency' => 'USD']))->assertSessionHasNoErrors();
        $this->assertSame('JPY', $payment->fresh()->currency);
        $this->assertSame(0,$payment->fresh()->minor_unit);
        $this->assertSame(1000,$payment->fresh()->amount_minor);
    }
}

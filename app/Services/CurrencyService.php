<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\Payments\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class CurrencyService
{
    private ?array $catalog = null;

    public function refresh(): void
    {
        $this->catalog = null;
    }

    public function catalog(): array
    {
        if ($this->catalog === null) {
            $this->catalog = Schema::hasTable('currencies') ? Currency::orderBy('code')->get()->keyBy('code')->map->toArray()->all() : collect(config('currencies'))->map(fn ($row, $code) => ['code' => $code, ...$row, 'is_enabled' => true, 'is_standard' => true])->all();
        }

        return $this->catalog;
    }

    public function get(string $code): array
    {
        return $this->catalog()[$code] ?? throw new InvalidArgumentException('Unknown currency code: '.$code);
    }

    public function enabled(): array
    {
        return array_filter($this->catalog(), fn ($row) => $row['is_enabled']);
    }

    public function minorUnit(string $code): int
    {
        return (int) $this->get($code)['minor_unit'];
    }

    public function displayCurrency(): string
    {
        $requested = request('currency');

        return is_string($requested) && array_key_exists($requested, $this->enabled()) ? $requested : $this->default();
    }

    public function default(): string
    {
        return app(PlatformSettings::class)->get('default_currency');
    }

    public function formatMinor(int $amount, string $code, ?int $precision = null): string
    {
        $precision ??= $this->minorUnit($code);
        $major = Money::major($amount, $code, $precision);
        [$whole,$fraction] = array_pad(explode('.', $major, 2), 2, '');
        $settings = app(PlatformSettings::class);
        $group = $settings->get('thousands_separator');
        $decimal = $settings->get('decimal_separator');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', $group, $whole);
        $formatted = $whole.($precision ? $decimal.$fraction : '');
        $symbol = $this->get($code)['symbol'];

        return $settings->get('currency_display') === 'code' || $symbol === $code ? $code.' '.$formatted : $symbol.$formatted.' · '.$code;
    }

    public function convert(string $amount, string $from, string $to): ?array
    {
        $this->get($from);
        $this->get($to);
        $number = BigDecimal::of($amount);
        if ($from === $to) {
            return ['amount' => (string) $number->toScale($this->minorUnit($to), RoundingMode::HalfUp), 'rate' => '1', 'updated_at' => null];
        }
        if (! Schema::hasTable('exchange_rates')) {
            return null;
        }
        $rate = ExchangeRate::where('base_currency', $from)->where('quote_currency', $to)->first();
        if ($rate) {
            $converted = $number->multipliedBy($rate->rate);
            $effective = $rate->rate;
        } else {
            $rate = ExchangeRate::where('base_currency', $to)->where('quote_currency', $from)->first();
            if (! $rate) {
                return null;
            }$converted = $number->dividedBy($rate->rate, $this->minorUnit($to) + 8, RoundingMode::HalfUp);
            $effective = (string) BigDecimal::of('1')->dividedBy($rate->rate, 12, RoundingMode::HalfUp);
        }

        return ['amount' => (string) $converted->toScale($this->minorUnit($to), RoundingMode::HalfUp), 'rate' => $effective, 'updated_at' => $rate->updated_at->toIso8601String()];
    }

    public function listing(array $property, ?string $target = null): array
    {
        $source = $property['currency'] ?? 'NGN';
        $target ??= $this->default();
        $conversion = $this->convert((string) $property['price'], $source, $target);
        $code = $conversion ? $target : $source;
        $amount = $conversion['amount'] ?? (string) $property['price'];
        try {
            $minor = Money::minor($amount, $code);
        } catch (InvalidArgumentException) {
            $code = $source;
            $amount = (string) $property['price'];
            $minor = Money::minor($amount, $code);
            $conversion = null;
        }

        return [...$property, 'currency' => $source, 'display_currency' => $code, 'display_amount' => $amount, 'formatted_price' => $this->formatMinor($minor, $code), 'converted' => $source !== $code, 'conversion_available' => $source === $target || $conversion !== null];
    }

    public function providerCurrencies(string $provider): array
    {
        $limits = ['paystack' => ['NGN', 'USD', 'GHS', 'ZAR', 'KES', 'XOF'], 'flutterwave' => ['NGN', 'USD', 'GBP', 'EUR', 'CAD', 'GHS', 'KES', 'ZAR', 'UGX', 'RWF', 'TZS', 'XAF', 'XOF', 'EGP', 'MWK', 'ZMW'], 'eversend' => ['NGN']];

        return array_values(array_intersect(app(PlatformSettings::class)->get($provider.'_currencies', []), $limits[$provider] ?? [], array_keys($this->enabled())));
    }
}

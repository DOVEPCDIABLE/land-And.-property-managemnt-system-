<?php

namespace App\Services;

use App\Services\Payments\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

class PricingCalculator
{
    public function calculate(string $amount, string $currency): array
    {
        $settings = app(PlatformSettings::class);
        $currencies = app(CurrencyService::class);
        $precision = $currencies->minorUnit($currency);
        $price = Money::minor($amount, $currency);
        if ($price <= 0) {
            throw new InvalidArgumentException('Enter an amount greater than zero.');
        }
        $fee = fn (string $percentage): int => BigDecimal::of((string) $price)->multipliedBy($percentage)->dividedBy('100', 0, RoundingMode::HalfUp)->toInt();
        $commission = $fee($settings->get('commission_percent'));
        $service = $fee($settings->get('service_fee_percent'));
        $flat = $settings->get('flat_fee');
        $feeCurrency = $settings->get('pricing_currency');
        $rate = null;
        if (BigDecimal::of($flat)->isGreaterThan('0')) {
            $converted = $currencies->convert($flat, $feeCurrency, $currency);
            if (! $converted) {
                throw new InvalidArgumentException('No exchange rate is configured for the flat fee. Select '.$feeCurrency.' or ask the administrator to configure a rate.');
            }
            $flat = $converted['amount'];
            $rate = $converted;
        } else {
            $flat = '0';
        }
        $flatMinor = Money::minor($flat, $currency);
        $subtotal = BigDecimal::of((string) $commission)->plus((string) $service)->plus((string) $flatMinor)->toInt();
        $tax = BigDecimal::of((string) $subtotal)->multipliedBy($settings->get('tax_percent'))->dividedBy('100', 0, RoundingMode::HalfUp)->toInt();
        $fees = BigDecimal::of((string) $subtotal)->plus((string) $tax)->toInt();
        $total = BigDecimal::of((string) $price)->plus((string) $fees)->toInt();

        return ['currency' => $currency, 'minor_unit' => $precision, 'price' => $price, 'commission' => $commission, 'service_fee' => $service, 'flat_fee' => $flatMinor, 'tax' => $tax, 'total_fees' => $fees, 'total_amount' => $total, 'assumptions' => ['commission_percent' => $settings->get('commission_percent'), 'service_fee_percent' => $settings->get('service_fee_percent'), 'tax_percent' => $settings->get('tax_percent'), 'flat_fee' => $settings->get('flat_fee'), 'flat_fee_currency' => $feeCurrency, 'exchange' => $rate], 'disclaimer' => $settings->get('pricing_disclaimer')];
    }
}

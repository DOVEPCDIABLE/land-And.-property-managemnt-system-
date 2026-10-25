<?php

namespace App\Services\Payments;

use App\Services\CurrencyService;
use Brick\Math\BigDecimal;
use InvalidArgumentException;
use Throwable;

class Money
{
    public static function minor(string|int|float $amount, string $currency = 'NGN', ?int $precision = null): int
    {
        $precision ??= app(CurrencyService::class)->minorUnit($currency);
        $value = (string) $amount;
        if (! preg_match('/^\d{1,15}(?:\.\d{1,8})?$/D', $value)) {
            throw new InvalidArgumentException('Use a positive decimal amount without scientific notation.');
        }
        $fraction = explode('.', $value, 2)[1] ?? '';
        if (strlen($fraction) > $precision) {
            throw new InvalidArgumentException($currency.' accepts at most '.$precision.' decimal places.');
        }
        try {
            return BigDecimal::of($value)->multipliedBy((string) (10 ** $precision))->toInt();
        } catch (Throwable) {
            throw new InvalidArgumentException('Amount is too large for exact minor-unit storage.');
        }
    }

    public static function major(int $amount, string $currency = 'NGN', ?int $precision = null): string
    {
        $precision ??= app(CurrencyService::class)->minorUnit($currency);

        return (string) BigDecimal::ofUnscaledValue((string) $amount, $precision);
    }
}

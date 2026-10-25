<?php

namespace App\Services\Payments;

final readonly class VerifiedPayment
{
    public function __construct(public bool $successful, public string $reference, public string $transactionId, public int $amountMinor, public string $currency) {}
}

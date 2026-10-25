<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentGateway
{
    /** @return array{checkout_url?: string, external_reference?: string, instructions?: array} */
    public function initialize(Payment $payment): array;

    public function verify(string $transactionId): VerifiedPayment;
}

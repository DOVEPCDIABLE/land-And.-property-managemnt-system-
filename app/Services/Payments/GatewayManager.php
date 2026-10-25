<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class GatewayManager
{
    public function driver(string $provider): PaymentGateway
    {
        return match ($provider) {
            'paystack' => app(PaystackGateway::class),'flutterwave' => app(FlutterwaveGateway::class),'eversend' => app(EversendGateway::class),default => throw new InvalidArgumentException('Unsupported payment provider.')
        };
    }
}

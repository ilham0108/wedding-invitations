<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Services\Payment\Gateways\ManualGateway;
use App\Services\Payment\Gateways\TripayGateway;
use App\Services\Payment\Gateways\XenditGateway;
use InvalidArgumentException;

/**
 * Resolves gateways by name. Adding Midtrans or Tripay means one class and one entry here.
 */
class PaymentManager
{
    private const GATEWAYS = [
        'xendit' => XenditGateway::class,
        'tripay' => TripayGateway::class,
        'manual' => ManualGateway::class,
    ];

    public function default(): PaymentGatewayInterface
    {
        return $this->gateway((string) config('payment.default'));
    }

    public function gateway(string $name): PaymentGatewayInterface
    {
        $class = self::GATEWAYS[$name] ?? throw new InvalidArgumentException("Unknown payment gateway [{$name}].");

        return app($class);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, self::GATEWAYS);
    }
}

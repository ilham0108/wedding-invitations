<?php

namespace App\Contracts\Payment;

/**
 * Gateways whose payment page is specific to one method (e.g. Tripay closed payment):
 * the customer picks a channel in our app before the transaction is created.
 */
interface RequiresPaymentMethod
{
    /**
     * Active channels the customer can choose from.
     *
     * @return array<int, array{code: string, name: string, group: string, icon_url: ?string}>
     */
    public function paymentMethods(): array;
}

<?php

namespace App\Support;

final class Money
{
    /**
     * "Rp 149.000" — whole rupiah, Indonesian thousands separator.
     */
    public static function format(int $amount, string $currency = 'IDR'): string
    {
        return ($currency === 'IDR' ? 'Rp ' : $currency.' ').number_format($amount, 0, ',', '.');
    }
}

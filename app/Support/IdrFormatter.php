<?php

namespace App\Support;

use InvalidArgumentException;

class IdrFormatter
{
    public static function format(string $amount): string
    {
        if (! preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $amount)) {
            throw new InvalidArgumentException('Nominal rupiah tidak valid.');
        }

        $negative = str_starts_with($amount, '-');
        $amount = $negative ? substr($amount, 1) : $amount;
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole);
        $fraction = rtrim(str_pad($fraction, 2, '0'), '0');

        return ($negative ? '-' : '').'Rp '.($grouped ?? $whole).($fraction === '' ? '' : ','.$fraction);
    }
}

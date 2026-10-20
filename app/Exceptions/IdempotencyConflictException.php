<?php

namespace App\Exceptions;

use RuntimeException;

class IdempotencyConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Kunci idempotensi yang sama telah digunakan dengan keranjang atau pembayaran berbeda.');
    }
}

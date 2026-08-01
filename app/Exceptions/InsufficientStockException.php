<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly string $productName, public readonly int $available)
    {
        parent::__construct(
            $available === 0
                ? "{$productName} se ha agotado mientras estabas comprando."
                : "Solo quedan {$available} unidades de {$productName}."
        );
    }
}

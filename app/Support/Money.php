<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Value object de dinero. Todo en céntimos enteros.
 * Evita que un float se cuele en un cálculo de precio.
 */
final readonly class Money
{
    private function __construct(
        public int $cents,
        public string $currency,
    ) {}

    public static function fromCents(int $cents, string $currency = 'EUR'): self
    {
        return new self($cents, strtoupper($currency));
    }

    public static function zero(string $currency = 'EUR'): self
    {
        return new self(0, strtoupper($currency));
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function times(int $factor): self
    {
        return new self($this->cents * $factor, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function format(): string
    {
        $symbol = match ($this->currency) {
            'EUR' => ' €',
            'USD' => ' $',
            default => ' '.$this->currency,
        };

        return number_format($this->cents / 100, 2, ',', '.').$symbol;
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "No se pueden sumar {$this->currency} y {$other->currency}."
            );
        }
    }
}

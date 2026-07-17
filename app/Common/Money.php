<?php

namespace App\Common;

use InvalidArgumentException;

/**
 * Value object para valores monetários. Internamente tudo é inteiro em centavos
 * para evitar os erros de arredondamento de float/double em cálculos financeiros.
 */
final readonly class Money
{
    private function __construct(
        public int $cents,
    ) {}

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function fromAmount(float|string $amount): self
    {
        $normalized = is_string($amount) ? str_replace(',', '.', $amount) : $amount;

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException("Valor monetário inválido: {$amount}");
        }

        return new self((int) round(((float) $normalized) * 100));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function toAmount(): float
    {
        return $this->cents / 100;
    }

    public function format(string $currencySymbol = 'R$'): string
    {
        $amount = number_format(abs($this->toAmount()), 2, ',', '.');
        $sign = $this->isNegative() ? '-' : '';

        return "{$sign}{$currencySymbol} {$amount}";
    }
}

<?php

namespace Tests\Unit\Common;

use App\Common\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_from_amount_converts_to_cents(): void
    {
        $money = Money::fromAmount(10.5);

        $this->assertSame(1050, $money->cents);
    }

    public function test_from_amount_accepts_comma_decimal_string(): void
    {
        $money = Money::fromAmount('10,50');

        $this->assertSame(1050, $money->cents);
    }

    public function test_add_sums_cents(): void
    {
        $result = Money::fromCents(1000)->add(Money::fromCents(250));

        $this->assertSame(1250, $result->cents);
    }

    public function test_subtract_can_produce_negative_values(): void
    {
        $result = Money::fromCents(500)->subtract(Money::fromCents(800));

        $this->assertSame(-300, $result->cents);
        $this->assertTrue($result->isNegative());
    }

    public function test_zero_is_neither_positive_nor_negative(): void
    {
        $zero = Money::zero();

        $this->assertTrue($zero->isZero());
        $this->assertFalse($zero->isPositive());
        $this->assertFalse($zero->isNegative());
    }

    public function test_format_renders_brl_with_two_decimals(): void
    {
        $this->assertSame('R$ 1.234,56', Money::fromCents(123456)->format());
    }

    public function test_format_shows_minus_sign_for_negative_values(): void
    {
        $this->assertSame('-R$ 10,00', Money::fromCents(-1000)->format());
    }
}

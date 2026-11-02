<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(public int $minorUnits)
    {
        if ($minorUnits < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }
    }

    public static function fromMinor(int $minorUnits): self
    {
        return new self($minorUnits);
    }

    /**
     * Parses digits rather than casting to float, so precision is never lost.
     * A third decimal place rounds half up.
     */
    public static function fromDecimal(string|float $amount): self
    {
        $decimal = is_float($amount) ? number_format($amount, 2, '.', '') : $amount;

        if (preg_match('/^([+-]?)(?=\.?\d)(\d*)\.?(\d*)$/', $decimal, $parts) !== 1) {
            throw new InvalidArgumentException("Invalid decimal amount [{$decimal}].");
        }

        [, $sign, $whole, $fraction] = $parts;

        $minorUnits = (int) $whole * 100
            + (int) substr(str_pad($fraction, 2, '0'), 0, 2)
            + ((int) ($fraction[2] ?? 0) >= 5 ? 1 : 0);

        return new self($sign === '-' ? -$minorUnits : $minorUnits);
    }

    public function add(self $other): self
    {
        return new self($this->minorUnits + $other->minorUnits);
    }

    public function multiply(int $factor): self
    {
        return new self($this->minorUnits * $factor);
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function toDecimal(): string
    {
        return number_format($this->minorUnits / 100, 2, '.', '');
    }

    public function format(): string
    {
        return number_format($this->minorUnits / 100, 2);
    }
}

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

    public static function fromDecimal(string|float $amount): self
    {
        $normalized = number_format((float) $amount, 2, '.', '');
        [$whole, $fraction] = explode('.', $normalized);

        return new self(((int) $whole * 100) + (int) $fraction);
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

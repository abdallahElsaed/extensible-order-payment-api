<?php

declare(strict_types=1);

use App\ValueObjects\Money;

it('creates money from minor units', function (): void {
    expect(Money::fromMinor(1999)->minorUnits())->toBe(1999);
});

it('converts a decimal string into minor units without drift', function (): void {
    expect(Money::fromDecimal('19.99')->minorUnits())->toBe(1999)
        ->and(Money::fromDecimal('0.10')->minorUnits())->toBe(10)
        ->and(Money::fromDecimal('100')->minorUnits())->toBe(10000);
});

it('converts a float into minor units without float drift', function (): void {
    expect(Money::fromDecimal(0.1 + 0.2)->minorUnits())->toBe(30)
        ->and(Money::fromDecimal(19.99)->minorUnits())->toBe(1999);
});

it('renders minor units back to a decimal string', function (): void {
    expect(Money::fromMinor(1999)->toDecimal())->toBe('19.99')
        ->and(Money::fromMinor(10000)->toDecimal())->toBe('100.00')
        ->and(Money::fromMinor(5)->toDecimal())->toBe('0.05');
});

it('adds two money values', function (): void {
    $sum = Money::fromMinor(1999)->add(Money::fromMinor(1));

    expect($sum->minorUnits())->toBe(2000)
        ->and($sum->toDecimal())->toBe('20.00');
});

it('multiplies money by an integer factor', function (): void {
    $total = Money::fromDecimal('2.50')->multiply(3);

    expect($total->minorUnits())->toBe(750)
        ->and($total->toDecimal())->toBe('7.50');
});

it('sums line totals exactly with no float drift', function (): void {
    $unitPrice = Money::fromDecimal('0.10');

    $total = collect(range(1, 10))->reduce(
        fn (Money $carry, int $i): Money => $carry->add($unitPrice),
        Money::fromMinor(0),
    );

    expect($total->minorUnits())->toBe(100)
        ->and($total->toDecimal())->toBe('1.00');
});

it('formats money with thousands separators', function (): void {
    expect(Money::fromMinor(123456789)->format())->toBe('1,234,567.89');
});

it('rejects negative amounts', function (): void {
    Money::fromMinor(-1);
})->throws(InvalidArgumentException::class);

it('rejects a negative decimal amount', function (): void {
    Money::fromDecimal('-0.50');
})->throws(InvalidArgumentException::class);

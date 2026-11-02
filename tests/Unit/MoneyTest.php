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

it('keeps every digit of a decimal string beyond float precision', function (): void {
    expect(Money::fromDecimal('90071992547409.93')->minorUnits())->toBe(9007199254740993);
});

it('parses a decimal string from its digits', function (string $amount, int $minorUnits): void {
    expect(Money::fromDecimal($amount)->minorUnits())->toBe($minorUnits);
})->with([
    'half rounds up where a float would not' => ['1.005', 101],
    'below half rounds down' => ['1.004', 100],
    'rounding carries into the whole part' => ['0.995', 100],
    'explicit plus' => ['+5', 500],
    'leading dot' => ['.5', 50],
    'trailing dot' => ['5.', 500],
    'zero' => ['0', 0],
    'negative zero' => ['-0', 0],
]);

it('rejects a string that is not a plain decimal', function (string $amount): void {
    Money::fromDecimal($amount);
})->with(['empty' => '', 'dot only' => '.', 'text' => 'abc', 'exponent' => '1e3', 'comma' => '1,50', 'spaces' => ' 1.50'])
    ->throws(InvalidArgumentException::class);

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

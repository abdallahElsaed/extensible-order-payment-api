<?php

declare(strict_types=1);

afterEach(function (): void {
    unset($_SERVER['PAYMENT_PENDING_TTL_MINUTES'], $_ENV['PAYMENT_PENDING_TTL_MINUTES']);
});

it('reads the pending TTL only from a positive whole number, or falls back to 15', function (string $value, int $minutes): void {
    $_SERVER['PAYMENT_PENDING_TTL_MINUTES'] = $_ENV['PAYMENT_PENDING_TTL_MINUTES'] = $value;

    expect((require config_path('payments.php'))['pending_ttl_minutes'])->toBe($minutes);
})->with([
    'valid' => ['30', 30],
    'text' => ['abc', 15],
    'empty' => ['', 15],
    'zero' => ['0', 15],
    'negative' => ['-5', 15],
    'fraction' => ['1.5', 15],
]);

<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\DTOs\Payment\GatewayResult;
use App\DTOs\Payment\ProcessPaymentData;
use App\Enums\Payment\PaymentStatus;
use App\Services\Payment\Contracts\PaymentGatewayContract;
use Closure;

/**
 * Runs a test-supplied closure mid-call, to observe or disturb the world while the
 * gateway is "working". The closure may throw, or return its own result.
 */
final class CallbackGateway implements PaymentGatewayContract
{
    public static ?Closure $callback = null;

    public function process(ProcessPaymentData $data): GatewayResult
    {
        $result = self::$callback !== null ? (self::$callback)($data) : null;

        return $result instanceof GatewayResult
            ? $result
            : new GatewayResult(PaymentStatus::Successful, 'callback_ref', 'Processed by the callback gateway.');
    }
}

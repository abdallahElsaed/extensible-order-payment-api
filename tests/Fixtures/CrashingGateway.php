<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\DTOs\Payment\GatewayResult;
use App\DTOs\Payment\ProcessPaymentData;
use App\Services\Payment\Contracts\PaymentGatewayContract;
use RuntimeException;

/**
 * Kills its own process mid-call: no shutdown, no rollback, no exception handling.
 */
final class CrashingGateway implements PaymentGatewayContract
{
    public function process(ProcessPaymentData $data): GatewayResult
    {
        posix_kill(getmypid(), SIGKILL);

        throw new RuntimeException('Unreachable: the process was killed.');
    }
}

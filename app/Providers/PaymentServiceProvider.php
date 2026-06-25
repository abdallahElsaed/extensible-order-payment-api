<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            PaymentGatewayRegistry::class,
            fn (Application $app): PaymentGatewayRegistry => new PaymentGatewayRegistry($app),
        );
    }
}

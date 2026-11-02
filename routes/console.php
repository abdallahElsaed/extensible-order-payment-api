<?php

use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('payments:expire-stale', function (PaymentService $payments) {
    $this->info(sprintf('Expired %d stale pending payment(s).', $payments->expireStale()));
})->purpose('Fail payments left pending past their TTL, for example after a crash');

Schedule::command('payments:expire-stale')->everyFiveMinutes();

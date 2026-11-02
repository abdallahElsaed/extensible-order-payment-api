<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config(['payments.pending_ttl_minutes' => 15]);
    $this->user = User::factory()->create();
    $this->order = Order::factory()->for($this->user)->confirmed()->create();
});

it('fails a pending payment older than the TTL', function () {
    $payment = Payment::factory()->for($this->order)->pending()->create(['created_at' => now()->subMinutes(15)]);

    $this->artisan('payments:expire-stale')
        ->expectsOutputToContain('Expired 1 stale')
        ->assertSuccessful();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->message)->toContain('never confirmed');
});

it('leaves a pending payment younger than the TTL alone', function () {
    $payment = Payment::factory()->for($this->order)->pending()->create(['created_at' => now()->subMinutes(14)]);

    $this->artisan('payments:expire-stale')->assertSuccessful();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('does not touch old settled payments', function () {
    $payment = Payment::factory()->for($this->order)->successful()->create(['created_at' => now()->subDay()]);

    $this->artisan('payments:expire-stale')->assertSuccessful();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Successful);
});

it('frees the order for a new attempt once the stuck payment expires', function () {
    Payment::factory()->for($this->order)->pending()->create(['created_at' => now()->subHour()]);

    $this->artisan('payments:expire-stale')->assertSuccessful();

    $this->actingAs($this->user, 'api')
        ->postJson("/api/orders/{$this->order->id}/payments", ['method' => 'credit_card'])
        ->assertCreated();
});

it('is scheduled every five minutes', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toMatch('/\*\/5 \* \* \* \*.*payments:expire-stale/');
});

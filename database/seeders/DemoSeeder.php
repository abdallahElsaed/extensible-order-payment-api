<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Payment\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds a known demo account with a few orders, line items, and a payment so the
 * API can be explored manually (Postman / curl) without building state by hand.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo User', 'password' => 'password'],
        );

        $pending = Order::factory()->for($user)->pending()->create([
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
        ]);
        $this->attachItems($pending, [
            ['Keyboard', 1, 7500],
            ['Mouse', 2, 2500],
        ]);

        $confirmed = Order::factory()->for($user)->confirmed()->create([
            'customer_name' => 'Alan Turing',
            'customer_email' => 'alan@example.com',
        ]);
        $this->attachItems($confirmed, [
            ['Monitor', 1, 35000],
            ['HDMI Cable', 3, 1200],
        ]);

        Payment::factory()->for($confirmed)->successful()->create([
            'method' => PaymentMethod::CreditCard,
            'reference' => 'cc_'.Str::uuid()->toString(),
            'message' => 'Credit card payment approved.',
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: int, 2: int}>  $items
     */
    private function attachItems(Order $order, array $items): void
    {
        $total = 0;

        foreach ($items as [$productName, $quantity, $unitPrice]) {
            $order->items()->create([
                'product_name' => $productName,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
            ]);

            $total += $quantity * $unitPrice;
        }

        $order->update(['total' => $total]);
    }
}

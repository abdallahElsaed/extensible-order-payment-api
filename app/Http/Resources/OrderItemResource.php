<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'unit_price' => Money::fromMinor($this->unit_price)->toDecimal(),
            'line_total' => Money::fromMinor($this->line_total)->toDecimal(),
        ];
    }
}

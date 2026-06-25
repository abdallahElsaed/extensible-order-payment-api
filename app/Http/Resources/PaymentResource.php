<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'order_id' => $this->order_id,
            'method' => $this->method->value,
            'status' => $this->status->value,
            'reference' => $this->reference,
            'message' => $this->message,
            'created_at' => $this->created_at,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\IdempotencyKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $key
 * @property string $request_hash
 * @property int $payment_id
 */
#[Fillable(['user_id', 'key', 'request_hash', 'payment_id'])]
class IdempotencyKey extends Model
{
    /** @use HasFactory<IdempotencyKeyFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}

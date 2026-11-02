<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use App\DTOs\Payment\IdempotencyData;
use App\Enums\Payment\PaymentMethod;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ProcessPaymentRequest extends FormRequest
{
    private const IDEMPOTENCY_HEADER = 'Idempotency-Key';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $key = $this->header(self::IDEMPOTENCY_HEADER);

                if ($key !== null && preg_match('/^[\x21-\x7E]{1,255}$/', $key) !== 1) {
                    $validator->errors()->add(self::IDEMPOTENCY_HEADER, 'Must be 1 to 255 visible ASCII characters.');
                }
            },
        ];
    }

    public function idempotency(): ?IdempotencyData
    {
        $key = $this->header(self::IDEMPOTENCY_HEADER);

        if ($key === null) {
            return null;
        }

        return new IdempotencyData(
            key: $key,
            requestHash: hash('sha256', json_encode([$this->path(), Arr::sortRecursive($this->input())], JSON_THROW_ON_ERROR)),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Payment\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ProcessPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Responses\ApiResponse;
use App\Services\Order\OrderService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly OrderService $orders,
    ) {}

    public function store(ProcessPaymentRequest $request, int $order): JsonResponse
    {
        $model = $this->orders->findForUser($request->user(), $order);

        $payment = $this->payments->process(
            order: $model,
            method: PaymentMethod::from($request->validated('method')),
        );

        return ApiResponse::success(
            data: new PaymentResource($payment),
            message: 'Payment processed.',
            status: 201,
        );
    }

    public function indexForOrder(Request $request, int $order): JsonResponse
    {
        $model = $this->orders->findForUser($request->user(), $order);

        return ApiResponse::paginated(
            paginated: PaymentResource::collection(
                $this->payments->paginateForOrder($model, $this->perPage($request)),
            )->response()->getData(true),
            message: 'Payments retrieved.',
        );
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            paginated: PaymentResource::collection(
                $this->payments->paginateForUser($request->user(), $this->perPage($request)),
            )->response()->getData(true),
            message: 'Payments retrieved.',
        );
    }

    public function show(Request $request, string $payment): JsonResponse
    {
        return ApiResponse::success(
            data: new PaymentResource($this->payments->findForUser($request->user(), $payment)),
            message: 'Payment retrieved.',
        );
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 15) ?: 15, 100);
    }
}

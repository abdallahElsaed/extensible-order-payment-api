<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Order\CreateOrderData;
use App\DTOs\Order\UpdateOrderData;
use App\Enums\Order\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $perPage = min((int) $request->integer('per_page', 15) ?: 15, 100);

        $orders = $this->orders->paginateForUser(
            user: $request->user(),
            status: $status !== null ? OrderStatus::tryFrom((string) $status) : null,
            perPage: $perPage,
        );

        return ApiResponse::paginated(
            paginated: OrderResource::collection($orders)->response()->getData(true),
            message: 'Orders retrieved.',
        );
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->create(
            user: $request->user(),
            data: CreateOrderData::fromArray($request->validated()),
        );

        return ApiResponse::success(
            data: new OrderResource($order),
            message: 'Order created.',
            status: 201,
        );
    }

    public function show(Request $request, int $order): JsonResponse
    {
        return ApiResponse::success(
            data: new OrderResource($this->orders->findForUser($request->user(), $order)),
            message: 'Order retrieved.',
        );
    }

    public function update(UpdateOrderRequest $request, int $order): JsonResponse
    {
        $model = $this->orders->findForUser($request->user(), $order);

        return ApiResponse::success(
            data: new OrderResource($this->orders->update($model, UpdateOrderData::fromArray($request->validated()))),
            message: 'Order updated.',
        );
    }

    public function destroy(Request $request, int $order): JsonResponse
    {
        $this->orders->delete($this->orders->findForUser($request->user(), $order));

        return ApiResponse::success(message: 'Order deleted.');
    }
}

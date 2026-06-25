<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status);
    }

    /**
     * @param  array{data?: mixed, links?: mixed, meta?: mixed}  $paginated
     */
    public static function paginated(
        array $paginated,
        string $message = 'OK',
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginated['data'] ?? [],
            'links' => $paginated['links'] ?? null,
            'meta' => $paginated['meta'] ?? null,
            'errors' => null,
        ], $status);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     */
    public static function error(
        string $message,
        ?array $errors = null,
        int $status = 400,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        $token = $this->guard()->login($user);

        return $this->respondWithToken($user, $token, 'Registered.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $token = $this->guard()->attempt($request->validated());

        if ($token === false) {
            return ApiResponse::error(
                message: 'Invalid credentials.',
                status: 401,
            );
        }

        /** @var User $user */
        $user = $this->guard()->user();

        return $this->respondWithToken($user, $token, 'Authenticated.');
    }

    private function respondWithToken(
        User $user,
        string $token,
        string $message,
        int $status = 200,
    ): JsonResponse {
        return ApiResponse::success(
            data: [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $this->guard()->getTTL() * 60,
            ],
            message: $message,
            status: $status,
        );
    }

    private function guard(): JWTGuard
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');

        return $guard;
    }
}

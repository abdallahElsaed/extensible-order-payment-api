<?php

use App\Exceptions\Order\InvalidStatusTransitionException;
use App\Exceptions\Order\OrderHasPaymentsException;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(fn (ValidationException $e) => ApiResponse::error(
            message: 'The given data was invalid.',
            errors: $e->errors(),
            status: 422,
        ));

        $exceptions->render(fn (AuthenticationException $e) => ApiResponse::error(
            message: 'Unauthenticated.',
            status: 401,
        ));

        $exceptions->render(fn (ModelNotFoundException|NotFoundHttpException $e) => ApiResponse::error(
            message: 'Resource not found.',
            status: 404,
        ));

        $exceptions->render(fn (OrderHasPaymentsException|InvalidStatusTransitionException $e) => ApiResponse::error(
            message: $e->getMessage(),
            status: 409,
        ));
    })->create();

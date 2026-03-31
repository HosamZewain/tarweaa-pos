<?php

use App\Exceptions\DrawerException;
use App\Exceptions\OrderException;
use App\Exceptions\ShiftException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'surface' => \App\Http\Middleware\EnsureSurfaceAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $businessError = function (\Throwable $e) {
            $status = $e->getCode();
            $status = is_int($status) && $status >= 400 && $status <= 599
                ? $status
                : 422;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        };

        $exceptions->render(function (ShiftException $e, Request $request) use ($businessError) {
            if ($request->expectsJson()) {
                return $businessError($e);
            }
        });

        $exceptions->render(function (DrawerException $e, Request $request) use ($businessError) {
            if ($request->expectsJson()) {
                return $businessError($e);
            }
        });

        $exceptions->render(function (OrderException $e, Request $request) use ($businessError) {
            if ($request->expectsJson()) {
                return $businessError($e);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson()) {
                $model = class_basename($e->getModel());

                return response()->json([
                    'success' => false,
                    'message' => "العنصر [{$model}] غير موجود.",
                ], 404);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح. يرجى تسجيل الدخول.',
                ], 401);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات غير صالحة.',
                    'errors' => $e->errors(),
                ], $e->status);
            }
        });
    })->create();

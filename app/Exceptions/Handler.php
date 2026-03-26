<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
        'pin',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (ShiftException $e, Request $request) {
            if ($request->expectsJson()) {
                return $this->businessError($e);
            }
        });

        $this->renderable(function (DrawerException $e, Request $request) {
            if ($request->expectsJson()) {
                return $this->businessError($e);
            }
        });

        $this->renderable(function (OrderException $e, Request $request) {
            if ($request->expectsJson()) {
                return $this->businessError($e);
            }
        });

        $this->renderable(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson()) {
                $model = class_basename($e->getModel());

                return response()->json([
                    'success' => false,
                    'message' => "العنصر [{$model}] غير موجود.",
                ], 404);
            }
        });

        $this->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح. يرجى تسجيل الدخول.',
                ], 401);
            }
        });
    }

    /**
     * Convert a business logic exception to a JSON response.
     */
    private function businessError(Throwable $e): JsonResponse
    {
        $status = $e->getCode();
        $status = is_int($status) && $status >= 400 && $status <= 599
            ? $status
            : 422;

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], $status);
    }

    /**
     * Convert a validation exception into a JSON response.
     */
    protected function invalidJson($request, ValidationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'بيانات غير صالحة.',
            'errors'  => $exception->errors(),
        ], $exception->status);
    }
}

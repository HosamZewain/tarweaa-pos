<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    /**
     * Return a success JSON response.
     */
    protected function success(mixed $data = null, string $message = 'تمت العملية بنجاح', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Return a created (201) JSON response.
     */
    protected function created(mixed $data = null, string $message = 'تم الإنشاء بنجاح'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Return an error JSON response.
     */
    protected function error(string $message, int $code = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Return a not-found JSON response.
     */
    protected function notFound(string $message = 'العنصر غير موجود'): JsonResponse
    {
        return $this->error($message, 404);
    }

    /**
     * Return a paginated JSON response.
     */
    protected function paginated($paginator, string $message = 'تمت العملية بنجاح'): JsonResponse
    {
        return response()->json([
            'success'    => true,
            'message'    => $message,
            'data'       => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }
}

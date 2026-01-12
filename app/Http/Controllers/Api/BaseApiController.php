<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class BaseApiController extends Controller
{
    /**
     * Return a successful JSON response
     */
    protected function success(
        mixed $data = null,
        string $message = '',
        int $statusCode = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'request_id' => Str::uuid()->toString(),
            ],
        ], $statusCode);
    }

    /**
     * Return an error JSON response
     */
    protected function error(
        string $message,
        string $code = 'ERROR',
        mixed $details = null,
        int $statusCode = 400
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'request_id' => Str::uuid()->toString(),
            ],
        ], $statusCode);
    }

    /**
     * Return a validation error response
     */
    protected function validationError(
        array $errors,
        string $message = 'The given data was invalid'
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => $message,
                'errors' => $errors,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'request_id' => Str::uuid()->toString(),
            ],
        ], 422);
    }

    /**
     * Return an unauthorized error response
     */
    protected function unauthorized(
        string $message = 'Unauthorized access'
    ): JsonResponse {
        return $this->error(
            $message,
            'UNAUTHORIZED',
            null,
            401
        );
    }

    /**
     * Return a forbidden error response
     */
    protected function forbidden(
        string $message = 'Access forbidden'
    ): JsonResponse {
        return $this->error(
            $message,
            'FORBIDDEN',
            null,
            403
        );
    }

    /**
     * Return a not found error response
     */
    protected function notFound(
        string $message = 'Resource not found'
    ): JsonResponse {
        return $this->error(
            $message,
            'NOT_FOUND',
            null,
            404
        );
    }
}

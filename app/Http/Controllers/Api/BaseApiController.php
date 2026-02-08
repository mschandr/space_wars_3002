<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\FindsByUuid;
use App\Http\Controllers\Api\Traits\ResolvesPlayer;
use App\Http\Controllers\Api\Traits\ResolvesShip;
use App\Http\Controllers\Controller;
use App\Models\PointOfInterest;
use App\Models\TradingHub;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Base API Controller
 *
 * Provides common functionality for all API controllers:
 * - Standardized JSON response methods (success, error, notFound, etc.)
 * - UUID-based resource lookup helpers (FindsByUuid trait)
 * - Player resolution with authorization (ResolvesPlayer trait)
 * - Ship resolution with authorization (ResolvesShip trait)
 * - Trading hub lookup helper
 */
class BaseApiController extends Controller
{
    use FindsByUuid;
    use ResolvesPlayer;
    use ResolvesShip;

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

    /**
     * Authorize that the player belongs to the authenticated user
     */
    protected function authorizePlayer($player, $user): void
    {
        if ($player->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this player');
        }
    }

    /**
     * Return error response from a resource validation result.
     *
     * Works with ResourceValidatorService results.
     *
     * @param  array{valid: bool, message: string|null, code: string|null}  $result
     * @return JsonResponse|null Returns null if valid, JsonResponse if invalid
     */
    protected function failIfInvalid(array $result): ?JsonResponse
    {
        if ($result['valid']) {
            return null;
        }

        return $this->error(
            $result['message'] ?? 'Validation failed',
            $result['code'] ?? 'VALIDATION_FAILED',
            null,
            400
        );
    }

    /**
     * Find a trading hub by UUID (supports both TradingHub UUID and POI UUID).
     *
     * This centralized helper reduces code duplication across controllers
     * and can be cached for improved performance.
     */
    protected function findTradingHub(string $uuid): ?TradingHub
    {
        // First try direct trading hub lookup
        $tradingHub = TradingHub::where('uuid', $uuid)->first();

        if ($tradingHub) {
            return $tradingHub;
        }

        // Try POI UUID lookup
        $poi = PointOfInterest::where('uuid', $uuid)->with('tradingHub')->first();

        return $poi?->tradingHub;
    }
}

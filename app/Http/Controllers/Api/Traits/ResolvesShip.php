<?php

namespace App\Http\Controllers\Api\Traits;

use App\Models\PlayerShip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trait for resolving player ships from UUID with authorization.
 *
 * Consolidates the repeated pattern of:
 * - Finding ship by UUID
 * - Verifying ownership (ship belongs to authenticated user's player)
 * - Optional eager loading
 */
trait ResolvesShip
{
    /**
     * Find a ship by UUID that belongs to the authenticated user.
     *
     * @param  string  $uuid  Ship UUID
     * @param  Request  $request  HTTP request with authenticated user
     * @param  array  $with  Relationships to eager load
     */
    protected function findAuthenticatedShip(string $uuid, Request $request, array $with = []): ?PlayerShip
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $query = PlayerShip::where('uuid', $uuid)
            ->whereHas('player', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            });

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->first();
    }

    /**
     * Find an authenticated ship or return error response.
     *
     * @param  string  $uuid  Ship UUID
     * @param  Request  $request  HTTP request with authenticated user
     * @param  array  $with  Relationships to eager load
     */
    protected function findAuthenticatedShipOrFail(string $uuid, Request $request, array $with = []): PlayerShip|JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->unauthorized('Authentication required');
        }

        $ship = $this->findAuthenticatedShip($uuid, $request, $with);

        if (! $ship) {
            return $this->notFound('Ship not found');
        }

        return $ship;
    }

    /**
     * Find a ship by UUID (any ship, not ownership-checked).
     *
     * Use this when you need to look up ship info without ownership check.
     *
     * @param  string  $uuid  Ship UUID
     * @param  array  $with  Relationships to eager load
     */
    protected function findShipByUuid(string $uuid, array $with = []): ?PlayerShip
    {
        $query = PlayerShip::where('uuid', $uuid);

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->first();
    }

    /**
     * Find a ship with full component data loaded.
     *
     * Common pattern for upgrade/repair operations.
     *
     * @param  string  $uuid  Ship UUID
     * @param  Request  $request  HTTP request with authenticated user
     */
    protected function findShipWithComponentsOrFail(string $uuid, Request $request): PlayerShip|JsonResponse
    {
        return $this->findAuthenticatedShipOrFail($uuid, $request, [
            'ship',
            'player',
            'components.component',
        ]);
    }
}

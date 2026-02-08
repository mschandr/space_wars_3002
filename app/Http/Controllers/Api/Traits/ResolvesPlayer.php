<?php

namespace App\Http\Controllers\Api\Traits;

use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trait for resolving player from UUID with authorization.
 *
 * Consolidates the repeated pattern of:
 * - Finding player by UUID
 * - Verifying ownership (user_id matches authenticated user)
 * - Optional eager loading
 * - Returning not found or forbidden responses
 */
trait ResolvesPlayer
{
    /**
     * Find an authenticated player by UUID.
     *
     * Only returns the player if it belongs to the authenticated user.
     *
     * @param  string  $uuid  Player UUID
     * @param  Request  $request  HTTP request with authenticated user
     * @param  array  $with  Relationships to eager load
     */
    protected function findAuthenticatedPlayer(string $uuid, Request $request, array $with = []): ?Player
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $query = Player::where('uuid', $uuid)
            ->where('user_id', $user->id);

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->first();
    }

    /**
     * Find an authenticated player or return error response.
     *
     * @param  string  $uuid  Player UUID
     * @param  Request  $request  HTTP request with authenticated user
     * @param  array  $with  Relationships to eager load
     */
    protected function findAuthenticatedPlayerOrFail(string $uuid, Request $request, array $with = []): Player|JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->unauthorized('Authentication required');
        }

        $player = $this->findAuthenticatedPlayer($uuid, $request, $with);

        if (! $player) {
            return $this->notFound('Player not found');
        }

        return $player;
    }

    /**
     * Find player by UUID (any player, not ownership-checked).
     *
     * Use this when you need to look up another player's public info.
     *
     * @param  string  $uuid  Player UUID
     * @param  array  $with  Relationships to eager load
     */
    protected function findPlayerByUuid(string $uuid, array $with = []): ?Player
    {
        $query = Player::where('uuid', $uuid);

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->first();
    }

    /**
     * Find a player with active ship validation.
     *
     * Common pattern for actions that require an active ship.
     *
     * @param  string  $uuid  Player UUID
     * @param  Request  $request  HTTP request with authenticated user
     * @param  array  $additionalWith  Additional relationships to eager load
     */
    protected function findPlayerWithShipOrFail(string $uuid, Request $request, array $additionalWith = []): Player|JsonResponse
    {
        $with = array_merge(['activeShip', 'currentLocation'], $additionalWith);

        $result = $this->findAuthenticatedPlayerOrFail($uuid, $request, $with);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        if (! $result->activeShip) {
            return $this->error('Player has no active ship', 'NO_ACTIVE_SHIP', null, 400);
        }

        return $result;
    }

    /**
     * Find a player with location validation.
     *
     * Common pattern for actions that require a current location.
     *
     * @param  string  $uuid  Player UUID
     * @param  Request  $request  HTTP request with authenticated user
     * @param  array  $additionalWith  Additional relationships to eager load
     */
    protected function findPlayerWithLocationOrFail(string $uuid, Request $request, array $additionalWith = []): Player|JsonResponse
    {
        $with = array_merge(['currentLocation'], $additionalWith);

        $result = $this->findAuthenticatedPlayerOrFail($uuid, $request, $with);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        if (! $result->currentLocation) {
            return $this->error('Player has no current location', 'NO_LOCATION', null, 400);
        }

        return $result;
    }
}

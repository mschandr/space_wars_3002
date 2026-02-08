<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ShipResource;
use App\Models\Galaxy;
use App\Models\PlayerShip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Handles basic ship operations (get, rename, fuel regen trigger)
 */
class ShipController extends BaseApiController
{
    /**
     * Get my ship in a specific galaxy (uses authenticated user context)
     *
     * GET /api/galaxies/{galaxyUuid}/my-ship
     */
    public function getMyShip(Request $request, string $galaxyUuid): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $galaxyUuid)->first();

        if (! $galaxy) {
            return $this->notFound('Galaxy not found');
        }

        $player = $request->user()
            ->players()
            ->where('galaxy_id', $galaxy->id)
            ->first();

        if (! $player) {
            return $this->error('You are not in this galaxy', 'NOT_IN_GALAXY', null, 404);
        }

        $ship = $player->activeShip()->with('ship')->first();

        if (! $ship) {
            return $this->error('No active ship found', 'NO_SHIP', null, 404);
        }

        return $this->success(new ShipResource($ship));
    }

    /**
     * Get active ship details for a player
     *
     * GET /api/players/{playerUuid}/ship
     */
    public function getActiveShip(Request $request, string $playerUuid): JsonResponse
    {
        $player = $request->user()
            ->players()
            ->where('uuid', $playerUuid)
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $ship = $player->activeShip()->with('ship')->first();

        if (! $ship) {
            return $this->notFound('No active ship found');
        }

        return $this->success(new ShipResource($ship));
    }

    /**
     * Trigger manual fuel regeneration (recalculate current fuel)
     *
     * POST /api/ships/{uuid}/regenerate-fuel
     */
    public function regenerateFuel(Request $request, string $uuid): JsonResponse
    {
        $ship = PlayerShip::where('uuid', $uuid)
            ->whereHas('player', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->first();

        if (! $ship) {
            return $this->notFound('Ship not found');
        }

        $fuelBefore = $ship->current_fuel;
        $ship->regenerateFuel();
        $fuelAfter = $ship->current_fuel;

        $fuelRegenerated = $fuelAfter - $fuelBefore;

        return $this->success([
            'fuel_before' => $fuelBefore,
            'fuel_after' => $fuelAfter,
            'fuel_regenerated' => $fuelRegenerated,
            'max_fuel' => $ship->max_fuel,
            'is_full' => $fuelAfter >= $ship->max_fuel,
            'time_to_full' => $ship->getTimeUntilFullFuel(),
        ], 'Fuel regenerated successfully');
    }

    /**
     * Rename ship
     *
     * PATCH /api/ships/{uuid}/name
     */
    public function rename(Request $request, string $uuid): JsonResponse
    {
        $ship = PlayerShip::where('uuid', $uuid)
            ->whereHas('player', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->first();

        if (! $ship) {
            return $this->notFound('Ship not found');
        }

        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:100'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $ship->name = $validated['name'];
        $ship->save();

        return $this->success(
            new ShipResource($ship->load('ship')),
            'Ship renamed successfully'
        );
    }
}

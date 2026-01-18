<?php

namespace App\Http\Controllers\Api;

use App\Models\Player;
use App\Services\TravelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Handles travel-related calculations and previews
 */
class TravelCalculationController extends BaseApiController
{
    public function __construct(
        private TravelService $travelService
    ) {}

    /**
     * Preview XP for a distance
     *
     * GET /api/travel/xp-preview?distance=100
     */
    public function previewXP(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'distance' => ['required', 'numeric', 'min:0'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $xp = $this->travelService->calculateTravelXP((float) $validated['distance']);

        return $this->success([
            'distance' => (float) $validated['distance'],
            'xp_earned' => $xp,
        ]);
    }

    /**
     * Calculate fuel cost for a distance
     *
     * GET /api/travel/fuel-cost?distance=100&player_uuid=xxx
     */
    public function calculateFuelCost(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'distance' => ['required', 'numeric', 'min:0'],
                'player_uuid' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $player = Player::where('uuid', $validated['player_uuid'])
            ->where('user_id', $request->user()->id)
            ->with('activeShip')
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        if (! $player->activeShip) {
            return $this->error('No active ship', 'NO_ACTIVE_SHIP');
        }

        $fuelCost = $this->travelService->calculateFuelCost(
            (float) $validated['distance'],
            $player->activeShip
        );

        return $this->success([
            'distance' => (float) $validated['distance'],
            'fuel_cost' => $fuelCost,
            'ship_warp_drive' => $player->activeShip->warp_drive,
            'current_fuel' => $player->activeShip->current_fuel,
            'can_afford' => $player->activeShip->current_fuel >= $fuelCost,
        ]);
    }
}

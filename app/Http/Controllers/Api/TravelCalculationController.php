<?php

namespace App\Http\Controllers\Api;

use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\WarpGate;
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
     * Calculate fuel cost to reach a destination
     *
     * GET /api/travel/fuel-cost?ship_uuid=<uuid>&poi_uuid=<uuid>
     * GET /api/travel/fuel-cost?ship_uuid=<uuid>&x=100&y=200
     *
     * Accepts a ship UUID and either a destination POI UUID or raw coordinates.
     * Returns fuel costs for both warp gate travel (if a gate exists) and direct jump.
     */
    public function calculateFuelCost(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ship_uuid' => ['required', 'string'],
                'poi_uuid' => ['required_without_all:x,y', 'string'],
                'x' => ['required_without:poi_uuid', 'numeric'],
                'y' => ['required_without:poi_uuid', 'numeric'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $ship = PlayerShip::where('uuid', $validated['ship_uuid'])
            ->whereHas('player', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->with(['player.currentLocation'])
            ->first();

        if (! $ship) {
            return $this->notFound('Ship not found');
        }

        $currentLocation = $ship->player->currentLocation;

        if (! $currentLocation) {
            return $this->error('Current location not found', 'NO_LOCATION');
        }

        // Resolve destination coordinates
        $destination = null;
        if (isset($validated['poi_uuid'])) {
            $destination = PointOfInterest::where('uuid', $validated['poi_uuid'])
                ->where('galaxy_id', $currentLocation->galaxy_id)
                ->first();

            if (! $destination) {
                return $this->notFound('Destination not found');
            }

            $destX = $destination->x;
            $destY = $destination->y;
        } else {
            $destX = (float) $validated['x'];
            $destY = (float) $validated['y'];
        }

        // Calculate distance
        $distance = sqrt(
            pow($destX - $currentLocation->x, 2) +
            pow($destY - $currentLocation->y, 2)
        );

        // Warp gate option — check if a direct gate exists to the destination
        $warpGate = null;
        if ($destination) {
            $warpGate = WarpGate::where(function ($query) use ($currentLocation, $destination) {
                $query->where(function ($q) use ($currentLocation, $destination) {
                    $q->where('source_poi_id', $currentLocation->id)
                        ->where('destination_poi_id', $destination->id);
                })->orWhere(function ($q) use ($currentLocation, $destination) {
                    $q->where('source_poi_id', $destination->id)
                        ->where('destination_poi_id', $currentLocation->id);
                });
            })
                ->where('is_hidden', false)
                ->where('status', 'active')
                ->first();
        }

        $warpGateOption = null;
        if ($warpGate) {
            $gateDistance = $warpGate->distance ?? $warpGate->calculateDistance();
            $gateFuelCost = $this->travelService->calculateFuelCost($gateDistance, $ship);
            $warpGateOption = [
                'gate_uuid' => $warpGate->uuid,
                'distance' => round($gateDistance, 2),
                'fuel_cost' => $gateFuelCost,
                'can_afford' => $ship->current_fuel >= $gateFuelCost,
            ];
        }

        // Direct jump option
        $maxJumpDistance = $this->travelService->getMaxJumpDistance($ship);
        $directFuelCost = $this->travelService->calculateDirectJumpFuelCost($distance, $ship);
        $inRange = $distance <= $maxJumpDistance;

        $directJumpOption = [
            'distance' => round($distance, 2),
            'fuel_cost' => $directFuelCost,
            'can_afford' => $ship->current_fuel >= $directFuelCost,
            'in_range' => $inRange,
            'max_range' => $maxJumpDistance,
        ];

        // Determine cheapest option
        $cheapestOption = null;
        $cheapestFuelCost = null;

        if ($warpGateOption && $warpGateOption['can_afford']) {
            $cheapestOption = 'warp_gate';
            $cheapestFuelCost = $warpGateOption['fuel_cost'];
        }

        if ($inRange && $directJumpOption['can_afford']) {
            if ($cheapestFuelCost === null || $directFuelCost < $cheapestFuelCost) {
                $cheapestOption = 'direct_jump';
                $cheapestFuelCost = $directFuelCost;
            }
        }

        return $this->success([
            'from' => [
                'uuid' => $currentLocation->uuid,
                'name' => $currentLocation->name,
                'x' => $currentLocation->x,
                'y' => $currentLocation->y,
            ],
            'to' => $destination ? [
                'uuid' => $destination->uuid,
                'name' => $destination->name,
                'x' => $destination->x,
                'y' => $destination->y,
            ] : [
                'x' => $destX,
                'y' => $destY,
            ],
            'distance' => round($distance, 2),
            'ship' => [
                'current_fuel' => $ship->current_fuel,
                'max_fuel' => $ship->max_fuel,
                'warp_drive' => $ship->warp_drive,
            ],
            'warp_gate' => $warpGateOption,
            'direct_jump' => $directJumpOption,
            'cheapest_option' => $cheapestOption,
            'cheapest_fuel_cost' => $cheapestFuelCost,
            'can_reach' => $cheapestOption !== null,
        ]);
    }
}

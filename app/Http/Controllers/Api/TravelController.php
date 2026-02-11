<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PointOfInterestResource;
use App\Models\PointOfInterest;
use App\Models\WarpGate;
use App\Services\TravelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Handles player travel and movement
 *
 * Uses ResolvesPlayer trait from BaseApiController for player lookup.
 */
class TravelController extends BaseApiController
{
    public function __construct(
        private TravelService $travelService
    ) {}

    /**
     * List available warp gates at a location
     *
     * GET /api/warp-gates/{locationUuid}
     */
    public function listWarpGates(string $locationUuid): JsonResponse
    {
        // Use trait method for UUID lookup
        $result = $this->findByUuidOrNotFound(PointOfInterest::class, $locationUuid, 'Location not found');

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $location = $result;

        $gates = WarpGate::where('source_poi_id', $location->id)
            ->where('is_hidden', false)
            ->where('status', 'active')
            ->with('destinationPoi')
            ->get();

        $gatesData = $gates->map(function ($gate) {
            return [
                'uuid' => $gate->uuid,
                'destination' => new PointOfInterestResource($gate->destinationPoi),
                'fuel_cost' => $gate->fuel_cost,
                'distance' => round($gate->distance, 2),
            ];
        });

        return $this->success([
            'location' => new PointOfInterestResource($location),
            'gate_count' => $gates->count(),
            'gates' => $gatesData,
        ]);
    }

    /**
     * Travel via warp gate
     *
     * POST /api/players/{uuid}/travel/warp-gate
     */
    public function travelViaWarpGate(Request $request, string $uuid): JsonResponse
    {
        try {
            // TODO: (Weak Validation) gate_uuid should validate UUID format ('uuid' rule) rather
            // than just 'string'. Same applies to target_poi_uuid in directJumpToHub().
            $validated = $request->validate([
                'gate_uuid' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        // Use trait method - finds player with ship validation
        $result = $this->findPlayerWithShipOrFail($uuid, $request, ['currentLocation']);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $player = $result;

        $gate = WarpGate::where('uuid', $validated['gate_uuid'])
            ->where('source_poi_id', $player->current_poi_id)
            ->first();

        if (! $gate) {
            return $this->error('Warp gate not found at current location', 'GATE_NOT_FOUND');
        }

        $travelResult = $this->travelService->executeTravel($player, $gate);

        if (! $travelResult['success']) {
            return $this->error($travelResult['message'], $travelResult['code'] ?? 'TRAVEL_FAILED');
        }

        // Get the destination POI
        $destination = $player->fresh()->currentLocation;

        return $this->success([
            'fuel_consumed' => $travelResult['fuel_cost'],
            'xp_earned' => $travelResult['xp_earned'],
            'new_location' => new PointOfInterestResource($destination),
            'level_up' => $travelResult['leveled_up'] ?? false,
            'new_level' => $travelResult['new_level'],
            'pirate_encounter' => $travelResult['pirate_encounter'] ?? null,
        ], 'Travel successful');
    }

    /**
     * Jump to coordinates
     *
     * POST /api/players/{uuid}/travel/coordinate
     */
    public function jumpToCoordinates(Request $request, string $uuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'target_x' => ['required', 'numeric'],
                'target_y' => ['required', 'numeric'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        // Use trait method - finds player with ship validation
        $result = $this->findPlayerWithShipOrFail($uuid, $request, ['currentLocation']);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $player = $result;

        $travelResult = $this->travelService->executeDirectJump(
            $player,
            (int) $validated['target_x'],
            (int) $validated['target_y']
        );

        if (! $travelResult['success']) {
            return $this->error($travelResult['message'], $travelResult['code'] ?? 'JUMP_FAILED');
        }

        // Get the destination POI
        $destination = $player->fresh()->currentLocation;

        return $this->success([
            'fuel_consumed' => $travelResult['fuel_cost'],
            'xp_earned' => $travelResult['xp_earned'],
            'new_location' => new PointOfInterestResource($destination),
            'level_up' => $travelResult['leveled_up'] ?? false,
            'new_level' => $travelResult['new_level'],
        ], 'Jump successful');
    }

    /**
     * Direct jump to trading hub
     *
     * POST /api/players/{uuid}/travel/direct-jump
     */
    public function directJumpToHub(Request $request, string $uuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'target_poi_uuid' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        // Use trait method for UUID lookup
        $result = $this->findByUuidOrNotFound(
            PointOfInterest::class,
            $validated['target_poi_uuid'],
            'Target location not found'
        );

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $targetPoi = $result;

        // Reuse coordinate jump logic with POI coordinates
        $request->merge(['target_x' => $targetPoi->x, 'target_y' => $targetPoi->y]);

        return $this->jumpToCoordinates($request, $uuid);
    }
}

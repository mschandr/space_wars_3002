<?php

namespace App\Http\Controllers\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Http\Resources\PointOfInterestResource;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\WarpGate;
use App\Services\TravelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Handles player travel and movement
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
        $location = PointOfInterest::where('uuid', $locationUuid)->first();

        if (! $location) {
            return $this->notFound('Location not found');
        }

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
            $validated = $request->validate([
                'gate_uuid' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $player = Player::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with(['activeShip', 'currentLocation'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        if (! $player->activeShip) {
            return $this->error('No active ship', 'NO_ACTIVE_SHIP');
        }

        $gate = WarpGate::where('uuid', $validated['gate_uuid'])
            ->where('source_poi_id', $player->current_poi_id)
            ->first();

        if (! $gate) {
            return $this->error('Warp gate not found at current location', 'GATE_NOT_FOUND');
        }

        $result = $this->travelService->executeTravel($player, $gate);

        if (! $result['success']) {
            return $this->error($result['message'], $result['code'] ?? 'TRAVEL_FAILED');
        }

        // Get the destination POI
        $destination = $player->fresh()->currentLocation;

        return $this->success([
            'fuel_consumed' => $result['fuel_cost'],
            'xp_earned' => $result['xp_earned'],
            'new_location' => new PointOfInterestResource($destination),
            'level_up' => $result['leveled_up'] ?? false,
            'new_level' => $result['new_level'],
            'pirate_encounter' => $result['pirate_encounter'] ?? null,
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

        $player = Player::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with(['activeShip', 'currentLocation'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        if (! $player->activeShip) {
            return $this->error('No active ship', 'NO_ACTIVE_SHIP');
        }

        $result = $this->travelService->executeDirectJump(
            $player,
            (int) $validated['target_x'],
            (int) $validated['target_y']
        );

        if (! $result['success']) {
            return $this->error($result['message'], $result['code'] ?? 'JUMP_FAILED');
        }

        // Get the destination POI
        $destination = $player->fresh()->currentLocation;

        return $this->success([
            'fuel_consumed' => $result['fuel_cost'],
            'xp_earned' => $result['xp_earned'],
            'new_location' => new PointOfInterestResource($destination),
            'level_up' => $result['leveled_up'] ?? false,
            'new_level' => $result['new_level'],
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

        $targetPoi = PointOfInterest::where('uuid', $validated['target_poi_uuid'])->first();

        if (! $targetPoi) {
            return $this->notFound('Target location not found');
        }

        // Reuse coordinate jump logic with POI coordinates
        $request->merge(['target_x' => $targetPoi->x, 'target_y' => $targetPoi->y]);

        return $this->jumpToCoordinates($request, $uuid);
    }

}

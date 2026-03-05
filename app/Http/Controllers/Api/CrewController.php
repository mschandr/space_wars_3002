<?php

namespace App\Http\Controllers\Api;

use App\Models\CrewMember;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CrewController extends BaseApiController
{
    /**
     * Get available crew at a POI
     *
     * GET /api/galaxies/{galaxyUuid}/crew/available?poi_uuid=xxx
     */
    public function getAvailableCrew(Request $request, string $galaxyUuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'poi_uuid' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        // Resolve galaxy
        $galaxy = Galaxy::where('uuid', $galaxyUuid)->first();
        if (!$galaxy) {
            return $this->notFound('Galaxy not found');
        }

        // Find POI and verify it belongs to this galaxy
        $poi = PointOfInterest::where('uuid', $validated['poi_uuid'])
            ->where('galaxy_id', $galaxy->id)
            ->first();
        if (!$poi) {
            return $this->notFound('Location not found');
        }

        // Get available crew at this POI (not assigned to any ship)
        $crew = CrewMember::where('current_poi_id', $poi->id)
            ->whereNull('player_ship_id')
            ->get();

        return $this->success([
            'available_crew' => $crew->map(fn (CrewMember $member) => [
                'uuid' => $member->uuid,
                'name' => $member->name,
                'role' => $member->role->value,
                'role_label' => $member->role->label(),
                'alignment' => $member->alignment->value,
                'alignment_label' => $member->alignment->label(),
                'reputation' => $member->reputation,
                'shady_actions' => $member->shady_actions,
                'traits' => $member->traits ?? [],
                'backstory' => $member->backstory,
            ]),
            'location' => [
                'uuid' => $poi->uuid,
                'name' => $poi->name,
            ],
        ]);
    }

    /**
     * Hire a crew member for a ship
     *
     * POST /api/ships/{shipUuid}/crew/hire
     * Body: { crew_uuid: string }
     */
    public function hireCrew(Request $request, string $shipUuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'crew_uuid' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        // Find ship
        $ship = PlayerShip::where('uuid', $shipUuid)
            ->where('player_id', $request->user()->player->id ?? null)
            ->first();

        if (!$ship) {
            return $this->notFound('Ship not found');
        }

        // Find crew
        $crew = CrewMember::where('uuid', $validated['crew_uuid'])
            ->where('player_ship_id', null) // Must be unassigned
            ->where('current_poi_id', $ship->current_poi_id) // Must be at same location as ship
            ->first();

        if (!$crew) {
            return $this->notFound('Crew member not available');
        }

        // Check max crew (5 slots per ship)
        if ($ship->crew()->count() >= 5) {
            return $this->error('Ship has maximum crew (5 members)', 409);
        }

        // Hire the crew member
        $crew->update(['player_ship_id' => $ship->id]);

        return $this->success([
            'message' => "Successfully hired {$crew->name}",
            'crew' => [
                'uuid' => $crew->uuid,
                'name' => $crew->name,
                'role' => $crew->role->value,
                'alignment' => $crew->alignment->value,
            ],
        ]);
    }

    /**
     * Dismiss a crew member from a ship
     *
     * POST /api/ships/{shipUuid}/crew/dismiss/{crewUuid}
     */
    public function dismissCrew(Request $request, string $shipUuid, string $crewUuid): JsonResponse
    {
        // Find ship
        $ship = PlayerShip::where('uuid', $shipUuid)
            ->where('player_id', $request->user()->player->id ?? null)
            ->first();

        if (!$ship) {
            return $this->notFound('Ship not found');
        }

        // Find crew
        $crew = CrewMember::where('uuid', $crewUuid)
            ->where('player_ship_id', $ship->id)
            ->first();

        if (!$crew) {
            return $this->notFound('Crew member not assigned to this ship');
        }

        // Dismiss: crew returns to their current POI
        $crewName = $crew->name;
        $crew->update(['player_ship_id' => null]);

        return $this->success([
            'message' => "Dismissed {$crewName}",
        ]);
    }

    /**
     * Transfer a crew member to another ship
     *
     * POST /api/ships/{shipUuid}/crew/transfer
     * Body: { crew_uuid: string, destination_ship_uuid: string }
     */
    public function transferCrew(Request $request, string $shipUuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'crew_uuid' => ['required', 'string'],
                'destination_ship_uuid' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $playerId = $request->user()->player->id ?? null;

        // Find source ship
        $sourceShip = PlayerShip::where('uuid', $shipUuid)
            ->where('player_id', $playerId)
            ->first();

        if (!$sourceShip) {
            return $this->notFound('Source ship not found');
        }

        // Find destination ship
        $destShip = PlayerShip::where('uuid', $validated['destination_ship_uuid'])
            ->where('player_id', $playerId)
            ->first();

        if (!$destShip) {
            return $this->notFound('Destination ship not found');
        }

        // Find crew
        $crew = CrewMember::where('uuid', $validated['crew_uuid'])
            ->where('player_ship_id', $sourceShip->id)
            ->first();

        if (!$crew) {
            return $this->notFound('Crew member not assigned to source ship');
        }

        // Check destination capacity
        if ($destShip->crew()->count() >= 5) {
            return $this->error('Destination ship has maximum crew (5 members)', 409);
        }

        // Transfer
        $crew->update(['player_ship_id' => $destShip->id]);

        return $this->success([
            'message' => "Transferred {$crew->name} to {$destShip->name}",
        ]);
    }
}

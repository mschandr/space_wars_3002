<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateFlotillaRequest;
use App\Http\Requests\AddShipToFlotillaRequest;
use App\Http\Requests\RemoveShipFromFlotillaRequest;
use App\Http\Requests\SetFlagshipRequest;
use App\Models\Flotilla;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Services\Flotilla\FlotillaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Flotilla Controller
 *
 * Manages fleet operations for players. Players can create flotillas (2-4 ships),
 * add/remove ships, change flagship designation, and dissolve formations.
 *
 * All endpoints require authentication and operate on the authenticated player's fleet.
 * A player can have only one active flotilla at a time.
 *
 * ## Business Rules
 * - Flotillas require minimum 1 ship (the flagship) and maximum 4 ships
 * - All ships must be at the same location to form/modify a flotilla
 * - Movement penalty increases with fleet size: 1.0x (1 ship), 1.1x (2), 1.2x (3), 1.3x (4)
 * - Fuel cost = base_cost × formation_penalty, all ships must have sufficient fuel
 * - Combat with flotilla grants 1.2x XP bonus per extra ship
 * - Flagship cannot be removed; must set a new flagship first
 *
 * ## Response Codes
 * - 201: Resource created successfully
 * - 200: Operation successful
 * - 403: Unauthorized (not fleet owner)
 * - 404: Fleet not found or ship not found
 * - 422: Validation failed or business rule violated
 */
class FlotillaController extends Controller
{
    public function __construct(
        private readonly FlotillaService $flotillaService,
    ) {}

    /**
     * Create a new flotilla with a flagship ship.
     *
     * Initializes a fleet formation with the specified ship as flagship.
     * Players can only have one active flotilla at a time. Flagship becomes
     * the formation leader and will be promoted to new flagship if destroyed
     * during combat (if other ships remain).
     *
     * **Endpoint:** `POST /api/players/{uuid}/flotilla`
     *
     * @param CreateFlotillaRequest $request Contains flagship_ship_id (required) and name (optional)
     * @param string $playerUuid The UUID of the player creating the flotilla
     *
     * @return JsonResponse {
     *   "message": "Flotilla created successfully",
     *   "flotilla": {
     *     "uuid": "...",
     *     "name": "Squadron Alpha",
     *     "flagship": {...},
     *     "ships": [...],
     *     "formation_stats": {...},
     *     "location": {...},
     *     "created_at": "2026-03-06T...",
     *     "updated_at": "2026-03-06T..."
     *   }
     * }
     *
     * @throws 201 Created - Flotilla created successfully
     * @throws 403 Unauthorized - Player is not the fleet owner
     * @throws 404 Not Found - Flagship ship not found
     * @throws 422 Unprocessable Entity - Validation failed or player already has a flotilla
     *
     * **Validation Rules:**
     * - flagship_ship_id: required|uuid|exists in player's ships
     * - name: optional|string|max:255
     *
     * **Use Cases:**
     * - Player wants to group ships for atomic movement
     * - Player wants combined-arms combat bonuses
     * - Player wants to organize ships under a tactical unit
     *
     * **Necessary:** ✅ YES - Core feature for fleet formation
     * **Useful:** ✅ YES - Provides movement efficiency and combat bonuses
     */
    public function createFlotilla(CreateFlotillaRequest $request, string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->firstOrFail();

        // Authorize player
        if (auth()->id() !== $player->id) {
            return response()->json(
                ['error' => 'Unauthorized'],
                403
            );
        }

        // Check player doesn't already have a flotilla
        if ($this->flotillaService->getPlayerFlotilla($player)) {
            return response()->json(
                ['error' => 'Player already has an active flotilla'],
                422
            );
        }

        // Get flagship ship
        $flagship = PlayerShip::where('uuid', $request->validated()['flagship_ship_id'])
            ->where('player_id', $player->id)
            ->firstOrFail();

        try {
            $flotilla = $this->flotillaService->createFlotilla(
                $player,
                $flagship,
                $request->validated()['name'] ?? null
            );

            return response()->json(
                [
                    'message' => 'Flotilla created successfully',
                    'flotilla' => $this->flotillaService->getFlotillaStatus($flotilla),
                ],
                201
            );
        } catch (\Exception $e) {
            return response()->json(
                ['error' => $e->getMessage()],
                422
            );
        }
    }

    /**
     * Get current flotilla status.
     *
     * Retrieves comprehensive fleet information including ship composition,
     * formation statistics, combat readiness, and location data. Essential
     * for UI to display fleet overview and manage tactical decisions.
     *
     * **Endpoint:** `GET /api/players/{uuid}/flotilla`
     *
     * @param string $playerUuid The UUID of the player
     *
     * @return JsonResponse {
     *   "uuid": "...",
     *   "name": "Squadron Alpha",
     *   "flagship": {
     *     "uuid": "...",
     *     "name": "Flagship",
     *     "hull": 100,
     *     "max_hull": 100,
     *     "current_fuel": 85,
     *     "max_fuel": 100
     *   },
     *   "ships": [...],
     *   "formation_stats": {
     *     "ship_count": 2,
     *     "is_full": false,
     *     "total_hull": 180,
     *     "weakest_ship_hull": 80,
     *     "slowest_warp_drive": 2,
     *     "total_cargo_hold": 1000,
     *     "available_cargo_space": 950,
     *     "total_weapon_damage": 12
     *   },
     *   "location": {
     *     "poi_id": 1,
     *     "poi_name": "Trading Hub Alpha",
     *     "at_same_location": true
     *   },
     *   "created_at": "2026-03-06T...",
     *   "updated_at": "2026-03-06T..."
     * }
     *
     * @throws 200 OK - Fleet data returned
     * @throws 403 Unauthorized - Player is not the fleet owner
     * @throws 404 Not Found - Player has no active flotilla
     *
     * **Use Cases:**
     * - Display fleet status in UI
     * - Check formation statistics before combat
     * - Verify fuel availability for movement
     * - Monitor ship health and cargo
     *
     * **Necessary:** ✅ YES - Core feature for fleet management
     * **Useful:** ✅ YES - Critical for tactical gameplay decisions
     */
    public function getFlotilla(string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->firstOrFail();

        // Authorize player
        if (auth()->id() !== $player->id) {
            return response()->json(
                ['error' => 'Unauthorized'],
                403
            );
        }

        $flotilla = $this->flotillaService->getPlayerFlotilla($player);

        if (!$flotilla) {
            return response()->json(
                ['message' => 'Player does not have an active flotilla'],
                404
            );
        }

        return response()->json(
            $this->flotillaService->getFlotillaStatus($flotilla),
            200
        );
    }

    /**
     * Add a ship to an existing flotilla.
     *
     * Recruits an additional ship into the fleet formation. Ships can be added
     * up to the maximum fleet size (4 ships). New ships must be at the same
     * location as the flagship and owned by the player.
     *
     * **Endpoint:** `POST /api/players/{uuid}/flotilla/add-ship`
     *
     * @param AddShipToFlotillaRequest $request Contains ship_id (required, uuid)
     * @param string $playerUuid The UUID of the player
     *
     * @return JsonResponse {
     *   "message": "Ship added to flotilla",
     *   "flotilla": {...}
     * }
     *
     * @throws 200 OK - Ship added successfully
     * @throws 403 Unauthorized - Player is not the fleet owner
     * @throws 404 Not Found - Flotilla not found or ship not found
     * @throws 422 Unprocessable Entity - Validation failed or business rule violated
     *
     * **Validation Rules:**
     * - ship_id: required|uuid|exists in player's ships
     *
     * **Business Rule Validations:**
     * - Flotilla not at maximum capacity (4 ships)
     * - Ship not already in another flotilla
     * - Ship at same location as flagship
     * - Ship owned by the player
     *
     * **Use Cases:**
     * - Expand fleet formation during gameplay
     * - Add reinforcements to existing flotilla
     * - Organize additional ships under tactical command
     *
     * **Necessary:** ✅ YES - Core feature for fleet expansion
     * **Useful:** ✅ YES - Allows tactical fleet composition changes
     */
    public function addShip(AddShipToFlotillaRequest $request, string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->firstOrFail();

        // Authorize player
        if (auth()->id() !== $player->id) {
            return response()->json(
                ['error' => 'Unauthorized'],
                403
            );
        }

        $flotilla = $this->flotillaService->getPlayerFlotilla($player);

        if (!$flotilla) {
            return response()->json(
                ['error' => 'Player does not have an active flotilla'],
                404
            );
        }

        // Get ship to add
        $ship = PlayerShip::where('uuid', $request->validated()['ship_id'])
            ->where('player_id', $player->id)
            ->firstOrFail();

        try {
            $this->flotillaService->addShipToFlotilla($flotilla, $ship);

            return response()->json(
                [
                    'message' => 'Ship added to flotilla',
                    'flotilla' => $this->flotillaService->getFlotillaStatus($flotilla->refresh()),
                ],
                200
            );
        } catch (\Exception $e) {
            return response()->json(
                ['error' => $e->getMessage()],
                422
            );
        }
    }

    /**
     * Remove a ship from the flotilla.
     *
     * Releases a ship from fleet formation, returning it to independent status.
     * The flagship cannot be removed; players must designate a new flagship
     * before removing the current one.
     *
     * **Endpoint:** `POST /api/players/{uuid}/flotilla/remove-ship`
     *
     * @param RemoveShipFromFlotillaRequest $request Contains ship_id (required, uuid)
     * @param string $playerUuid The UUID of the player
     *
     * @return JsonResponse {
     *   "message": "Ship removed from flotilla",
     *   "flotilla": {...}
     * }
     *
     * @throws 200 OK - Ship removed successfully
     * @throws 403 Unauthorized - Player is not the fleet owner
     * @throws 404 Not Found - Flotilla not found or ship not found
     * @throws 422 Unprocessable Entity - Cannot remove flagship or other validation failed
     *
     * **Validation Rules:**
     * - ship_id: required|uuid|in this player's fleet
     *
     * **Business Rule Validations:**
     * - Cannot remove the flagship (must set new flagship first)
     * - Ship must be in the fleet being operated on
     *
     * **Use Cases:**
     * - Reduce fleet size for tactical reasons
     * - Separate damaged ships from formation
     * - Reassign ships to solo operations
     * - Manage fleet composition during campaigns
     *
     * **Necessary:** ✅ YES - Core feature for fleet management flexibility
     * **Useful:** ✅ YES - Allows dynamic tactical adjustments
     */
    public function removeShip(RemoveShipFromFlotillaRequest $request, string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->firstOrFail();

        // Authorize player
        if (auth()->id() !== $player->id) {
            return response()->json(
                ['error' => 'Unauthorized'],
                403
            );
        }

        $flotilla = $this->flotillaService->getPlayerFlotilla($player);

        if (!$flotilla) {
            return response()->json(
                ['error' => 'Player does not have an active flotilla'],
                404
            );
        }

        // Get ship to remove
        $ship = PlayerShip::where('uuid', $request->validated()['ship_id'])
            ->where('player_id', $player->id)
            ->where('flotilla_id', $flotilla->id)
            ->firstOrFail();

        try {
            $this->flotillaService->removeShipFromFlotilla($flotilla, $ship);

            return response()->json(
                [
                    'message' => 'Ship removed from flotilla',
                    'flotilla' => $this->flotillaService->getFlotillaStatus($flotilla->refresh()),
                ],
                200
            );
        } catch (\Exception $e) {
            return response()->json(
                ['error' => $e->getMessage()],
                422
            );
        }
    }

    /**
     * Change the flagship of the flotilla.
     *
     * Reassigns fleet command to a different ship. The new flagship must be
     * currently part of the fleet. Flagship designation matters for:
     * - Combat: New flagship promoted if current one is destroyed
     * - Movement: Flotilla speed determined by slowest ship
     * - Location: Flagship location used for formation requirements
     *
     * **Endpoint:** `POST /api/players/{uuid}/flotilla/set-flagship`
     *
     * @param SetFlagshipRequest $request Contains ship_id (required, uuid)
     * @param string $playerUuid The UUID of the player
     *
     * @return JsonResponse {
     *   "message": "Flagship changed successfully",
     *   "flotilla": {...}
     * }
     *
     * @throws 200 OK - Flagship changed successfully
     * @throws 403 Unauthorized - Player is not the fleet owner
     * @throws 404 Not Found - Flotilla not found or ship not found
     * @throws 422 Unprocessable Entity - Ship not in this fleet or validation failed
     *
     * **Validation Rules:**
     * - ship_id: required|uuid|in this player's fleet
     *
     * **Business Rule Validations:**
     * - New flagship must be part of the fleet
     * - New flagship must be owned by the player
     *
     * **Use Cases:**
     * - Prepare for flagship destruction in combat (move command to tougher ship)
     * - Optimize fleet composition (move command to fastest ship)
     * - Rotate leadership among fleet ships
     * - Strategic restructuring of fleet hierarchy
     *
     * **Necessary:** ✅ YES - Core feature for tactical command structure
     * **Useful:** ✅ YES - Critical for combat preparation and strategy
     */
    public function setFlagship(SetFlagshipRequest $request, string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->firstOrFail();

        // Authorize player
        if (auth()->id() !== $player->id) {
            return response()->json(
                ['error' => 'Unauthorized'],
                403
            );
        }

        $flotilla = $this->flotillaService->getPlayerFlotilla($player);

        if (!$flotilla) {
            return response()->json(
                ['error' => 'Player does not have an active flotilla'],
                404
            );
        }

        // Get new flagship
        $newFlagship = PlayerShip::where('uuid', $request->validated()['ship_id'])
            ->where('player_id', $player->id)
            ->where('flotilla_id', $flotilla->id)
            ->firstOrFail();

        try {
            $this->flotillaService->setFlagship($flotilla, $newFlagship);

            return response()->json(
                [
                    'message' => 'Flagship changed successfully',
                    'flotilla' => $this->flotillaService->getFlotillaStatus($flotilla->refresh()),
                ],
                200
            );
        } catch (\Exception $e) {
            return response()->json(
                ['error' => $e->getMessage()],
                422
            );
        }
    }

    /**
     * Dissolve the flotilla.
     *
     * Disbands the fleet formation, releasing all ships to independent status.
     * Ships retain all cargo and status; only the formation structure is removed.
     * Atomic operation: either all ships are released or none are.
     *
     * **Endpoint:** `DELETE /api/players/{uuid}/flotilla`
     *
     * @param string $playerUuid The UUID of the player
     *
     * @return JsonResponse {
     *   "message": "Flotilla 'Squadron Alpha' dissolved. All ships are now independent."
     * }
     *
     * @throws 200 OK - Flotilla dissolved, all ships released
     * @throws 403 Unauthorized - Player is not the fleet owner
     * @throws 404 Not Found - Player has no active flotilla
     * @throws 422 Unprocessable Entity - Dissolution failed
     *
     * **Side Effects:**
     * - All ships in flotilla have flotilla_id set to null
     * - Ships regain independent movement (no formation penalty)
     * - Ships revert to solo combat mechanics (no formation bonuses)
     *
     * **Use Cases:**
     * - End fleet campaign
     * - Switch to solo ship operations
     * - Prepare for complete fleet reorganization
     * - Abandon fleet formation after combat
     *
     * **Necessary:** ✅ YES - Core feature for operational flexibility
     * **Useful:** ✅ YES - Essential for allowing players to abandon formations
     *
     * **Implementation Notes:**
     * - Uses atomic transaction to ensure data consistency
     * - Idempotent if no flotilla exists (returns 404)
     * - No validation needed beyond ownership check
     */
    public function dissolveFlotilla(string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->firstOrFail();

        // Authorize player
        if (auth()->id() !== $player->id) {
            return response()->json(
                ['error' => 'Unauthorized'],
                403
            );
        }

        $flotilla = $this->flotillaService->getPlayerFlotilla($player);

        if (!$flotilla) {
            return response()->json(
                ['error' => 'Player does not have an active flotilla'],
                404
            );
        }

        try {
            $flotillaName = $flotilla->name;
            $this->flotillaService->dissolveFlotilla($flotilla);

            return response()->json(
                [
                    'message' => "Flotilla '{$flotillaName}' dissolved. All ships are now independent.",
                ],
                200
            );
        } catch (\Exception $e) {
            return response()->json(
                ['error' => $e->getMessage()],
                422
            );
        }
    }
}

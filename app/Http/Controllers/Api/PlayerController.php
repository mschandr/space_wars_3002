<?php

namespace App\Http\Controllers\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Http\Resources\PlayerResource;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\PlayerShip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlayerController extends BaseApiController
{
    /**
     * List all players for the authenticated user
     *
     * GET /api/players
     */
    public function index(Request $request): JsonResponse
    {
        $players = $request->user()
            ->players()
            ->with(['galaxy', 'currentLocation', 'activeShip'])
            ->get();

        return $this->success(PlayerResource::collection($players));
    }

    /**
     * Create a new player in a galaxy
     *
     * POST /api/players
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'galaxy_id' => ['required', 'exists:galaxies,id'],
                'call_sign' => ['required', 'string', 'max:50'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        $galaxy = Galaxy::findOrFail($validated['galaxy_id']);

        // Check if call sign is unique within this galaxy
        $existingPlayer = Player::where('galaxy_id', $galaxy->id)
            ->where('call_sign', $validated['call_sign'])
            ->first();

        if ($existingPlayer) {
            return $this->error(
                'Call sign already exists in this galaxy',
                'DUPLICATE_CALL_SIGN',
                null,
                422
            );
        }

        DB::beginTransaction();
        try {
            // Find a random inhabited starting location
            $startingLocation = PointOfInterest::where('galaxy_id', $galaxy->id)
                ->where('type', PointOfInterestType::STAR)
                ->where('is_inhabited', true)
                ->inRandomOrder()
                ->first();

            if (! $startingLocation) {
                DB::rollBack();

                return $this->error(
                    'No suitable starting location found in galaxy',
                    'NO_STARTING_LOCATION'
                );
            }

            // Get starting credits from config
            $startingCredits = config('game_config.ships.starting_credits', 10000);

            // Create player
            $player = Player::create([
                'user_id' => $request->user()->id,
                'galaxy_id' => $galaxy->id,
                'call_sign' => $validated['call_sign'],
                'credits' => $startingCredits,
                'experience' => 0,
                'level' => 1,
                'current_poi_id' => $startingLocation->id,
                'status' => 'active',
            ]);

            // Give player a starting ship (Scout class)
            $scoutShip = Ship::where('class', 'scout')->first();
            if ($scoutShip) {
                PlayerShip::create([
                    'player_id' => $player->id,
                    'ship_id' => $scoutShip->id,
                    'name' => "{$player->call_sign}'s Scout",
                    'current_fuel' => $scoutShip->base_max_fuel ?? 100,
                    'max_fuel' => $scoutShip->base_max_fuel ?? 100,
                    'hull' => $scoutShip->base_hull ?? 100,
                    'max_hull' => $scoutShip->base_hull ?? 100,
                    'weapons' => $scoutShip->base_weapons ?? 10,
                    'cargo_hold' => $scoutShip->base_cargo ?? 100,
                    'sensors' => $scoutShip->base_sensors ?? 1,
                    'warp_drive' => $scoutShip->base_warp_drive ?? 1,
                    'is_active' => true,
                    'status' => 'operational',
                ]);
            }

            // TODO: Give player starter star charts (3 nearest systems)

            DB::commit();

            return $this->success(
                new PlayerResource($player->load(['galaxy', 'currentLocation', 'activeShip'])),
                'Player created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error(
                'Failed to create player: '.$e->getMessage(),
                'PLAYER_CREATION_FAILED'
            );
        }
    }

    /**
     * Get player details by UUID
     *
     * GET /api/players/{uuid}
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with(['galaxy', 'currentLocation', 'activeShip.ship'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        return $this->success(new PlayerResource($player));
    }

    /**
     * Update player details
     *
     * PATCH /api/players/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        try {
            $validated = $request->validate([
                'call_sign' => ['sometimes', 'string', 'max:50'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        // Check if call sign is unique within galaxy (if provided)
        if (isset($validated['call_sign'])) {
            $existingPlayer = Player::where('galaxy_id', $player->galaxy_id)
                ->where('call_sign', $validated['call_sign'])
                ->where('id', '!=', $player->id)
                ->first();

            if ($existingPlayer) {
                return $this->error(
                    'Call sign already exists in this galaxy',
                    'DUPLICATE_CALL_SIGN',
                    null,
                    422
                );
            }

            $player->call_sign = $validated['call_sign'];
            $player->save();
        }

        return $this->success(
            new PlayerResource($player->load(['galaxy', 'currentLocation', 'activeShip'])),
            'Player updated successfully'
        );
    }

    /**
     * Delete player
     *
     * DELETE /api/players/{uuid}
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $player->delete();

        return $this->success(null, 'Player deleted successfully');
    }

    /**
     * Set player as active for the user
     *
     * POST /api/players/{uuid}/set-active
     */
    public function setActive(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        // This is a simple implementation - you might want to add
        // an is_active field to the players table if you want to
        // track which player is currently active for a user

        return $this->success(
            new PlayerResource($player->load(['galaxy', 'currentLocation', 'activeShip'])),
            'Player set as active'
        );
    }
}

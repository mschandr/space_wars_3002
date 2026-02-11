<?php

namespace App\Http\Controllers\Api;

use App\Enums\WarpGate\GateType;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\WarpGate;
use App\Services\MirrorUniverseService;
use App\Services\TravelService;
use Illuminate\Http\JsonResponse;

class MirrorUniverseController extends BaseApiController
{
    public function __construct(
        private MirrorUniverseService $mirrorService,
        private TravelService $travelService
    ) {}

    /**
     * Check if player can access mirror universe
     */
    public function checkAccess(string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)
            ->with(['activeShip', 'galaxy'])
            ->firstOrFail();

        $ship = $player->activeShip;

        if (! $ship) {
            return $this->error('No active ship', 'NO_ACTIVE_SHIP', null, 400);
        }

        // Get mirror config
        $mirrorConfig = config('game_config.mirror_universe', []);
        $requiredSensorLevel = $mirrorConfig['required_sensor_level'] ?? 5;
        $cooldownHours = $mirrorConfig['cooldown_hours'] ?? 24;

        // Check sensor requirement
        $hasSufficientSensors = $ship->sensors >= $requiredSensorLevel;

        // Check cooldown
        $lastMirrorTravel = $player->last_mirror_travel_at ?? null;
        $cooldownRemaining = 0;
        $canTravel = true;

        if ($lastMirrorTravel) {
            $hoursSinceTravel = (int) round(abs(now()->diffInHours($lastMirrorTravel)));
            $cooldownRemaining = max(0, $cooldownHours - $hoursSinceTravel);
            $canTravel = $cooldownRemaining === 0;
        }

        // Get mirror gate location
        $mirrorGate = WarpGate::where('galaxy_id', $player->galaxy_id)
            ->where('gate_type', GateType::MIRROR_ENTRY)
            ->with('sourcePoi')
            ->first();

        return $this->success([
            'player' => [
                'uuid' => $player->uuid,
                'call_sign' => $player->call_sign,
            ],
            'access' => [
                'has_sufficient_sensors' => $hasSufficientSensors,
                'required_sensor_level' => $requiredSensorLevel,
                'current_sensor_level' => $ship->sensors,
                'can_travel' => $hasSufficientSensors && $canTravel,
                'cooldown_remaining_hours' => $cooldownRemaining,
                'next_available_at' => $lastMirrorTravel && $cooldownRemaining > 0
                    ? $lastMirrorTravel->addHours($cooldownHours)->toIso8601String()
                    : null,
            ],
            'mirror_gate' => $mirrorGate ? [
                'uuid' => $mirrorGate->uuid,
                'location' => [
                    'poi_uuid' => $mirrorGate->sourcePoi->uuid,
                    'name' => $mirrorGate->sourcePoi->name,
                    'x' => $mirrorGate->sourcePoi->x,
                    'y' => $mirrorGate->sourcePoi->y,
                ],
                'is_at_gate' => $player->current_poi_id === $mirrorGate->source_poi_id,
            ] : null,
            'mirror_modifiers' => $mirrorConfig,
        ], 'Mirror universe access information retrieved');
    }

    /**
     * Get mirror gate location in galaxy
     */
    public function getMirrorGate(string $galaxyUuid): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $galaxyUuid)->firstOrFail();

        $mirrorGate = WarpGate::where('galaxy_id', $galaxy->id)
            ->where('gate_type', GateType::MIRROR_ENTRY)
            ->with(['sourcePoi', 'destinationPoi'])
            ->first();

        if (! $mirrorGate) {
            return $this->error('No mirror gate exists in this galaxy', 'MIRROR_GATE_NOT_FOUND', null, 404);
        }

        $mirrorConfig = config('game_config.mirror_universe', []);

        return $this->success([
            'galaxy' => [
                'uuid' => $galaxy->uuid,
                'name' => $galaxy->name,
            ],
            'mirror_gate' => [
                'uuid' => $mirrorGate->uuid,
                'location' => [
                    'poi_uuid' => $mirrorGate->sourcePoi->uuid,
                    'name' => $mirrorGate->sourcePoi->name,
                    'coordinates' => [
                        'x' => $mirrorGate->sourcePoi->x,
                        'y' => $mirrorGate->sourcePoi->y,
                    ],
                ],
                'destination' => $mirrorGate->destinationPoi ? [
                    'poi_uuid' => $mirrorGate->destinationPoi->uuid,
                    'name' => $mirrorGate->destinationPoi->name,
                    'coordinates' => [
                        'x' => $mirrorGate->destinationPoi->x,
                        'y' => $mirrorGate->destinationPoi->y,
                    ],
                ] : null,
            ],
            'requirements' => [
                'sensor_level' => $mirrorConfig['required_sensor_level'] ?? 5,
                'cooldown_hours' => $mirrorConfig['cooldown_hours'] ?? 24,
            ],
            'warnings' => [
                'increased_pirate_difficulty' => true,
                'resource_rewards' => 'Doubled',
                'cannot_return_immediately' => true,
            ],
        ], 'Mirror gate location retrieved successfully');
    }

    /**
     * Enter mirror universe
     */
    public function enter(string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)
            ->with(['activeShip', 'galaxy'])
            ->firstOrFail();

        $ship = $player->activeShip;

        if (! $ship) {
            return $this->error('No active ship', 'NO_ACTIVE_SHIP', null, 400);
        }

        // Get mirror config
        $mirrorConfig = config('game_config.mirror_universe', []);
        $requiredSensorLevel = $mirrorConfig['required_sensor_level'] ?? 5;
        $cooldownHours = $mirrorConfig['cooldown_hours'] ?? 24;

        // Validate sensor requirement
        if ($ship->sensors < $requiredSensorLevel) {
            return $this->error(
                "Insufficient sensor level. Required: {$requiredSensorLevel}, Current: {$ship->sensors}",
                'INSUFFICIENT_SENSORS',
                null,
                400
            );
        }

        // Check cooldown
        $lastMirrorTravel = $player->last_mirror_travel_at ?? null;
        if ($lastMirrorTravel) {
            $hoursSinceTravel = (int) round(abs(now()->diffInHours($lastMirrorTravel)));
            if ($hoursSinceTravel < $cooldownHours) {
                $hoursRemaining = $cooldownHours - $hoursSinceTravel;

                return $this->error(
                    "Mirror universe cooldown active. Can travel again in {$hoursRemaining} hours",
                    'COOLDOWN_ACTIVE',
                    null,
                    400
                );
            }
        }

        // Find mirror gate
        $mirrorGate = WarpGate::where('galaxy_id', $player->galaxy_id)
            ->where('gate_type', GateType::MIRROR_ENTRY)
            ->firstOrFail();

        // Check if player is at mirror gate
        if ($player->current_poi_id !== $mirrorGate->source_poi_id) {
            return $this->error('You must be at the mirror gate to enter the mirror universe', 'NOT_AT_GATE', null, 400);
        }

        // Execute travel through mirror gate
        $travelResult = $this->travelService->executeTravel($player, $mirrorGate);

        // Update cooldown
        $player->update(['last_mirror_travel_at' => now()]);

        return $this->success([
            'travel_result' => $travelResult,
            'message' => 'Successfully entered the mirror universe',
            'warnings' => [
                'doubled_pirate_difficulty' => true,
                'doubled_resources' => true,
                'return_cooldown_active' => true,
                'next_available_return' => now()->addHours($cooldownHours)->toIso8601String(),
            ],
        ], 'Entered mirror universe successfully');
    }
}

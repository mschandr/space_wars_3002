<?php

namespace App\Services;

use App\Enums\PointsOfInterest\PointOfInterestStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\WarpGate;
use Illuminate\Support\Facades\Log;

/**
 * Travel Service
 *
 * Handles travel calculations and execution
 */
class TravelService
{
    /**
     * Calculate fuel cost for travel based on distance and ship warp drive
     *
     * Formula:
     * - Base cost: ceil(distance)
     * - Efficiency: 1 + ((warp_drive - 1) * 0.2) - 20% reduction per warp level
     * - Final cost: max(1, ceil(baseCost / efficiency))
     *
     * @param  float  $distance  The distance to travel
     * @param  PlayerShip  $ship  The ship performing the travel
     * @return int The fuel cost
     */
    public function calculateFuelCost(float $distance, PlayerShip $ship): int
    {
        // Base fuel cost uses actual Euclidean distance
        $baseCost = ceil($distance);

        // Warp drive reduces fuel consumption (20% reduction per level)
        $efficiency = 1 + (($ship->warp_drive ?? 1) - 1) * 0.2;
        $fuelCost = max(1, (int) ceil($baseCost / $efficiency));

        return $fuelCost;
    }

    /**
     * Calculate XP earned for travel
     *
     * Formula: max(10, distance * 5)
     * - 5 XP per unit distance
     * - Minimum 10 XP
     *
     * @param  float  $distance  The distance traveled
     * @return int The XP earned
     */
    public function calculateTravelXP(float $distance): int
    {
        return (int) max(10, $distance * 5);
    }

    /**
     * Execute travel through a warp gate
     *
     * @param  Player  $player  The player traveling
     * @param  WarpGate  $gate  The warp gate to travel through
     * @return array Result with success status, message, XP earned, and level info
     */
    public function executeTravel(Player $player, WarpGate $gate): array
    {
        $ship = $player->activeShip;

        if (! $ship) {
            return [
                'success' => false,
                'message' => 'No active ship',
                'xp_earned' => 0,
                'old_level' => $player->level,
                'new_level' => $player->level,
            ];
        }

        // Check if this is a mirror universe gate
        if ($gate->isMirrorGate()) {
            $mirrorService = app(MirrorUniverseService::class);
            $canTraverse = $mirrorService->canTraverseMirrorGate($player, $gate);

            if (! $canTraverse['can_traverse']) {
                return [
                    'success' => false,
                    'message' => $canTraverse['reason'],
                    'cooldown_until' => $canTraverse['cooldown_until'] ?? null,
                    'xp_earned' => 0,
                    'old_level' => $player->level,
                    'new_level' => $player->level,
                ];
            }
        }

        // Gates are bidirectional — resolve the other end based on player's current location
        $destination = $gate->source_poi_id === $player->current_poi_id
            ? $gate->destinationPoi
            : $gate->sourcePoi;
        $distance = $gate->distance ?? $gate->calculateDistance();
        $fuelCost = $this->calculateFuelCost($distance, $ship);

        // TODO: (Logical Error) Call $ship->regenerateFuel() before checking fuel to ensure
        // passive fuel regeneration is applied. Direct property access bypasses the regeneration
        // logic in PlayerShip::getCurrentFuel().
        if ($ship->current_fuel < $fuelCost) {
            return [
                'success' => false,
                'message' => 'Insufficient fuel',
                'required_fuel' => $fuelCost,
                'current_fuel' => $ship->current_fuel,
                'xp_earned' => 0,
                'old_level' => $player->level,
                'new_level' => $player->level,
            ];
        }

        // Consume fuel
        $ship->consumeFuel($fuelCost);

        // Update player and ship location
        $player->current_poi_id = $destination->id;
        $ship->current_poi_id = $destination->id;
        $ship->save();

        // Track last trading hub for respawn
        if ($destination->tradingHub && $destination->tradingHub->is_active) {
            $player->last_trading_hub_poi_id = $destination->id;
        }

        // Handle mirror universe entry/exit
        $message = 'Travel successful';
        $mirrorGate = false;
        if ($gate->gate_type === 'mirror_entry') {
            $player->enterMirrorUniverse();
            $message = '⚡ QUANTUM SHIFT DETECTED ⚡ You have entered the Mirror Universe!';
            $mirrorGate = true;
        } elseif ($gate->gate_type === 'mirror_return') {
            $player->exitMirrorUniverse();
            $message = '⚡ REALITY STABILIZING ⚡ You have returned to the Prime Universe';
            $mirrorGate = true;
        }

        $player->save();

        // Discover the warp lane traveled + all gates visible at destination
        $laneKnowledgeService = app(LaneKnowledgeService::class);
        $laneKnowledgeService->discoverLaneBidirectional($player, $gate, 'travel');
        $laneKnowledgeService->discoverAllGatesAtLocation($player, $destination->id, 'travel');

        // Update player knowledge (fog-of-war system)
        $knowledgeService = app(PlayerKnowledgeService::class);
        $knowledgeService->markVisited($player, $destination);

        // Award XP
        $xpEarned = $this->calculateTravelXP($distance);
        $oldLevel = $player->level;
        $player->addExperience($xpEarned);
        $newLevel = $player->level;

        // Check for magnetic mines at destination
        $mineResult = $this->checkMagneticMines($player, $destination);

        // Auto-scan destination if enabled
        $scanResult = null;
        if (config('game_config.scanning.auto_scan_on_arrival', true)) {
            $scanResult = $this->autoScanDestination($player, $destination);
        }

        return [
            'success' => true,
            'message' => $message,
            'destination' => $destination->name,
            'distance' => $distance,
            'fuel_cost' => $fuelCost,
            'fuel_remaining' => $ship->current_fuel,
            'xp_earned' => $xpEarned,
            'old_level' => $oldLevel,
            'new_level' => $newLevel,
            'leveled_up' => $newLevel > $oldLevel,
            'mirror_gate' => $mirrorGate,
            'universe' => $player->isInMirrorUniverse() ? 'mirror' : 'prime',
            'scan' => $scanResult,
            'mine_encounter' => $mineResult,
        ];
    }

    /**
     * Calculate maximum jump distance based on warp drive level
     *
     * @param  PlayerShip  $ship  The ship
     * @return float Maximum jump distance in coordinates
     */
    public function getMaxJumpDistance(PlayerShip $ship): float
    {
        $baseDistance = config('game_config.direct_travel.base_max_distance', 5.0);
        $distancePerLevel = config('game_config.direct_travel.distance_per_warp_level', 5.0);
        $warpLevel = $ship->warp_drive ?? 1;

        return $baseDistance + ($distancePerLevel * ($warpLevel - 1));
    }

    /**
     * Calculate fuel cost for direct jump (with penalty)
     *
     * @param  float  $distance  Distance to jump
     * @param  PlayerShip  $ship  The ship
     * @return int Fuel cost
     */
    public function calculateDirectJumpFuelCost(float $distance, PlayerShip $ship): int
    {
        // Direct jumps use reduced warp efficiency — gates are engineered for warp drives
        $efficiencyFactor = config('game_config.direct_travel.warp_efficiency_factor', 0.25);
        $warpLevel = $ship->warp_drive ?? 1;
        $directEfficiency = 1 + (($warpLevel - 1) * 0.2 * $efficiencyFactor);
        $baseCost = max(1, (int) ceil(ceil($distance) / $directEfficiency));

        $penalty = config('game_config.direct_travel.fuel_penalty_multiplier', 4.0);

        return (int) ceil($baseCost * $penalty);
    }

    /**
     * Check if a direct jump is possible
     *
     * @param  Player  $player  The player
     * @param  int  $targetX  Target X coordinate
     * @param  int  $targetY  Target Y coordinate
     * @return array Validation result
     */
    public function canDirectJump(Player $player, int $targetX, int $targetY): array
    {
        if (! config('game_config.direct_travel.enabled', true)) {
            return [
                'can_jump' => false,
                'reason' => 'Direct coordinate travel is disabled',
            ];
        }

        $ship = $player->activeShip;

        if (! $ship) {
            return [
                'can_jump' => false,
                'reason' => 'No active ship',
            ];
        }

        $currentPoi = $player->currentLocation;

        if (! $currentPoi) {
            return [
                'can_jump' => false,
                'reason' => 'Current location not found',
            ];
        }

        // Calculate distance
        $distance = sqrt(
            pow($targetX - $currentPoi->x, 2) +
            pow($targetY - $currentPoi->y, 2)
        );

        // Check max jump distance
        $maxDistance = $this->getMaxJumpDistance($ship);
        if ($distance > $maxDistance) {
            return [
                'can_jump' => false,
                'reason' => 'Distance exceeds maximum jump range',
                'distance' => round($distance, 2),
                'max_distance' => $maxDistance,
                'warp_level' => $ship->warp_drive ?? 1,
            ];
        }

        // Check galaxy bounds
        $galaxy = $currentPoi->galaxy;
        if ($targetX < 0 || $targetX > $galaxy->width ||
            $targetY < 0 || $targetY > $galaxy->height) {
            return [
                'can_jump' => false,
                'reason' => 'Coordinates outside galaxy bounds',
                'galaxy_bounds' => [
                    'width' => $galaxy->width,
                    'height' => $galaxy->height,
                ],
            ];
        }

        // Calculate fuel cost
        $fuelCost = $this->calculateDirectJumpFuelCost($distance, $ship);
        if ($ship->current_fuel < $fuelCost) {
            return [
                'can_jump' => false,
                'reason' => 'Insufficient fuel',
                'required_fuel' => $fuelCost,
                'current_fuel' => $ship->current_fuel,
                'distance' => round($distance, 2),
            ];
        }

        return [
            'can_jump' => true,
            'distance' => round($distance, 2),
            'fuel_cost' => $fuelCost,
        ];
    }

    /**
     * Execute a direct coordinate jump
     *
     * @param  Player  $player  The player
     * @param  int  $targetX  Target X coordinate
     * @param  int  $targetY  Target Y coordinate
     * @return array Result with success status, message, XP earned, and level info
     */
    public function executeDirectJump(Player $player, int $targetX, int $targetY): array
    {
        $check = $this->canDirectJump($player, $targetX, $targetY);

        if (! $check['can_jump']) {
            return [
                'success' => false,
                'message' => $check['reason'],
                'details' => $check,
                'xp_earned' => 0,
                'old_level' => $player->level,
                'new_level' => $player->level,
            ];
        }

        $ship = $player->activeShip;
        $currentPoi = $player->currentLocation;
        $distance = $check['distance'];
        $fuelCost = $check['fuel_cost'];

        // Consume fuel
        $ship->consumeFuel($fuelCost);

        // Find or create POI at target coordinates
        $targetPoi = $this->findOrCreateEmptySpace(
            $currentPoi->galaxy_id,
            $targetX,
            $targetY
        );

        // Update player and ship location
        $player->current_poi_id = $targetPoi->id;
        $ship->current_poi_id = $targetPoi->id;
        $ship->save();
        $player->save();

        // Update player knowledge (fog-of-war system)
        $knowledgeService = app(PlayerKnowledgeService::class);
        $knowledgeService->markVisited($player, $targetPoi);

        // Discover all gates visible at the destination
        $laneKnowledgeService = app(LaneKnowledgeService::class);
        $laneKnowledgeService->discoverAllGatesAtLocation($player, $targetPoi->id, 'travel');

        // Check for magnetic mines at destination
        $mineResult = $this->checkMagneticMines($player, $targetPoi);

        // Award reduced XP
        $baseXp = $this->calculateTravelXP($distance);
        $xpMultiplier = config('game_config.direct_travel.xp_multiplier', 0.75);
        $xpEarned = (int) ($baseXp * $xpMultiplier);

        $oldLevel = $player->level;
        $player->addExperience($xpEarned);
        $newLevel = $player->level;

        // Auto-scan destination if enabled
        $scanResult = null;
        if (config('game_config.scanning.auto_scan_on_arrival', true)) {
            $scanResult = $this->autoScanDestination($player, $targetPoi);
        }

        return [
            'success' => true,
            'message' => 'Direct jump successful',
            'destination' => $targetPoi->name,
            'destination_coords' => [$targetX, $targetY],
            'distance' => $distance,
            'fuel_cost' => $fuelCost,
            'fuel_remaining' => $ship->current_fuel,
            'xp_earned' => $xpEarned,
            'old_level' => $oldLevel,
            'new_level' => $newLevel,
            'leveled_up' => $newLevel > $oldLevel,
            'jump_type' => 'direct',
            'scan' => $scanResult,
            'mine_encounter' => $mineResult,
        ];
    }

    /**
     * Find POI at coordinates or create empty space marker
     *
     * @param  int  $galaxyId  Galaxy ID
     * @param  int  $x  X coordinate
     * @param  int  $y  Y coordinate
     * @return PointOfInterest The POI at those coordinates
     */
    private function findOrCreateEmptySpace(int $galaxyId, int $x, int $y): PointOfInterest
    {
        // TODO: (Race Condition) Use firstOrCreate() inside a DB::transaction() to prevent duplicate
        // POIs when two players jump to the same empty coordinates simultaneously. The current
        // find-then-create pattern has a TOCTOU (time-of-check-time-of-use) vulnerability.
        $existing = PointOfInterest::where('galaxy_id', $galaxyId)
            ->where('x', $x)
            ->where('y', $y)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Create "Empty Space" POI
        return PointOfInterest::create([
            'galaxy_id' => $galaxyId,
            'type' => PointOfInterestType::EMPTY_SPACE,
            'status' => PointOfInterestStatus::ACTIVE,
            'x' => $x,
            'y' => $y,
            'name' => "Empty Space ({$x}, {$y})",
            'is_inhabited' => false,
            'is_hidden' => false,
            'attributes' => [],
        ]);
    }

    /**
     * Check for magnetic mines at destination.
     */
    private function checkMagneticMines(Player $player, PointOfInterest $destination): ?array
    {
        try {
            $orbitalService = app(OrbitalStructureService::class);

            return $orbitalService->checkMagneticMines($player, $destination);
        } catch (\Throwable $e) {
            Log::warning('Magnetic mine check failed', [
                'player_id' => $player->id,
                'destination_id' => $destination->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Auto-scan destination on arrival
     *
     * @param  Player  $player  The player
     * @param  PointOfInterest  $destination  The destination POI
     * @return array|null Scan result or null if scan failed
     */
    private function autoScanDestination(Player $player, PointOfInterest $destination): ?array
    {
        try {
            // Populate system details on first access (lazy generation)
            $populationService = app(SystemPopulationService::class);
            $populationService->ensurePopulated($destination);

            $scanService = app(SystemScanService::class);
            $result = $scanService->scanSystem($player, $destination);

            if ($result['success']) {
                return [
                    'scan_level' => $result['scan_level'],
                    'cached' => $result['cached'] ?? false,
                    'new_discoveries' => $result['new_discoveries'] ?? [],
                    'can_reveal_more' => $result['can_reveal_more'] ?? false,
                ];
            }
        } catch (\Throwable $e) {
            // Log but don't fail the travel
            \Log::warning('Auto-scan failed on arrival', [
                'player_id' => $player->id,
                'destination_id' => $destination->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}

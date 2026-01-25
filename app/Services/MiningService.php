<?php

namespace App\Services;

use App\Enums\Galaxy\RegionType;
use App\Models\Colony;
use App\Models\ColonyBuilding;
use App\Models\Mineral;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;

class MiningService
{
    /**
         * Determine whether a player is permitted to mine at a given point of interest.
         *
         * Core-region POIs are mineable if unowned or owned by the player; outer-region POIs are always mineable.
         *
         * @param Player $player The player attempting to mine.
         * @param PointOfInterest $poi The point of interest to check.
         * @return bool `true` if the player may mine at the POI, `false` otherwise.
         */
    public function canMineAt(Player $player, PointOfInterest $poi): bool
    {
        // Outer region is always mineable
        if ($poi->region === RegionType::OUTER) {
            return true;
        }

        // Core region with no owner is mineable
        if ($poi->owner_id === null) {
            return true;
        }

        // Core region requires ownership
        return $poi->owner_id === $player->id;
    }

    /**
     * Provide a user-facing message explaining why the player cannot mine at the point of interest, or null if mining is permitted.
     *
     * @param Player $player The player attempting to mine.
     * @param PointOfInterest $poi The point of interest being evaluated.
     * @return string|null A restriction message when mining is not allowed, or `null` if the player may mine at the POI.
     */
    public function getMiningRestrictionMessage(Player $player, PointOfInterest $poi): ?string
    {
        if ($this->canMineAt($player, $poi)) {
            return null;
        }

        if ($poi->region === RegionType::CORE) {
            if ($poi->owner_id !== null && $poi->owner_id !== $player->id) {
                return 'This core system is owned by another player. Mining rights are restricted.';
            }
        }

        return 'You do not have permission to mine at this location.';
    }

    /**
     * Calculate mining efficiency based on ship sensor level
     * Formula: min(2.0, 0.1 * (1.18^sensorLevel))
     *
     * Sensor 1: 11.8% efficiency
     * Sensor 6: 62.3% efficiency
     * Sensor 7: 73.5% efficiency
     * Sensor 16: 199.6% efficiency (caps at 200%)
     */
    public function calculateSensorEfficiency(int $sensorLevel): float
    {
        $efficiency = 0.1 * pow(1.18, $sensorLevel);

        return min(2.0, $efficiency); // Cap at 200%
    }

    /**
     * Extract resources from a POI using orbital mining facility
     */
    public function extractResources(
        ColonyBuilding $miningFacility,
        PlayerShip $ship,
        PointOfInterest $poi,
        Mineral $mineral,
        int $depositSize
    ): array {
        // Validate this is an orbital mining facility
        if ($miningFacility->building_type !== 'orbital_mining') {
            return [
                'success' => false,
                'message' => 'Building is not an orbital mining facility',
            ];
        }

        // Validate the POI is suitable for mining
        if (! $this->canMineFromPOI($poi, $mineral)) {
            return [
                'success' => false,
                'message' => "Cannot mine {$mineral->name} from {$poi->type}",
            ];
        }

        // Calculate extraction efficiency based on ship sensors
        $efficiency = $this->calculateSensorEfficiency($ship->sensors);

        // Calculate actual amount extracted
        $extractedAmount = (int) ($depositSize * $efficiency);

        // Apply facility level bonus (orbital mining facility level)
        $facilityBonus = 1 + (($miningFacility->level - 1) * 0.1); // 10% per level
        $extractedAmount = (int) ($extractedAmount * $facilityBonus);

        return [
            'success' => true,
            'amount_extracted' => $extractedAmount,
            'efficiency_percent' => round($efficiency * 100, 1),
            'sensor_level' => $ship->sensors,
            'facility_level' => $miningFacility->level,
            'deposit_size' => $depositSize,
            'remaining' => $depositSize - $extractedAmount,
        ];
    }

    /**
     * Check if a POI can be mined for a specific mineral
     */
    public function canMineFromPOI(PointOfInterest $poi, Mineral $mineral): bool
    {
        $mineralAttributes = $mineral->attributes ?? [];
        $foundIn = $mineralAttributes['found_in'] ?? [];

        // Check if this mineral can be found in this POI type
        if (! empty($foundIn)) {
            return in_array($poi->planet_class, $foundIn) || in_array($poi->type, $foundIn);
        }

        return false;
    }

    /**
     * Get available minerals at a POI
     */
    public function getAvailableMinerals(PointOfInterest $poi): array
    {
        $deposits = $poi->mineral_deposits ?? [];
        $available = [];

        foreach ($deposits as $mineralName => $depositInfo) {
            $mineral = Mineral::where('name', $mineralName)->first();
            if ($mineral) {
                $available[] = [
                    'mineral' => $mineral,
                    'deposit_size' => $depositInfo['size'] ?? 0,
                    'richness' => $depositInfo['richness'] ?? 'trace',
                ];
            }
        }

        return $available;
    }

    /**
     * Check if a colony has orbital mining capability
     */
    public function hasOrbitalMining(Colony $colony): bool
    {
        return $colony->buildings()
            ->where('building_type', 'orbital_mining')
            ->where('status', 'operational')
            ->exists();
    }

    /**
     * Get sensor efficiency explanation for UI
     */
    public function getEfficiencyExplanation(int $sensorLevel): string
    {
        $efficiency = $this->calculateSensorEfficiency($sensorLevel);
        $percent = round($efficiency * 100, 1);

        $explanation = "Sensor Level {$sensorLevel}: {$percent}% extraction efficiency\n";

        // Show what next level would give
        if ($sensorLevel < 20) {
            $nextEfficiency = $this->calculateSensorEfficiency($sensorLevel + 1);
            $nextPercent = round($nextEfficiency * 100, 1);
            $improvement = $nextPercent - $percent;
            $explanation .= 'Upgrade to Level '.($sensorLevel + 1).": {$nextPercent}% (+{$improvement}%)";
        }

        return $explanation;
    }

    /**
     * Calculate Quantium extraction from ice giant
     */
    public function extractQuantium(
        ColonyBuilding $miningFacility,
        PlayerShip $ship,
        PointOfInterest $iceGiant,
        int $depositSize = 5000
    ): array {
        // Get Quantium mineral
        $quantium = Mineral::where('name', 'Quantium')->first();

        if (! $quantium) {
            return [
                'success' => false,
                'message' => 'Quantium mineral not found in database',
            ];
        }

        // Validate it's an ice giant
        if ($iceGiant->planet_class !== 'ice_giant') {
            return [
                'success' => false,
                'message' => 'Quantium can only be extracted from ice giants',
            ];
        }

        return $this->extractResources(
            $miningFacility,
            $ship,
            $iceGiant,
            $quantium,
            $depositSize
        );
    }

    /**
     * Start automated mining operation
     */
    public function startAutomatedMining(
        Colony $colony,
        PointOfInterest $poi,
        Mineral $mineral
    ): array {
        // Check if colony has orbital mining
        $miningFacility = $colony->buildings()
            ->where('building_type', 'orbital_mining')
            ->where('status', 'operational')
            ->first();

        if (! $miningFacility) {
            return [
                'success' => false,
                'message' => 'No operational orbital mining facility',
            ];
        }

        // Get player's best ship with sensors (for efficiency calculation)
        $bestShip = $colony->player->ships()
            ->where('is_active', true)
            ->orderBy('sensors', 'desc')
            ->first();

        if (! $bestShip) {
            return [
                'success' => false,
                'message' => 'No ship available for sensor operations',
            ];
        }

        // Calculate production rate (per cycle)
        $efficiency = $this->calculateSensorEfficiency($bestShip->sensors);
        $baseProduction = $miningFacility->effects['mineral_production'] ?? 100;
        $productionPerCycle = (int) ($baseProduction * $efficiency);

        return [
            'success' => true,
            'production_per_cycle' => $productionPerCycle,
            'sensor_efficiency' => round($efficiency * 100, 1),
            'mineral' => $mineral->name,
        ];
    }
}
<?php

namespace App\Services;

use App\Enums\OrbitalStructureType;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\OrbitalStructure;
use App\Models\Player;
use App\Models\PointOfInterest;

class OrbitalStructureService
{
    /**
     * Build a new orbital structure at a planet/moon.
     */
    public function buildStructure(Player $player, PointOfInterest $poi, string $type): array
    {
        $structureType = OrbitalStructureType::tryFrom($type);
        if (! $structureType) {
            return ['success' => false, 'message' => 'Invalid structure type'];
        }

        // Must be a planet or moon
        if (! $this->isValidBody($poi)) {
            return ['success' => false, 'message' => 'Structures can only be built at planets or moons'];
        }

        // Player must be in the same star system
        $playerStar = $player->currentLocation?->getRootStar();
        $poiStar = $poi->getRootStar();
        if (! $playerStar || ! $poiStar || $playerStar->id !== $poiStar->id) {
            return ['success' => false, 'message' => 'You must be in the same star system'];
        }

        // Check per-body limit
        $existing = OrbitalStructure::where('poi_id', $poi->id)
            ->where('structure_type', $type)
            ->whereNotIn('status', ['destroyed'])
            ->count();

        if ($existing >= $structureType->maxPerBody()) {
            return ['success' => false, 'message' => "Maximum {$structureType->maxPerBody()} {$structureType->label()} per body reached"];
        }

        // Check costs
        $costs = $this->getScaledCost($structureType, 1);
        if ($player->credits < $costs['credits']) {
            return ['success' => false, 'message' => 'Insufficient credits'];
        }

        // Deduct credits
        $player->deductCredits($costs['credits']);

        $structure = OrbitalStructure::create([
            'poi_id' => $poi->id,
            'player_id' => $player->id,
            'structure_type' => $structureType,
            'level' => 1,
            'status' => 'constructing',
            'name' => $structureType->label(),
            'construction_progress' => 0,
            'construction_started_at' => now(),
            'health' => $structureType->baseHealth(),
            'max_health' => $structureType->baseHealth(),
            'attributes' => $structureType->effects(),
            'credits_per_cycle' => 0,
            'minerals_per_cycle' => 0,
        ]);

        return [
            'success' => true,
            'message' => "Construction of {$structureType->label()} has begun",
            'structure' => $structure,
            'cost' => $costs,
        ];
    }

    /**
     * Advance construction by one cycle (+10%).
     */
    public function advanceConstruction(OrbitalStructure $structure): array
    {
        if ($structure->status !== 'constructing') {
            return ['success' => false, 'message' => 'Structure is not under construction'];
        }

        $rate = config('game_config.orbital_structures.construction_rate', 10);
        $structure->advanceConstruction($rate);

        return [
            'success' => true,
            'progress' => $structure->construction_progress,
            'completed' => $structure->status === 'operational',
        ];
    }

    /**
     * Get all structures at a body.
     */
    public function getStructuresAtBody(PointOfInterest $poi): \Illuminate\Database\Eloquent\Collection
    {
        return OrbitalStructure::where('poi_id', $poi->id)
            ->where('status', '!=', 'destroyed')
            ->get();
    }

    /**
     * Get all structures owned by a player.
     */
    public function getPlayerStructures(Player $player): \Illuminate\Database\Eloquent\Collection
    {
        return OrbitalStructure::where('player_id', $player->id)
            ->where('status', '!=', 'destroyed')
            ->get();
    }

    /**
     * Process mining extraction for a mining platform.
     */
    public function processMiningExtraction(OrbitalStructure $structure): array
    {
        if ($structure->structure_type !== OrbitalStructureType::MINING_PLATFORM || ! $structure->isOperational()) {
            return ['success' => false, 'extracted' => 0];
        }

        $extracted = $structure->calculateExtraction();

        return [
            'success' => true,
            'extracted' => $extracted,
        ];
    }

    /**
     * Check for magnetic mines when a hostile player arrives at a body.
     * Owner and allies are exempt from mine targeting.
     */
    public function checkMagneticMines(Player $player, PointOfInterest $destination): array
    {
        // Get active mines at this body and all bodies in the system
        $mines = OrbitalStructure::where('poi_id', $destination->id)
            ->where('structure_type', OrbitalStructureType::MAGNETIC_MINE)
            ->where('status', 'operational')
            ->get();

        if ($mines->isEmpty()) {
            return ['mines_present' => false, 'damage' => 0, 'mines_detonated' => 0];
        }

        // Owner exemption
        $mineOwnerIds = $mines->pluck('player_id')->unique();
        if ($mineOwnerIds->contains($player->id)) {
            return ['mines_present' => true, 'damage' => 0, 'mines_detonated' => 0, 'friendly' => true];
        }

        $ship = $player->activeShip;
        if (! $ship) {
            return ['mines_present' => true, 'damage' => 0, 'mines_detonated' => 0];
        }

        // Detection: base 30% + 10% per sensor level
        $sensorLevel = $ship->sensors ?? 1;
        $detectionChance = min(0.90, config('game_config.orbital_structures.mine_detection_base', 0.30)
            + ($sensorLevel * config('game_config.orbital_structures.mine_detection_per_sensor', 0.10)));

        $totalDamage = 0;
        $minesDetonated = 0;
        $minesEvaded = 0;

        foreach ($mines as $mine) {
            $roll = random_int(1, 100) / 100;

            if ($roll < $detectionChance) {
                // Mine detected and evaded
                $minesEvaded++;

                continue;
            }

            // Mine attaches and detonates - explosive decompression
            $mineDamage = $mine->structure_type->effects()['mine_damage'];
            $levelMultiplier = 1 + (($mine->level - 1) * 0.3);
            $damage = (int) ($mineDamage * $levelMultiplier);
            $totalDamage += $damage;
            $minesDetonated++;

            // Mine is consumed on detonation
            $mine->update(['status' => 'destroyed', 'health' => 0]);
        }

        // Apply damage to ship
        if ($totalDamage > 0 && $ship) {
            $ship->hull = max(0, $ship->hull - $totalDamage);
            $ship->save();
        }

        return [
            'mines_present' => true,
            'damage' => $totalDamage,
            'mines_detonated' => $minesDetonated,
            'mines_evaded' => $minesEvaded,
            'ship_destroyed' => $ship && $ship->hull <= 0,
        ];
    }

    /**
     * Calculate total orbital defense damage output at a POI.
     */
    public function calculateOrbitalDefenseDamage(PointOfInterest $poi): int
    {
        return OrbitalStructure::where('poi_id', $poi->id)
            ->where('structure_type', OrbitalStructureType::ORBITAL_DEFENSE)
            ->where('status', 'operational')
            ->get()
            ->sum(fn (OrbitalStructure $s) => $s->calculateDamage());
    }

    /**
     * Upgrade a structure to the next level.
     */
    public function upgradeStructure(Player $player, OrbitalStructure $structure): array
    {
        if ($structure->player_id !== $player->id) {
            return ['success' => false, 'message' => 'You do not own this structure'];
        }

        if ($structure->status !== 'operational') {
            return ['success' => false, 'message' => 'Structure must be operational to upgrade'];
        }

        if ($structure->level >= 5) {
            return ['success' => false, 'message' => 'Structure is at maximum level'];
        }

        $newLevel = $structure->level + 1;
        $costs = $this->getScaledCost($structure->structure_type, $newLevel);

        if ($player->credits < $costs['credits']) {
            return ['success' => false, 'message' => 'Insufficient credits'];
        }

        $player->deductCredits($costs['credits']);

        $structure->level = $newLevel;
        $structure->status = 'constructing';
        $structure->construction_progress = 0;
        $structure->construction_started_at = now();
        $structure->construction_completed_at = null;

        // Scale health with level
        $baseHealth = $structure->structure_type->baseHealth();
        $healthMultiplier = 1 + (($newLevel - 1) * 0.3);
        $structure->max_health = (int) ($baseHealth * $healthMultiplier);
        $structure->health = $structure->max_health;

        $structure->save();

        return [
            'success' => true,
            'message' => "Upgrading {$structure->name} to level {$newLevel}",
            'new_level' => $newLevel,
            'cost' => $costs,
        ];
    }

    /**
     * Demolish/scuttle a structure.
     */
    public function demolishStructure(Player $player, OrbitalStructure $structure): array
    {
        if ($structure->player_id !== $player->id) {
            return ['success' => false, 'message' => 'You do not own this structure'];
        }

        $name = $structure->name;
        $structure->update(['status' => 'destroyed', 'health' => 0]);

        return [
            'success' => true,
            'message' => "{$name} has been scuttled",
        ];
    }

    /**
     * Get scaled cost for a structure type at a given level.
     */
    private function getScaledCost(OrbitalStructureType $type, int $level): array
    {
        $base = $type->baseCost();
        $multiplier = 1 + (($level - 1) * 0.5);

        return [
            'credits' => (int) ($base['credits'] * $multiplier),
            'minerals' => (int) ($base['minerals'] * $multiplier),
        ];
    }

    /**
     * Check if a POI is a valid body for orbital structures.
     */
    private function isValidBody(PointOfInterest $poi): bool
    {
        return in_array($poi->type, [
            PointOfInterestType::TERRESTRIAL,
            PointOfInterestType::SUPER_EARTH,
            PointOfInterestType::OCEAN,
            PointOfInterestType::LAVA,
            PointOfInterestType::ICE_GIANT,
            PointOfInterestType::GAS_GIANT,
            PointOfInterestType::HOT_JUPITER,
            PointOfInterestType::CHTHONIC,
            PointOfInterestType::DWARF_PLANET,
            PointOfInterestType::MOON,
            PointOfInterestType::PLANET,
        ]);
    }
}

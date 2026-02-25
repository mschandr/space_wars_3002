<?php

namespace App\Services;

use App\Models\PirateBand;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\WarpGate;
use App\Models\WarpLanePirate;
use Illuminate\Support\Collection;

class PirateEncounterService
{
    public function __construct(
        private readonly PirateFleetGenerator $fleetGenerator,
        private readonly EscapeCalculationService $escapeService,
        private readonly CombatResolutionService $combatService,
        private readonly SurrenderService $surrenderService,
        private readonly SalvageService $salvageService,
        private readonly PlayerDeathService $deathService,
    ) {}

    /**
     * Check if a warp gate has active pirate presence (legacy lane-based)
     */
    public function hasPiratePresence(WarpGate $gate): bool
    {
        return $gate->warpLanePirate()->where('is_active', true)->exists();
    }

    /**
     * Check if a pirate band in this sector might intercept travel.
     *
     * Probability based on: number of active bands, distance from pirate position,
     * sector danger level, and player sensor level (better sensors = better avoidance).
     */
    public function checkSectorPirateEncounter(
        Player $player,
        PointOfInterest $destination,
        ?WarpGate $gate = null,
    ): ?PirateBand {
        $sectorId = $destination->sector_id;
        if (! $sectorId) {
            return null;
        }

        $bands = PirateBand::active()
            ->inSector($sectorId)
            ->with(['captain.faction', 'currentLocation'])
            ->get();

        if ($bands->isEmpty()) {
            return null;
        }

        $sensorLevel = $player->activeShip?->sensors ?? 1;
        $sectorDanger = $destination->sector?->danger_level ?? 1;

        foreach ($bands as $band) {
            $pirateLocation = $band->currentLocation;
            if (! $pirateLocation) {
                continue;
            }

            // Calculate distance from pirate to destination
            $distance = sqrt(
                pow($pirateLocation->x - $destination->x, 2) +
                pow($pirateLocation->y - $destination->y, 2)
            );

            $roamingRadius = $band->roaming_radius_ly ?? 50;

            // Skip if destination is outside pirate's patrol range
            if ($distance > $roamingRadius) {
                continue;
            }

            // Base encounter chance: 30% if right on top of pirate, drops with distance
            $proximityFactor = max(0, 1.0 - ($distance / $roamingRadius));
            $baseChance = 0.30 * $proximityFactor;

            // Danger level modifier: higher danger = more pirates
            $dangerModifier = 1.0 + ($sectorDanger * 0.1);

            // Sensor avoidance: better sensors = slightly better chance to avoid
            $sensorAvoidance = 1.0 - (($sensorLevel - 1) * 0.05);
            $sensorAvoidance = max(0.5, $sensorAvoidance); // Floor at 50%

            $finalChance = $baseChance * $dangerModifier * $sensorAvoidance;

            if ((mt_rand(1, 1000) / 1000) <= $finalChance) {
                return $band;
            }
        }

        return null;
    }

    /**
     * Get the pirate encounter for a warp gate (legacy lane-based)
     */
    public function getEncounter(WarpGate $gate): ?WarpLanePirate
    {
        return $gate->warpLanePirate()
            ->with(['captain.faction'])
            ->where('is_active', true)
            ->first();
    }

    /**
     * Generate the pirate fleet for an encounter
     */
    public function generateFleet(WarpLanePirate $encounter): Collection
    {
        return $this->fleetGenerator->generateFleet($encounter);
    }

    /**
     * Get encounter details for display
     */
    public function getEncounterDetails(WarpLanePirate $encounter, Collection $fleet): array
    {
        $captain = $encounter->captain;
        $faction = $captain->faction;

        // Check if encounter is in mirror universe
        $galaxy = $encounter->warpGate?->galaxy;
        $isMirrorUniverse = $galaxy && $galaxy->isMirrorUniverse();

        // Apply mirror universe difficulty boost
        $difficultyTier = $encounter->difficulty_tier;
        if ($isMirrorUniverse) {
            $difficultyTier = (int) round($galaxy->applyMirrorMultiplier($difficultyTier, 'pirate_difficulty'));
        }

        return [
            'captain_name' => $captain->getFullName(),
            'captain_title' => $captain->title,
            'faction_name' => $faction->getFullName(),
            'fleet_size' => $fleet->count(),
            'difficulty_tier' => $difficultyTier,
            'is_mirror_universe' => $isMirrorUniverse,
            'fleet' => $fleet->map(function ($ship) use ($galaxy, $isMirrorUniverse) {
                // Boost pirate ship stats in mirror universe
                $hullMultiplier = $isMirrorUniverse
                    ? $galaxy->applyMirrorMultiplier(1.0, 'pirate_difficulty')
                    : 1.0;
                $weaponsMultiplier = $hullMultiplier;

                return [
                    'name' => $ship->ship_name,
                    'class' => $ship->ship->class ?? 'Unknown',
                    'hull' => (int) ($ship->hull * $hullMultiplier),
                    'max_hull' => (int) ($ship->max_hull * $hullMultiplier),
                    'weapons' => (int) ($ship->weapons * $weaponsMultiplier),
                ];
            }),
        ];
    }

    /**
     * Attempt to escape from pirates
     */
    public function attemptEscape(PlayerShip $playerShip, Collection $pirateFleet): array
    {
        return $this->escapeService->attemptEscape($playerShip, $pirateFleet);
    }

    /**
     * Get escape analysis for display
     */
    public function getEscapeAnalysis(PlayerShip $playerShip, Collection $pirateFleet): array
    {
        return $this->escapeService->getEscapeAnalysis($playerShip, $pirateFleet);
    }

    /**
     * Process player surrender
     */
    public function processSurrender(Player $player, PlayerShip $playerShip, Collection $pirateFleet): array
    {
        $result = $this->surrenderService->processSurrender($player, $playerShip, $pirateFleet);

        // Generate message
        $captain = $pirateFleet->first()->captain ?? null;
        $captainName = $captain ? $captain->getFullName() : 'the pirates';
        $result['message'] = $this->surrenderService->generateSurrenderMessage($result, $captainName);

        return $result;
    }

    /**
     * Initiate combat with pirates
     */
    public function initiateCombat(Player $player, PlayerShip $playerShip, Collection $pirateFleet): array
    {
        // Resolve combat
        $combatResult = $this->combatService->resolveCombat($player, $playerShip, $pirateFleet);

        // If player won, collect salvage
        if ($combatResult['victory']) {
            $destroyedShips = $pirateFleet->where('hull', '<=', 0);
            $salvage = $this->salvageService->collectSalvage($destroyedShips);
            $combatResult['salvage'] = $this->salvageService->organizeSalvage($salvage);
        } else {
            // Player died - process death
            $deathResult = $this->deathService->processPlayerDeath($player, $playerShip);
            $combatResult['death'] = $deathResult;
            $combatResult['death_message'] = $this->deathService->generateDeathMessage($deathResult);
        }

        return $combatResult;
    }

    /**
     * Get combat preview
     */
    public function getCombatPreview(PlayerShip $playerShip, Collection $pirateFleet): array
    {
        return $this->combatService->getCombatPreview($playerShip, $pirateFleet);
    }

    /**
     * Transfer salvage to player
     */
    public function transferSalvage(
        Player $player,
        PlayerShip $playerShip,
        array $selectedMinerals = [],
        array $selectedPlanIds = []
    ): array {
        return $this->salvageService->transferSalvage($player, $playerShip, $selectedMinerals, $selectedPlanIds);
    }

    /**
     * Validate salvage selection
     */
    public function validateSalvageSelection(PlayerShip $playerShip, array $selectedMinerals): array
    {
        return $this->salvageService->validateSelection($playerShip, $selectedMinerals);
    }

    /**
     * Record that an encounter happened (for analytics)
     */
    public function recordEncounter(WarpLanePirate $encounter): void
    {
        $encounter->recordEncounter();
    }
}

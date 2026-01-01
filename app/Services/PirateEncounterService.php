<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerShip;
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
    ) {
    }

    /**
     * Check if a warp gate has active pirate presence
     */
    public function hasPiratePresence(WarpGate $gate): bool
    {
        return $gate->warpLanePirate()->where('is_active', true)->exists();
    }

    /**
     * Get the pirate encounter for a warp gate
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

        return [
            'captain_name' => $captain->getFullName(),
            'captain_title' => $captain->title,
            'faction_name' => $faction->getFullName(),
            'fleet_size' => $fleet->count(),
            'difficulty_tier' => $encounter->difficulty_tier,
            'fleet' => $fleet->map(function ($ship) {
                return [
                    'name' => $ship->ship_name,
                    'class' => $ship->ship->class ?? 'Unknown',
                    'hull' => $ship->hull,
                    'max_hull' => $ship->max_hull,
                    'weapons' => $ship->weapons,
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
        $combatResult = $this->combatService->resolveCombat($playerShip, $pirateFleet);

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

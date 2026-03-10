<?php

namespace App\Services\Combat;

use App\Models\Flotilla;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\WarpLanePirate;
use App\Services\Flotilla\FlotillaCombatService;
use Illuminate\Support\Facades\DB;

class CombatService
{
    public function __construct(
        private readonly FlotillaCombatService $flottillaCombatService,
    ) {}

    /**
     * Determine if a player is in a flotilla with active ships
     *
     * @param Player $player
     * @return Flotilla|null
     */
    public function getPlayerFlotilla(Player $player): ?Flotilla
    {
        $flotilla = $player->flotillas()->first();

        // Verify flotilla still has ships
        if ($flotilla && $flotilla->ships()->count() === 0) {
            return null;
        }

        return $flotilla;
    }

    /**
     * Check if a player can engage in combat with a flotilla
     * (Determines which combat system to use)
     *
     * @param Player $player
     * @return bool
     */
    public function canCombatAsFlotilla(Player $player): bool
    {
        $flotilla = $this->getPlayerFlotilla($player);

        if (!$flotilla) {
            return false;
        }

        // Flotilla must have at least 2 ships to use flotilla combat
        // (1 ship should use single-ship combat system)
        return $flotilla->ships()->count() >= 2;
    }

    /**
     * Get combat readiness information for a player
     * Shows whether they'll engage as single ship or flotilla
     *
     * @param Player $player
     * @return array
     */
    public function getCombatReadiness(Player $player): array
    {
        $flotilla = $this->getPlayerFlotilla($player);
        $activeShip = $player->activeShip;

        return [
            'has_flotilla' => $flotilla !== null,
            'flotilla_ship_count' => $flotilla?->ships()->count() ?? 0,
            'will_engage_as_flotilla' => $this->canCombatAsFlotilla($player),
            'active_ship' => [
                'id' => $activeShip?->id,
                'name' => $activeShip?->name,
                'hull' => $activeShip?->hull,
            ],
            'flotilla_details' => $flotilla ? [
                'name' => $flotilla->name,
                'ship_count' => $flotilla->ships()->count(),
                'total_hull' => $flotilla->totalHull(),
                'total_weapons' => $flotilla->ships()->sum('weapons'),
                'slowest_speed' => $flotilla->slowestShip()?->warp_drive,
            ] : null,
        ];
    }

    /**
     * Prepare combat preview for flotilla or single ship
     *
     * @param Player $player
     * @param WarpLanePirate $encounter
     * @return array Combat preview data
     */
    public function prepareCombatPreview(Player $player, WarpLanePirate $encounter): array
    {
        if ($this->canCombatAsFlotilla($player)) {
            return $this->preparFlotillaCombatPreview($player, $encounter);
        } else {
            return $this->prepareSingleShipCombatPreview($player, $encounter);
        }
    }

    /**
     * Prepare combat preview for single-ship combat
     * (Delegates to existing system via controller)
     *
     * @param Player $player
     * @param WarpLanePirate $encounter
     * @return array
     */
    private function prepareSingleShipCombatPreview(Player $player, WarpLanePirate $encounter): array
    {
        return [
            'combat_type' => 'single_ship',
            'ship' => [
                'id' => $player->activeShip->id,
                'name' => $player->activeShip->name,
                'hull' => $player->activeShip->hull,
                'weapons' => $player->activeShip->weapons,
            ],
            'pirate_difficulty' => $encounter->difficulty_level ?? 'medium',
        ];
    }

    /**
     * Prepare combat preview for flotilla combat
     *
     * @param Player $player
     * @param WarpLanePirate $encounter
     * @return array
     */
    private function preparFlotillaCombatPreview(Player $player, WarpLanePirate $encounter): array
    {
        $flotilla = $this->getPlayerFlotilla($player);

        if (!$flotilla) {
            throw new \Exception('Player does not have a valid flotilla');
        }

        $combatStats = $this->flottillaCombatService->getAggregateCombatStats($flotilla);

        return [
            'combat_type' => 'flotilla',
            'flotilla' => [
                'name' => $flotilla->name,
                'ship_count' => $flotilla->shipCount(),
                'total_hull' => $combatStats['total_hull'],
                'total_weapons' => $combatStats['total_weapons'],
                'formation_strength' => $combatStats['combat_efficiency'],
            ],
            'pirate_difficulty' => $encounter->difficulty_level ?? 'medium',
            'note' => 'All ships engage together. Slowest ship determines movement speed.',
        ];
    }

    /**
     * Calculate combat outcome for a flotilla
     * Used by controller to determine victory/defeat
     *
     * @param Player $player
     * @param WarpLanePirate $encounter
     * @param array $pirateFleet
     * @return array Combat result with victory flag and outcome details
     */
    public function resolveFlotillaCombat(
        Player $player,
        WarpLanePirate $encounter,
        array $pirateFleet
    ): array {
        return DB::transaction(function () use ($player, $encounter, $pirateFleet) {
            $flotilla = $this->getPlayerFlotilla($player);

            if (!$flotilla || !$this->canCombatAsFlotilla($player)) {
                throw new \Exception('Player cannot engage in flotilla combat');
            }

            // Simulate combat rounds
            $rounds = [];
            $playerDamageTotal = 0;
            $pirateDamageTotal = 0;
            $maxRounds = 20;
            $roundCount = 0;

            while ($roundCount < $maxRounds && $this->flottillaCombatService->isFlotillaCombatCapable($flotilla) && count($pirateFleet) > 0) {
                $roundCount++;

                // Player flotilla attacks (all ships fire together)
                $playerDamage = $this->flottillaCombatService->getTotalFlotillaWeaponDamage($flotilla);
                $playerDamageTotal += $playerDamage;

                // Eliminate pirate ships based on damage
                $remaining = [];
                $pirateDamageTaken = 0;

                foreach ($pirateFleet as $pirate) {
                    $pirateDamageTaken += random_int((int) ($playerDamage * 0.5), (int) ($playerDamage * 1.5));

                    if ($pirateDamageTaken < ($pirate['hull'] ?? 100)) {
                        $pirate['hull'] = ($pirate['hull'] ?? 100) - $pirateDamageTaken;
                        $remaining[] = $pirate;
                        $pirateDamageTaken = 0;
                    }
                }

                $pirateFleet = $remaining;

                if (count($pirateFleet) === 0) {
                    break; // Player victory
                }

                // Pirates attack (target weakest ship first)
                $targetShip = $this->flottillaCombatService->selectPirateFocusTarget($flotilla);

                if ($targetShip) {
                    $pirateDamage = 0;

                    foreach ($pirateFleet as $pirate) {
                        $pirateDamage += random_int(5, 20); // Per-pirate damage variance
                    }

                    $pirateDamageTotal += $pirateDamage;

                    // Apply damage to target ship
                    $damageResult = $this->flottillaCombatService->applyDamageToFlotilla(
                        $flotilla,
                        $pirateDamage
                    );

                    if (!$damageResult['ship_destroyed'] && !$this->flottillaCombatService->isFlotillaCombatCapable($flotilla)) {
                        break; // Flotilla destroyed
                    }
                }

                $rounds[] = [
                    'round' => $roundCount,
                    'player_damage_dealt' => $playerDamage,
                    'pirate_damage_taken' => $pirateDamage ?? 0,
                    'pirate_ships_remaining' => count($pirateFleet),
                    'player_ships_remaining' => $flotilla->ships()->where('hull', '>', 0)->count(),
                ];
            }

            // Determine victory or defeat
            $victory = count($pirateFleet) === 0 && $this->flottillaCombatService->isFlotillaCombatCapable($flotilla);

            $result = [
                'victory' => $victory,
                'combat_type' => 'flotilla',
                'rounds' => $rounds,
                'round_count' => $roundCount,
                'log' => $this->generateFlotillaCombatLog($flotilla, $rounds, $victory),
                'player_damage_dealt' => $playerDamageTotal,
                'pirate_damage_taken' => $pirateDamageTotal,
                'player_hull_remaining' => $flotilla->totalHull(),
                'xp_earned' => $victory ? $this->calculateFlotillaXP($flotilla, $rounds) : 0,
            ];

            if ($victory) {
                $result['salvage'] = [
                    'available_cargo' => 'See salvage options after battle',
                    'available_components' => 'See salvage options after battle',
                    'pirate_loot' => 'Random percentage of pirate cargo (30-70%)',
                ];
            } else {
                $result['death'] = [
                    'message' => 'Your flotilla was destroyed in combat',
                    'destroyed_ships' => $this->flottillaCombatService->getDestroyedShips($flotilla)->count(),
                ];
            }

            return $result;
        });
    }

    /**
     * Generate combat log for flotilla battle
     *
     * @param Flotilla $flotilla
     * @param array $rounds
     * @param bool $victory
     * @return array
     */
    private function generateFlotillaCombatLog(Flotilla $flotilla, array $rounds, bool $victory): array
    {
        $log = [
            "Flotilla '{$flotilla->name}' engaged in combat",
            "Ships in formation: " . $flotilla->shipCount(),
            "Total firepower: " . $this->flottillaCombatService->getTotalFlotillaWeaponDamage($flotilla) . " damage",
            '',
        ];

        foreach ($rounds as $round) {
            $log[] = "Round {$round['round']}: Player dealt {$round['player_damage_dealt']} damage. Pirates down to {$round['pirate_ships_remaining']} ships.";
        }

        if ($victory) {
            $log[] = '';
            $log[] = 'Victory! All pirate ships destroyed.';
        } else {
            $log[] = '';
            $log[] = 'Defeat! Flotilla destroyed in combat.';
        }

        return $log;
    }

    /**
     * Calculate XP earned for flotilla combat
     * (Multi-ship bonus: 1.2x per additional ship)
     *
     * @param Flotilla $flotilla
     * @param array $rounds
     * @return int
     */
    private function calculateFlotillaXP(Flotilla $flotilla, array $rounds): int
    {
        $baseXP = 100 * count($rounds); // 100 XP per round
        $shipBonus = 1 + (($flotilla->shipCount() - 1) * 0.2); // 1.2x per extra ship

        return (int) ($baseXP * $shipBonus);
    }

    /**
     * Handle escape attempt from flotilla combat
     *
     * @param Flotilla $flotilla
     * @param array $pirateFleet
     * @return array ['success' => bool, 'message' => string, ...]
     */
    public function attemptFlotillaEscape(Flotilla $flotilla, array $pirateFleet): array
    {
        // Escape chance based on slowest ship's speed
        $slowestWarp = $flotilla->slowestShip()?->warp_drive ?? 1;
        $averagePirateSpeed = array_sum(array_map(fn($p) => $p['speed'] ?? 2, $pirateFleet)) / count($pirateFleet);

        $speedAdvantage = $slowestWarp / $averagePirateSpeed;
        $baseEscapeChance = 0.4; // 40% base
        $escapeChance = min(0.9, $baseEscapeChance * $speedAdvantage); // Cap at 90%

        $success = random_int(0, 100) / 100 <= $escapeChance;

        return [
            'success' => $success,
            'escape_chance' => (int) ($escapeChance * 100),
            'message' => $success
                ? "Your flotilla jumped to hyperspace! Escaped from pirates."
                : "The slowest ship in your flotilla isn't fast enough! Pirates intercepted you.",
        ];
    }

    /**
     * Handle surrender in flotilla combat
     * (All ships surrender together, cargo lost from all ships)
     *
     * @param Player $player
     * @param Flotilla $flotilla
     * @return array Surrender result
     */
    public function handleFlotillaSurrender(Player $player, Flotilla $flotilla): array
    {
        $totalCargoLost = 0;

        foreach ($flotilla->ships as $ship) {
            // 70% of cargo lost to pirates
            $cargoLost = (int) ($ship->current_cargo * 0.7);
            $totalCargoLost += $cargoLost;

            if ($cargoLost > 0) {
                $ship->removeCargo($cargoLost);
            }
        }

        return [
            'surrendered' => true,
            'total_cargo_lost' => $totalCargoLost,
            'message' => "Your flotilla surrendered. Pirates took {$totalCargoLost} units of cargo from all ships.",
        ];
    }
}

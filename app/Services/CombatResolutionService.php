<?php

namespace App\Services;

use App\Models\PlayerShip;
use Illuminate\Support\Collection;

class CombatResolutionService
{
    /**
     * Resolve combat between player and pirate fleet
     *
     * Combat Flow:
     * 1. Player attacks first (targets weakest pirate)
     * 2. Each surviving pirate attacks player
     * 3. Repeat until one side is destroyed
     *
     * Damage = weapons ± 20% randomization
     *
     * @param PlayerShip $playerShip
     * @param Collection $pirateFleet Collection of PirateFleet models
     * @return array ['victory' => bool, 'log' => array, 'player_hull_remaining' => int]
     */
    public function resolveCombat(PlayerShip $playerShip, Collection $pirateFleet): array
    {
        $combatLog = [];
        $round = 1;

        $combatLog[] = [
            'type' => 'header',
            'message' => "⚔️  COMBAT INITIATED  ⚔️",
        ];
        $combatLog[] = [
            'type' => 'info',
            'message' => "Your Ship: {$playerShip->name} (Hull: {$playerShip->hull}/{$playerShip->max_hull})",
        ];

        foreach ($pirateFleet as $pirate) {
            $combatLog[] = [
                'type' => 'info',
                'message' => "Enemy: {$pirate->ship_name} (Hull: {$pirate->hull}/{$pirate->max_hull}, Weapons: {$pirate->weapons})",
            ];
        }

        $combatLog[] = ['type' => 'divider', 'message' => str_repeat('─', 50)];

        // Combat loop
        while ($playerShip->hull > 0 && $pirateFleet->where('hull', '>', 0)->count() > 0) {
            $combatLog[] = [
                'type' => 'round',
                'message' => "\n🔹 ROUND {$round}",
            ];

            // Player's turn - attack weakest pirate
            $target = $this->selectWeakestTarget($pirateFleet);
            if ($target) {
                $damage = $this->calculateDamage($playerShip->weapons);
                $target->takeDamage($damage);

                $combatLog[] = [
                    'type' => 'player_attack',
                    'message' => "  ➜ You fire on {$target->ship_name} for {$damage} damage! (Hull: {$target->hull}/{$target->max_hull})",
                ];

                if ($target->isDestroyed()) {
                    $combatLog[] = [
                        'type' => 'enemy_destroyed',
                        'message' => "  💥 {$target->ship_name} DESTROYED!",
                    ];
                }
            }

            // Pirates' turn - each attacks player
            $survivingPirates = $pirateFleet->where('hull', '>', 0);
            foreach ($survivingPirates as $pirate) {
                $damage = $this->calculateDamage($pirate->weapons);
                $playerShip->hull = max(0, $playerShip->hull - $damage);

                $combatLog[] = [
                    'type' => 'enemy_attack',
                    'message' => "  ⬅ {$pirate->ship_name} fires for {$damage} damage! (Your Hull: {$playerShip->hull}/{$playerShip->max_hull})",
                ];

                if ($playerShip->hull <= 0) {
                    $combatLog[] = [
                        'type' => 'player_destroyed',
                        'message' => "  ☠️  YOUR SHIP HAS BEEN DESTROYED!",
                    ];
                    break;
                }
            }

            $round++;

            // Safety limit to prevent infinite loops
            if ($round > 100) {
                $combatLog[] = [
                    'type' => 'error',
                    'message' => "Combat timeout - draw!",
                ];
                break;
            }
        }

        // Determine outcome
        $victory = $playerShip->hull > 0;

        $combatLog[] = ['type' => 'divider', 'message' => str_repeat('─', 50)];

        if ($victory) {
            $combatLog[] = [
                'type' => 'victory',
                'message' => "🏆 VICTORY! All enemy ships destroyed!",
            ];
            $combatLog[] = [
                'type' => 'info',
                'message' => "Your remaining hull: {$playerShip->hull}/{$playerShip->max_hull}",
            ];
        } else {
            $combatLog[] = [
                'type' => 'defeat',
                'message' => "☠️  DEFEAT - Your ship has been destroyed...",
            ];
        }

        // Save player ship state
        $playerShip->save();

        return [
            'victory' => $victory,
            'log' => $combatLog,
            'player_hull_remaining' => $playerShip->hull,
            'rounds' => $round - 1,
        ];
    }

    /**
     * Select the weakest target (lowest hull)
     */
    private function selectWeakestTarget(Collection $pirateFleet)
    {
        return $pirateFleet->where('hull', '>', 0)
            ->sortBy('hull')
            ->first();
    }

    /**
     * Calculate damage with ±20% randomization
     */
    private function calculateDamage(int $weaponsPower): int
    {
        $variance = $weaponsPower * 0.20;
        $min = (int)floor($weaponsPower - $variance);
        $max = (int)ceil($weaponsPower + $variance);

        return rand($min, $max);
    }

    /**
     * Get combat preview (estimate outcome without actually fighting)
     */
    public function getCombatPreview(PlayerShip $playerShip, Collection $pirateFleet): array
    {
        $playerPower = $playerShip->weapons;
        $totalPiratePower = $pirateFleet->sum('weapons');
        $pirateCount = $pirateFleet->count();

        $playerAdvantage = $playerPower - ($totalPiratePower / max(1, $pirateCount));

        if ($playerAdvantage > 20) {
            $difficulty = 'Easy';
            $winChance = 90;
        } elseif ($playerAdvantage > 0) {
            $difficulty = 'Moderate';
            $winChance = 70;
        } elseif ($playerAdvantage > -20) {
            $difficulty = 'Challenging';
            $winChance = 50;
        } elseif ($playerAdvantage > -40) {
            $difficulty = 'Dangerous';
            $winChance = 30;
        } else {
            $difficulty = 'Deadly';
            $winChance = 10;
        }

        return [
            'difficulty' => $difficulty,
            'estimated_win_chance' => $winChance,
            'your_weapons' => $playerPower,
            'enemy_weapons' => $totalPiratePower,
            'enemy_count' => $pirateCount,
        ];
    }
}

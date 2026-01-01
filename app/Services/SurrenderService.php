<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerShip;
use Illuminate\Support\Collection;

class SurrenderService
{
    // Components that can be downgraded
    private const DOWNGRADEABLE_COMPONENTS = [
        'weapons',
        'sensors',
        'warp_drive',
        'max_hull',
    ];

    /**
     * Process player surrender
     *
     * 1. Jettison all cargo (delete PlayerCargo)
     * 2. 25% chance: Steal upgrades (detach plans + downgrade 1-2 components)
     *
     * @param Player $player
     * @param PlayerShip $playerShip
     * @param Collection $pirateFleet
     * @return array Surrender result with details
     */
    public function processSurrender(Player $player, PlayerShip $playerShip, Collection $pirateFleet): array
    {
        $result = [
            'cargo_lost' => 0,
            'plans_stolen' => 0,
            'components_downgraded' => [],
            'upgrades_stolen' => false,
        ];

        // Step 1: Jettison all cargo
        $cargoCount = $playerShip->cargo()->count();
        $playerShip->cargo()->delete();
        $playerShip->current_cargo = 0;
        $playerShip->save();

        $result['cargo_lost'] = $cargoCount;

        // Step 2: 25% chance to steal upgrades
        if (rand(1, 100) <= 25) {
            $result['upgrades_stolen'] = true;

            // Detach all upgrade plans
            $plansCount = $player->plans()->count();
            $player->plans()->detach();
            $result['plans_stolen'] = $plansCount;

            // Downgrade 1-2 random components
            $componentsToDowngrade = rand(1, 2);
            $selectedComponents = $this->selectRandomComponents($componentsToDowngrade);

            foreach ($selectedComponents as $component) {
                $downgradeAmount = rand(1, 3);
                $result['components_downgraded'][] = $this->downgradeComponent(
                    $playerShip,
                    $component,
                    $downgradeAmount
                );
            }

            $playerShip->save();
        }

        return $result;
    }

    /**
     * Select random components to downgrade
     */
    private function selectRandomComponents(int $count): array
    {
        $shuffled = self::DOWNGRADEABLE_COMPONENTS;
        shuffle($shuffled);

        return array_slice($shuffled, 0, $count);
    }

    /**
     * Downgrade a component, respecting base ship values
     */
    private function downgradeComponent(PlayerShip $playerShip, string $component, int $amount): array
    {
        $currentValue = $playerShip->$component;
        $baseShip = $playerShip->ship;

        // Determine base value from ship
        $baseValue = match ($component) {
            'weapons' => $baseShip->attributes['starting_weapons'] ?? 10,
            'sensors' => $baseShip->attributes['starting_sensors'] ?? 1,
            'warp_drive' => $baseShip->attributes['starting_warp_drive'] ?? 1,
            'max_hull' => $baseShip->hull_strength,
            default => $currentValue,
        };

        // Calculate new value (can't go below base)
        $newValue = max($baseValue, $currentValue - $amount);
        $actualDowngrade = $currentValue - $newValue;

        // Apply downgrade
        $playerShip->$component = $newValue;

        // Special handling for hull - reduce current hull proportionally
        if ($component === 'max_hull' && $actualDowngrade > 0) {
            $playerShip->hull = max(1, $playerShip->hull - $actualDowngrade);
        }

        return [
            'component' => $component,
            'from' => $currentValue,
            'to' => $newValue,
            'amount' => $actualDowngrade,
        ];
    }

    /**
     * Generate surrender message
     */
    public function generateSurrenderMessage(array $result, string $captainName): string
    {
        $messages = [];

        $messages[] = "You surrender to {$captainName}.";
        $messages[] = "Your cargo bay is emptied ({$result['cargo_lost']} items jettisoned).";

        if ($result['upgrades_stolen']) {
            $messages[] = "\nThe pirates board your ship and strip valuable components!";

            if ($result['plans_stolen'] > 0) {
                $messages[] = "  - {$result['plans_stolen']} upgrade plans stolen";
            }

            foreach ($result['components_downgraded'] as $downgrade) {
                if ($downgrade['amount'] > 0) {
                    $componentName = ucfirst(str_replace('_', ' ', $downgrade['component']));
                    $messages[] = "  - {$componentName}: {$downgrade['from']} → {$downgrade['to']} (-{$downgrade['amount']})";
                }
            }
        } else {
            $messages[] = "\nThe pirates let you go with a warning...this time.";
        }

        return implode("\n", $messages);
    }
}

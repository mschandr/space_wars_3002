<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerShip;
use Illuminate\Support\Facades\DB;

class ShipRepairService
{
    // Repair costs per unit
    private const HULL_REPAIR_COST_PER_POINT = 10;

    private const COMPONENT_REPAIR_MULTIPLIER = 50; // Cost per level to restore

    /**
     * Calculate repair costs for a ship
     */
    public function getRepairInfo(PlayerShip $ship): array
    {
        $hullDamage = $ship->max_hull - $ship->hull;
        $hullRepairCost = $hullDamage * self::HULL_REPAIR_COST_PER_POINT;

        // Detect downgraded components
        $downgradedComponents = $this->detectDowngradedComponents($ship);
        $componentRepairCost = 0;

        foreach ($downgradedComponents as $component) {
            $componentRepairCost += $component['repair_cost'];
        }

        return [
            'hull_damage' => $hullDamage,
            'hull_repair_cost' => $hullRepairCost,
            'needs_hull_repair' => $hullDamage > 0,
            'downgraded_components' => $downgradedComponents,
            'component_repair_cost' => $componentRepairCost,
            'needs_component_repair' => ! empty($downgradedComponents),
            'total_repair_cost' => $hullRepairCost + $componentRepairCost,
        ];
    }

    /**
     * Detect components that are below their expected values
     */
    private function detectDowngradedComponents(PlayerShip $ship): array
    {
        $downgraded = [];
        $baseShip = $ship->ship;

        // Components to check
        $components = [
            'weapons' => [
                'name' => 'Weapons',
                'current' => $ship->weapons,
                'base' => $baseShip->attributes['starting_weapons'] ?? 10,
            ],
            'sensors' => [
                'name' => 'Sensors',
                'current' => $ship->sensors,
                'base' => $baseShip->attributes['starting_sensors'] ?? 1,
            ],
            'warp_drive' => [
                'name' => 'Warp Drive',
                'current' => $ship->warp_drive,
                'base' => $baseShip->attributes['starting_warp_drive'] ?? 1,
            ],
            'max_hull' => [
                'name' => 'Hull Plating',
                'current' => $ship->max_hull,
                'base' => $baseShip->hull_strength,
            ],
            'cargo_hold' => [
                'name' => 'Cargo Hold',
                'current' => $ship->cargo_hold,
                'base' => $baseShip->cargo_capacity,
            ],
            'max_fuel' => [
                'name' => 'Fuel Tank',
                'current' => $ship->max_fuel,
                'base' => $baseShip->attributes['max_fuel'] ?? 100,
            ],
        ];

        foreach ($components as $key => $data) {
            // We can't easily determine the "correct" upgraded value without tracking upgrade history
            // For now, we'll only detect if components are below base ship values (catastrophic damage)
            if ($data['current'] < $data['base']) {
                $deficit = $data['base'] - $data['current'];
                $repairCost = $deficit * self::COMPONENT_REPAIR_MULTIPLIER;

                $downgraded[] = [
                    'component' => $key,
                    'name' => $data['name'],
                    'current' => $data['current'],
                    'should_be' => $data['base'],
                    'deficit' => $deficit,
                    'repair_cost' => $repairCost,
                ];
            }
        }

        return $downgraded;
    }

    /**
     * Repair ship hull
     */
    public function repairHull(Player $player, PlayerShip $ship): array
    {
        $hullDamage = $ship->max_hull - $ship->hull;
        $cost = $hullDamage * self::HULL_REPAIR_COST_PER_POINT;

        if ($player->credits < $cost) {
            return [
                'success' => false,
                'message' => 'Insufficient credits for hull repair',
                'cost' => $cost,
            ];
        }

        return DB::transaction(function () use ($player, $ship, $cost, $hullDamage) {
            $player->deductCredits($cost);

            // Repair hull
            $ship->hull = $ship->max_hull;
            $ship->save();

            return [
                'success' => true,
                'message' => "Hull repaired: {$hullDamage} points restored",
                'cost' => $cost,
                'hull_repaired' => $hullDamage,
            ];
        });
    }

    /**
     * Repair downgraded components
     */
    public function repairComponents(Player $player, PlayerShip $ship): array
    {
        $downgraded = $this->detectDowngradedComponents($ship);

        if (empty($downgraded)) {
            return [
                'success' => false,
                'message' => 'No components need repair',
                'cost' => 0,
            ];
        }

        $totalCost = array_sum(array_column($downgraded, 'repair_cost'));

        if ($player->credits < $totalCost) {
            return [
                'success' => false,
                'message' => 'Insufficient credits for component repair',
                'cost' => $totalCost,
            ];
        }

        return DB::transaction(function () use ($player, $ship, $downgraded, $totalCost) {
            $player->deductCredits($totalCost);

            // Repair each component
            $repaired = [];
            foreach ($downgraded as $component) {
                $ship->{$component['component']} = $component['should_be'];
                $repaired[] = $component['name'];
            }
            $ship->save();

            return [
                'success' => true,
                'message' => 'Components repaired: '.implode(', ', $repaired),
                'cost' => $totalCost,
                'components_repaired' => $repaired,
            ];
        });
    }

    /**
     * Repair everything (hull + components)
     */
    public function repairAll(Player $player, PlayerShip $ship): array
    {
        $repairInfo = $this->getRepairInfo($ship);
        $totalCost = $repairInfo['total_repair_cost'];

        if ($totalCost === 0) {
            return [
                'success' => false,
                'message' => 'Ship is already in perfect condition',
                'cost' => 0,
            ];
        }

        if ($player->credits < $totalCost) {
            return [
                'success' => false,
                'message' => 'Insufficient credits for repairs',
                'cost' => $totalCost,
            ];
        }

        return DB::transaction(function () use ($player, $ship, $repairInfo, $totalCost) {
            $results = [];

            // Deduct total cost once upfront
            $player->deductCredits($totalCost);

            // Repair hull
            if ($repairInfo['needs_hull_repair']) {
                $hullDamage = $ship->max_hull - $ship->hull;
                $ship->hull = $ship->max_hull;
                $results[] = "Hull repaired: {$hullDamage} points restored";
            }

            // Repair components
            if ($repairInfo['needs_component_repair']) {
                $repaired = [];
                foreach ($repairInfo['downgraded_components'] as $component) {
                    $ship->{$component['component']} = $component['should_be'];
                    $repaired[] = $component['name'];
                }
                $results[] = 'Components repaired: '.implode(', ', $repaired);
            }

            $ship->save();

            return [
                'success' => true,
                'message' => implode("\n", $results),
                'cost' => $totalCost,
            ];
        });
    }
}

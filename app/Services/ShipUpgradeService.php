<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerShip;

/**
 * @deprecated Use discrete swappable components via ShipyardInventoryService and SalvageYardService instead.
 *             This service will be removed once all consumers have migrated.
 */
class ShipUpgradeService
{
    // Base upgrade costs
    const BASE_COSTS = [
        'max_fuel' => 100,
        'max_hull' => 200,
        'weapons' => 150,
        'cargo_hold' => 100,
        'sensors' => 300,
        'warp_drive' => 500,
    ];

    // Upgrade increments
    const INCREMENTS = [
        'max_fuel' => 10,
        'max_hull' => 10,
        'weapons' => 5,
        'cargo_hold' => 10,
        'sensors' => 1,
        'warp_drive' => 1,
    ];

    // Maximum levels
    const MAX_LEVELS = [
        'max_fuel' => 50,
        'max_hull' => 50,
        'weapons' => 100,
        'cargo_hold' => 100,
        'sensors' => 10,
        'warp_drive' => 10,
    ];

    /**
     * Calculate upgrade cost for a component
     */
    public function calculateUpgradeCost(string $component, int $currentLevel): int
    {
        if (! isset(self::BASE_COSTS[$component])) {
            throw new \InvalidArgumentException("Invalid component: {$component}");
        }

        $baseCost = self::BASE_COSTS[$component];

        return (int) floor($baseCost * (1 + ($currentLevel * 0.5)));
    }

    /**
     * Get the current level of a component
     */
    public function getComponentLevel(PlayerShip $ship, string $component): int
    {
        $increment = self::INCREMENTS[$component];

        // Get base value from ship template
        $shipTemplate = $ship->ship;
        $baseValue = match ($component) {
            'max_fuel' => $shipTemplate->attributes['max_fuel'] ?? 100,
            'max_hull' => $shipTemplate->hull_strength,
            'weapons' => $shipTemplate->attributes['starting_weapons'] ?? 10,
            'cargo_hold' => $shipTemplate->cargo_capacity,
            'sensors' => $shipTemplate->attributes['starting_sensors'] ?? 1,
            'warp_drive' => $shipTemplate->attributes['starting_warp_drive'] ?? 1,
            default => 0,
        };

        $currentValue = $ship->{$component};

        return (int) floor(($currentValue - $baseValue) / $increment);
    }

    /**
     * Calculate the maximum level for a component including plan bonuses
     */
    public function getMaxLevel(Player $player, string $component): int
    {
        // Base maximum level
        $baseMax = self::MAX_LEVELS[$component] ?? 100;

        // Additional levels from plans
        $additionalLevels = $player->getAdditionalLevelsForComponent($component);

        return $baseMax + $additionalLevels;
    }

    /**
     * Check if a ship is a precursor vessel (cannot be upgraded).
     */
    public function isPrecursorShip(PlayerShip $ship): bool
    {
        $blueprint = $ship->ship;

        return $blueprint?->class === 'precursor'
            || ($blueprint?->attributes['is_precursor'] ?? false);
    }

    /**
     * Check if component can be upgraded
     */
    public function canUpgrade(PlayerShip $ship, string $component): bool
    {
        if ($this->isPrecursorShip($ship)) {
            return false;
        }

        $currentLevel = $this->getComponentLevel($ship, $component);
        $maxLevel = $this->getMaxLevel($ship->player, $component);

        return $currentLevel < $maxLevel;
    }

    /**
     * Upgrade a ship component
     */
    public function upgrade(PlayerShip $ship, string $component): array
    {
        if (! isset(self::BASE_COSTS[$component])) {
            return [
                'success' => false,
                'message' => "Invalid component: {$component}",
            ];
        }

        // Precursor ships cannot be upgraded
        if ($this->isPrecursorShip($ship)) {
            return [
                'success' => false,
                'message' => 'Precursor vessels are beyond mortal engineering. Their technology cannot be modified.',
            ];
        }

        // Check if component can be upgraded
        if (! $this->canUpgrade($ship, $component)) {
            return [
                'success' => false,
                'message' => "Component {$component} is already at maximum level.",
            ];
        }

        $currentLevel = $this->getComponentLevel($ship, $component);
        $cost = $this->calculateUpgradeCost($component, $currentLevel);

        // Check if player has enough credits
        if (! $ship->player->deductCredits($cost)) {
            return [
                'success' => false,
                'message' => "Insufficient credits. Upgrade costs {$cost} credits.",
                'cost' => $cost,
            ];
        }

        // Apply the upgrade
        $increment = self::INCREMENTS[$component];
        $ship->{$component} += $increment;

        // If upgrading max_hull, also restore hull proportionally
        if ($component === 'max_hull') {
            $ship->hull += $increment;
        }

        $ship->save();

        return [
            'success' => true,
            'message' => "Successfully upgraded {$component} to level ".($currentLevel + 1),
            'cost' => $cost,
            'new_level' => $currentLevel + 1,
            'new_value' => $ship->{$component},
        ];
    }

    /**
     * Get upgrade information for all components
     */
    public function getUpgradeInfo(PlayerShip $ship): array
    {
        $isPrecursor = $this->isPrecursorShip($ship);
        $info = [];
        $player = $ship->player;

        foreach (array_keys(self::BASE_COSTS) as $component) {
            $currentLevel = $this->getComponentLevel($ship, $component);
            $baseMaxLevel = self::MAX_LEVELS[$component];
            $maxLevel = $this->getMaxLevel($player, $component);
            $canUpgrade = ! $isPrecursor && $currentLevel < $maxLevel;

            $info[$component] = [
                'current_value' => $ship->{$component},
                'current_level' => $currentLevel,
                'base_max_level' => $baseMaxLevel,
                'max_level' => $maxLevel,
                'additional_levels' => $maxLevel - $baseMaxLevel,
                'can_upgrade' => $canUpgrade,
                'upgrade_cost' => $canUpgrade ? $this->calculateUpgradeCost($component, $currentLevel) : null,
                'next_value' => $canUpgrade ? $ship->{$component} + self::INCREMENTS[$component] : null,
                'locked_reason' => $isPrecursor ? 'Precursor technology cannot be modified.' : null,
            ];
        }

        return $info;
    }
}

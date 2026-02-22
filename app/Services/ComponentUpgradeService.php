<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerShipComponent;
use Illuminate\Support\Facades\DB;

/**
 * Service for upgrading installed ship components.
 *
 * Each component has a max_upgrade_level based on rarity (cheaper parts are more upgradeable).
 * Each level adds 15% of the base effect value.
 * Cost scales: upgrade_cost_base * (1 + current_level * 0.5)
 *
 * Exotic components cannot be upgraded (max_upgrade_level = 0).
 * Precursor ships cannot have their components upgraded.
 */
class ComponentUpgradeService
{
    /**
     * Get upgrade info for an installed component.
     */
    public function getUpgradeInfo(PlayerShipComponent $installed): array
    {
        $installed->loadMissing('component', 'playerShip.ship');

        $component = $installed->component;
        $currentLevel = $installed->upgrade_level ?? 0;
        $maxLevel = $component->max_upgrade_level ?? 0;
        $canUpgrade = $this->canUpgrade($installed);

        $effectPerLevel = config('game_config.components.upgrade_effect_per_level', 0.15);

        // Calculate current and next effects
        $currentEffects = [];
        $nextEffects = [];
        foreach ($component->effects ?? [] as $stat => $baseValue) {
            if (is_numeric($baseValue)) {
                $currentEffects[$stat] = round($baseValue * (1 + $currentLevel * $effectPerLevel), 2);
                if ($canUpgrade) {
                    $nextEffects[$stat] = round($baseValue * (1 + ($currentLevel + 1) * $effectPerLevel), 2);
                }
            } else {
                $currentEffects[$stat] = $baseValue;
                $nextEffects[$stat] = $baseValue;
            }
        }

        return [
            'component_name' => $component->name,
            'rarity' => $component->rarity->value,
            'rarity_label' => $component->rarity->label(),
            'current_level' => $currentLevel,
            'max_level' => $maxLevel,
            'can_upgrade' => $canUpgrade,
            'upgrade_cost' => $canUpgrade ? $this->calculateUpgradeCost($installed) : null,
            'current_effects' => $currentEffects,
            'next_effects' => $canUpgrade ? $nextEffects : null,
            'locked_reason' => $this->getLockedReason($installed),
        ];
    }

    /**
     * Calculate the credit cost for the next upgrade.
     * Formula: upgrade_cost_base * (1 + current_level * scaling)
     */
    public function calculateUpgradeCost(PlayerShipComponent $installed): int
    {
        $installed->loadMissing('component');

        $base = (float) ($installed->component->upgrade_cost_base ?? 0);
        $currentLevel = $installed->upgrade_level ?? 0;
        $scaling = config('game_config.components.upgrade_cost_scaling', 0.5);

        return (int) ceil($base * (1 + $currentLevel * $scaling));
    }

    /**
     * Execute a component upgrade.
     *
     * @return array{success: bool, message: string, cost?: int, new_level?: int}
     */
    public function upgradeComponent(Player $player, PlayerShipComponent $installed): array
    {
        $installed->loadMissing('component', 'playerShip.ship');

        if (! $this->canUpgrade($installed)) {
            $reason = $this->getLockedReason($installed);

            return [
                'success' => false,
                'message' => $reason ?? 'This component cannot be upgraded further.',
            ];
        }

        $cost = $this->calculateUpgradeCost($installed);

        if ($player->credits < $cost) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Insufficient credits. Upgrade costs %s but you only have %s.',
                    number_format($cost),
                    number_format($player->credits)
                ),
                'cost' => $cost,
            ];
        }

        return DB::transaction(function () use ($player, $installed, $cost) {
            $player->deductCredits($cost);

            $installed->upgrade_level = ($installed->upgrade_level ?? 0) + 1;
            $installed->save();

            return [
                'success' => true,
                'message' => sprintf(
                    '%s upgraded to level %d.',
                    $installed->component->name,
                    $installed->upgrade_level
                ),
                'cost' => $cost,
                'new_level' => $installed->upgrade_level,
            ];
        });
    }

    /**
     * Check if an installed component can be upgraded.
     */
    public function canUpgrade(PlayerShipComponent $installed): bool
    {
        $installed->loadMissing('component', 'playerShip.ship');

        $component = $installed->component;
        $currentLevel = $installed->upgrade_level ?? 0;
        $maxLevel = $component->max_upgrade_level ?? 0;

        // Already at max
        if ($currentLevel >= $maxLevel) {
            return false;
        }

        // Exotic components can't be upgraded (max_upgrade_level should be 0, but double-check)
        if ($component->rarity->value === 'exotic') {
            return false;
        }

        // Precursor ships can't have components upgraded
        if ($this->isPrecursorShip($installed)) {
            return false;
        }

        return true;
    }

    /**
     * Get the reason a component can't be upgraded, or null if it can.
     */
    private function getLockedReason(PlayerShipComponent $installed): ?string
    {
        $installed->loadMissing('component', 'playerShip.ship');

        $component = $installed->component;
        $currentLevel = $installed->upgrade_level ?? 0;
        $maxLevel = $component->max_upgrade_level ?? 0;

        if ($this->isPrecursorShip($installed)) {
            return 'Precursor vessels are beyond mortal engineering. Their technology cannot be modified.';
        }

        if ($component->rarity->value === 'exotic') {
            return 'Exotic components are already at peak performance and cannot be upgraded.';
        }

        if ($currentLevel >= $maxLevel) {
            return 'Component is already at maximum upgrade level.';
        }

        return null;
    }

    /**
     * Check if the component is installed on a precursor ship.
     */
    private function isPrecursorShip(PlayerShipComponent $installed): bool
    {
        $ship = $installed->playerShip;
        $blueprint = $ship?->ship;

        return $blueprint?->class === 'precursor'
            || ($blueprint?->attributes['is_precursor'] ?? false);
    }
}

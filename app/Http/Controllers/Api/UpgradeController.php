<?php

namespace App\Http\Controllers\Api;

use App\Models\PlayerShip;
use App\Services\ShipUpgradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles ship component upgrades
 */
class UpgradeController extends BaseApiController
{
    public function __construct(
        private ShipUpgradeService $upgradeService
    ) {}

    /**
     * List all upgradeable components
     *
     * GET /api/ships/{uuid}/upgrade-options
     */
    public function listUpgradeOptions(Request $request, string $uuid): JsonResponse
    {
        $ship = PlayerShip::where('uuid', $uuid)
            ->whereHas('player', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->with('player.plans')
            ->first();

        if (! $ship) {
            return $this->notFound('Ship not found');
        }

        $upgradeInfo = $this->upgradeService->getUpgradeInfo($ship);

        return $this->success([
            'ship_uuid' => $ship->uuid,
            'ship_name' => $ship->name,
            'player_credits' => (float) $ship->player->credits,
            'components' => $upgradeInfo,
        ]);
    }

    /**
     * Get upgrade details for a specific component
     *
     * GET /api/ships/{uuid}/upgrade/{component}
     */
    public function getComponentUpgradeDetails(Request $request, string $uuid, string $component): JsonResponse
    {
        $ship = PlayerShip::where('uuid', $uuid)
            ->whereHas('player', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->with('player.plans')
            ->first();

        if (! $ship) {
            return $this->notFound('Ship not found');
        }

        if (! isset(ShipUpgradeService::BASE_COSTS[$component])) {
            return $this->error("Invalid component: {$component}", 'INVALID_COMPONENT');
        }

        $currentLevel = $this->upgradeService->getComponentLevel($ship, $component);
        $maxLevel = $this->upgradeService->getMaxLevel($ship->player, $component);
        $canUpgrade = $this->upgradeService->canUpgrade($ship, $component);

        return $this->success([
            'component' => $component,
            'current_value' => $ship->{$component},
            'current_level' => $currentLevel,
            'max_level' => $maxLevel,
            'can_upgrade' => $canUpgrade,
            'upgrade_cost' => $canUpgrade ? $this->upgradeService->calculateUpgradeCost($component, $currentLevel) : null,
            'next_value' => $canUpgrade ? $ship->{$component} + ShipUpgradeService::INCREMENTS[$component] : null,
            'increment' => ShipUpgradeService::INCREMENTS[$component],
            'player_credits' => (float) $ship->player->credits,
            'can_afford' => $canUpgrade && $ship->player->credits >= $this->upgradeService->calculateUpgradeCost($component, $currentLevel),
        ]);
    }

    /**
     * Execute component upgrade
     *
     * POST /api/ships/{uuid}/upgrade/{component}
     */
    public function executeUpgrade(Request $request, string $uuid, string $component): JsonResponse
    {
        $ship = PlayerShip::where('uuid', $uuid)
            ->whereHas('player', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->with('player')
            ->first();

        if (! $ship) {
            return $this->notFound('Ship not found');
        }

        $result = $this->upgradeService->upgrade($ship, $component);

        if (! $result['success']) {
            return $this->error($result['message'], 'UPGRADE_FAILED');
        }

        return $this->success([
            'component' => $component,
            'old_value' => $result['new_value'] - ShipUpgradeService::INCREMENTS[$component],
            'new_value' => $result['new_value'],
            'new_level' => $result['new_level'],
            'cost' => $result['cost'],
            'credits_remaining' => (float) $ship->player->fresh()->credits,
        ], $result['message']);
    }

    /**
     * Get owned upgrade plans
     *
     * GET /api/players/{uuid}/plans
     */
    public function getOwnedPlans(Request $request, string $uuid): JsonResponse
    {
        $player = \App\Models\Player::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with('plans')
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $plansByComponent = [];
        foreach ($player->plans as $plan) {
            $component = $plan->component;
            if (! isset($plansByComponent[$component])) {
                $plansByComponent[$component] = [
                    'component' => $component,
                    'total_additional_levels' => 0,
                    'plans' => [],
                ];
            }

            $plansByComponent[$component]['total_additional_levels'] += $plan->additional_levels;
            $plansByComponent[$component]['plans'][] = [
                'id' => $plan->id,
                'uuid' => $plan->uuid,
                'name' => $plan->name,
                'additional_levels' => $plan->additional_levels,
                'rarity' => $plan->rarity,
            ];
        }

        return $this->success([
            'player_uuid' => $player->uuid,
            'plans_by_component' => array_values($plansByComponent),
            'total_plans' => $player->plans->count(),
        ]);
    }

    /**
     * Get upgrade cost formulas
     *
     * GET /api/upgrade-costs
     */
    public function getUpgradeCostFormulas(): JsonResponse
    {
        return $this->success([
            'formula' => 'base_cost * (1 + (current_level * 0.5))',
            'base_costs' => ShipUpgradeService::BASE_COSTS,
            'increments' => ShipUpgradeService::INCREMENTS,
        ]);
    }

    /**
     * Get max levels per component
     *
     * GET /api/upgrade-limits
     */
    public function getUpgradeLimits(): JsonResponse
    {
        return $this->success([
            'base_max_levels' => ShipUpgradeService::MAX_LEVELS,
            'note' => 'Additional levels can be gained from upgrade plans',
        ]);
    }
}

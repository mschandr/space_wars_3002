<?php

namespace App\Http\Controllers\Api;

use App\Models\Player;
use App\Services\ShipRepairService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipServiceController extends BaseApiController
{
    public function __construct(
        private readonly ShipRepairService $repairService
    ) {}

    /**
     * Get repair estimate for a ship
     *
     * GET /api/ships/{uuid}/repair-estimate
     */
    public function getRepairEstimate(string $uuid): JsonResponse
    {
        $ship = \App\Models\PlayerShip::where('uuid', $uuid)->firstOrFail();

        $repairInfo = $this->repairService->getRepairInfo($ship);

        return $this->success([
            'hull_damage' => $repairInfo['hull_damage'],
            'hull_repair_cost' => $repairInfo['hull_repair_cost'],
            'needs_hull_repair' => $repairInfo['needs_hull_repair'],
            'downgraded_components' => $repairInfo['downgraded_components'],
            'component_repair_cost' => $repairInfo['component_repair_cost'],
            'needs_component_repair' => $repairInfo['needs_component_repair'],
            'total_repair_cost' => $repairInfo['total_repair_cost'],
            'hull_percentage' => $ship->max_hull > 0 ? round(($ship->hull / $ship->max_hull) * 100, 1) : 0,
        ]);
    }

    /**
     * Repair ship hull only
     *
     * POST /api/ships/{uuid}/repair/hull
     */
    public function repairHull(Request $request, string $uuid): JsonResponse
    {
        $ship = \App\Models\PlayerShip::where('uuid', $uuid)->firstOrFail();
        $player = $ship->player;

        $this->authorizePlayer($player, $request->user());

        $result = $this->repairService->repairHull($player, $ship);

        if (! $result['success']) {
            return $this->error($result['message'], 'ERROR', null, 400);
        }

        // Reload ship
        $ship->refresh();

        return $this->success([
            'hull_repaired' => $result['hull_repaired'],
            'cost' => $result['cost'],
            'current_hull' => $ship->hull,
            'max_hull' => $ship->max_hull,
            'remaining_credits' => $player->credits,
        ], $result['message']);
    }

    /**
     * Repair downgraded components
     *
     * POST /api/ships/{uuid}/repair/components
     */
    public function repairComponents(Request $request, string $uuid): JsonResponse
    {
        $ship = \App\Models\PlayerShip::where('uuid', $uuid)->firstOrFail();
        $player = $ship->player;

        $this->authorizePlayer($player, $request->user());

        $result = $this->repairService->repairComponents($player, $ship);

        if (! $result['success']) {
            return $this->error($result['message'], 'ERROR', null, 400);
        }

        // Reload ship
        $ship->refresh();

        return $this->success([
            'components_repaired' => $result['components_repaired'] ?? [],
            'cost' => $result['cost'],
            'remaining_credits' => $player->credits,
        ], $result['message']);
    }

    /**
     * Repair everything (hull + components)
     *
     * POST /api/ships/{uuid}/repair/all
     */
    public function repairAll(Request $request, string $uuid): JsonResponse
    {
        $ship = \App\Models\PlayerShip::where('uuid', $uuid)->firstOrFail();
        $player = $ship->player;

        $this->authorizePlayer($player, $request->user());

        $result = $this->repairService->repairAll($player, $ship);

        if (! $result['success']) {
            return $this->error($result['message'], 'ERROR', null, 400);
        }

        // Reload ship and player
        $ship->refresh();
        $player->refresh();

        return $this->success([
            'cost' => $result['cost'],
            'current_hull' => $ship->hull,
            'max_hull' => $ship->max_hull,
            'remaining_credits' => $player->credits,
        ], $result['message']);
    }

    /**
     * Get maintenance status (hull integrity assessment)
     *
     * GET /api/ships/{uuid}/maintenance
     */
    public function getMaintenanceStatus(string $uuid): JsonResponse
    {
        $ship = \App\Models\PlayerShip::where('uuid', $uuid)->firstOrFail();

        $hullPercentage = $ship->max_hull > 0 ? ($ship->hull / $ship->max_hull) * 100 : 0;

        $status = match (true) {
            $hullPercentage >= 90 => 'excellent',
            $hullPercentage >= 70 => 'good',
            $hullPercentage >= 50 => 'fair',
            $hullPercentage >= 30 => 'poor',
            $hullPercentage >= 10 => 'critical',
            default => 'emergency',
        };

        $repairInfo = $this->repairService->getRepairInfo($ship);

        return $this->success([
            'status' => $status,
            'hull_percentage' => round($hullPercentage, 1),
            'current_hull' => $ship->hull,
            'max_hull' => $ship->max_hull,
            'damage' => $repairInfo['hull_damage'],
            'needs_repair' => $repairInfo['needs_hull_repair'] || $repairInfo['needs_component_repair'],
            'estimated_repair_cost' => $repairInfo['total_repair_cost'],
            'is_operational' => $ship->status === 'operational',
        ]);
    }
}

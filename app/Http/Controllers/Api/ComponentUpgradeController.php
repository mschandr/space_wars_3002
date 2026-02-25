<?php

namespace App\Http\Controllers\Api;

use App\Models\PlayerShipComponent;
use App\Services\ComponentUpgradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComponentUpgradeController extends BaseApiController
{
    public function __construct(
        private ComponentUpgradeService $upgradeService
    ) {}

    /**
     * Get upgrade info for an installed component.
     *
     * GET /api/players/{uuid}/components/{componentId}/upgrade-info
     */
    public function upgradeInfo(Request $request, string $uuid, int $componentId): JsonResponse
    {
        $player = $this->findPlayerWithShipOrFail($uuid, $request);

        if ($player instanceof JsonResponse) {
            return $player;
        }

        $installed = PlayerShipComponent::where('id', $componentId)
            ->whereHas('playerShip', fn ($q) => $q->where('player_id', $player->id))
            ->with('component', 'playerShip.ship')
            ->first();

        if (! $installed) {
            return $this->notFound('Component not found on any of your ships.');
        }

        $info = $this->upgradeService->getUpgradeInfo($installed);

        return $this->success([
            'component_id' => $installed->id,
            'player_credits' => (float) $player->credits,
            'can_afford' => $info['can_upgrade'] && $player->credits >= ($info['upgrade_cost'] ?? PHP_INT_MAX),
            ...$info,
        ]);
    }

    /**
     * Execute a component upgrade.
     *
     * POST /api/players/{uuid}/components/{componentId}/upgrade
     */
    public function upgrade(Request $request, string $uuid, int $componentId): JsonResponse
    {
        $player = $this->findPlayerWithShipOrFail($uuid, $request);

        if ($player instanceof JsonResponse) {
            return $player;
        }

        $installed = PlayerShipComponent::where('id', $componentId)
            ->whereHas('playerShip', fn ($q) => $q->where('player_id', $player->id))
            ->with('component', 'playerShip.ship')
            ->first();

        if (! $installed) {
            return $this->notFound('Component not found on any of your ships.');
        }

        $result = $this->upgradeService->upgradeComponent($player, $installed);

        if (! $result['success']) {
            return $this->error($result['message'], 'UPGRADE_FAILED');
        }

        return $this->success([
            'new_level' => $result['new_level'],
            'cost' => $result['cost'],
            'credits_remaining' => (float) $player->fresh()->credits,
        ], $result['message']);
    }
}

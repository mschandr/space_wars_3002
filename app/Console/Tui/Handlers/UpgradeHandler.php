<?php

namespace App\Console\Tui\Handlers;

use App\Models\Player;
use App\Services\ShipUpgradeService;

class UpgradeHandler
{
    public function __construct(
        private Player $player,
        private ShipUpgradeService $upgradeService
    ) {
    }

    /**
     * Execute a component upgrade
     */
    public function executeUpgrade(string $component): array
    {
        $ship = $this->player->activeShip;

        // Check if upgrade is possible
        if (!$this->upgradeService->canUpgrade($ship, $component)) {
            return [
                'success' => false,
                'message' => "Component is already at maximum level",
            ];
        }

        // Attempt upgrade
        $result = $this->upgradeService->upgrade($ship, $component);

        if ($result['success']) {
            // Reload player data
            $this->player->refresh();
            $this->player->load('activeShip.ship');

            return [
                'success' => true,
                'message' => $result['message'] . " - Cost: " . number_format($result['cost'], 2) . " credits",
                'cost' => $result['cost'],
                'new_level' => $result['new_level'],
                'new_value' => $result['new_value'],
            ];
        }

        return $result;
    }

    /**
     * Get upgrade info for all components
     */
    public function getUpgradeInfo(): array
    {
        $ship = $this->player->activeShip;
        return $this->upgradeService->getUpgradeInfo($ship);
    }

    /**
     * Get upgrade info for a specific component
     */
    public function getComponentInfo(string $component): ?array
    {
        $info = $this->getUpgradeInfo();
        return $info[$component] ?? null;
    }
}

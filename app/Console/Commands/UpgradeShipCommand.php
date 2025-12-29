<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Services\ShipUpgradeService;
use Illuminate\Console\Command;

class UpgradeShipCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ship:upgrade {player_id} {component?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upgrade a ship component or view upgrade information';

    /**
     * Execute the console command.
     */
    public function handle(ShipUpgradeService $upgradeService)
    {
        $playerId = $this->argument('player_id');
        $component = $this->argument('component');

        // Find the player
        $player = Player::find($playerId);
        if (!$player) {
            $this->error("Player with ID {$playerId} not found.");
            return 1;
        }

        // Get the player's active ship
        $ship = $player->activeShip;
        if (!$ship) {
            $this->error("Player has no active ship.");
            return 1;
        }

        // If no component specified, show upgrade info
        if (!$component) {
            $this->info("Ship: {$ship->name}");
            $this->info("Player Credits: {$player->credits}");
            $this->newLine();

            $upgradeInfo = $upgradeService->getUpgradeInfo($ship);

            $this->table(
                ['Component', 'Current Value', 'Level', 'Max Level', 'Can Upgrade', 'Upgrade Cost', 'Next Value'],
                collect($upgradeInfo)->map(function ($info, $component) {
                    return [
                        $component,
                        $info['current_value'],
                        $info['current_level'],
                        $info['max_level'],
                        $info['can_upgrade'] ? 'Yes' : 'No',
                        $info['upgrade_cost'] ?? 'N/A',
                        $info['next_value'] ?? 'N/A',
                    ];
                })->toArray()
            );

            $this->info("Usage: ship:upgrade {player_id} {component}");
            return 0;
        }

        // Perform the upgrade
        $result = $upgradeService->upgrade($ship, $component);

        if ($result['success']) {
            $this->info($result['message']);
            $this->info("New value: {$result['new_value']}");
            $this->info("Cost: {$result['cost']} credits");
            $this->info("Remaining credits: {$player->credits}");
        } else {
            $this->error($result['message']);
            if (isset($result['cost'])) {
                $this->info("Player credits: {$player->credits}");
            }
            return 1;
        }

        return 0;
    }
}

<?php

namespace App\Console\Commands;

use App\Console\Tui\Windows\MainMenuWindow;
use App\Models\Player;
use Illuminate\Console\Command;
use TerminalUI\Core\Application;

/**
 * Player Interface Command - TUI Version
 *
 * Uses the TerminalUI framework for a clean, modern interface
 */
class PlayerInterfaceTuiCommand extends Command
{
    protected $signature = 'player:interface-tui {player_id}';

    protected $description = 'Display player interface using TUI framework';

    public function handle(): int
    {
        $playerId = $this->argument('player_id');

        // Load player with all relationships
        $player = Player::with([
            'currentLocation.tradingHub',
            'currentLocation.shipShop',
            'currentLocation.componentShop',
            'currentLocation.repairShop',
            'currentLocation.plansShop',
            'currentLocation.children',
            'currentLocation.parent',
            'activeShip.ship',
            'activeShip.cargo.mineral'
        ])->find($playerId);

        if (!$player) {
            $this->error("Player with ID {$playerId} not found.");
            return 1;
        }

        if (!$player->activeShip) {
            $this->error("Player has no active ship.");
            return 1;
        }

        // Create TUI application
        $app = new Application();

        // Create main menu window
        $mainWindow = new MainMenuWindow($player);

        // Add window to application
        $app->addWindow($mainWindow);

        // Run TUI application
        $app->run();

        // Clean exit message
        $this->newLine();
        $this->info('Exiting Space Wars 3002...');
        $this->newLine();

        return 0;
    }
}

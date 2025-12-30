<?php

namespace App\Console\Commands;

use App\Console\Services\TuiNavigationService;
use App\Console\Tui\Screens\DashboardScreen;
use App\Console\Tui\Screens\PlansScreen;
use App\Console\Tui\Screens\TradeScreen;
use App\Console\Tui\Screens\TravelScreen;
use App\Console\Tui\Screens\UpgradesScreen;
use App\Models\Player;
use Illuminate\Console\Command;
use PhpTui\Term\Terminal;
use PhpTui\Tui\Bridge\PhpTerm\PhpTermBackend;
use PhpTui\Tui\Display\Backend;

class PlayerInterfaceTuiCommand extends Command
{
    protected $signature = 'player:tui {player_id}';
    protected $description = 'TUI-based player interface using php-tui';

    private Player $player;
    private Backend $backend;
    private Terminal $terminal;
    private TuiNavigationService $navigator;
    private bool $running = true;
    private array $screens = [];

    public function handle(): int
    {
        $playerId = $this->argument('player_id');

        // Load player with relationships
        $this->player = Player::with([
            'currentLocation.children',
            'currentLocation.parent',
            'currentLocation.tradingHub',
            'activeShip.ship',
            'activeShip.cargo.mineral',
            'plans'
        ])->find($playerId);

        if (!$this->player) {
            $this->error("Player with ID {$playerId} not found.");
            return 1;
        }

        if (!$this->player->activeShip) {
            $this->error("Player has no active ship.");
            return 1;
        }

        // Initialize terminal, backend and navigation
        $this->terminal = Terminal::new();
        $this->backend = PhpTermBackend::new($this->terminal);
        $this->navigator = new TuiNavigationService();

        // Initialize screens
        $this->initializeScreens();

        try {
            // Run the TUI
            $this->runTui();
        } finally {
            // Ensure terminal is restored
            $this->terminal->disableRawMode();
        }

        return 0;
    }

    /**
     * Initialize all screen objects
     */
    private function initializeScreens(): void
    {
        $this->screens = [
            'main' => new DashboardScreen($this->player),
            'travel' => new TravelScreen($this->player),
            'trade' => new TradeScreen($this->player),
            'upgrades' => new UpgradesScreen($this->player),
            'plans' => new PlansScreen($this->player),
        ];
    }

    /**
     * Main TUI event loop
     */
    private function runTui(): void
    {
        $this->terminal->enableRawMode();

        while ($this->running) {
            // Regenerate fuel before each render
            $this->player->activeShip->regenerateFuel();

            // Render the current screen
            $this->render();

            // Handle input (non-blocking with timeout)
            if ($event = $this->terminal->events()->next(100)) {
                $this->handleEvent($event);
            }
        }
    }

    /**
     * Render the current screen
     */
    private function render(): void
    {
        $this->backend->draw(function ($display) {
            $currentScreen = $this->getCurrentScreen();
            $currentScreen->render($display);
        });
    }

    /**
     * Get the current screen based on navigation state
     */
    private function getCurrentScreen()
    {
        $view = $this->navigator->getCurrentView();
        return $this->screens[$view] ?? $this->screens['main'];
    }

    /**
     * Handle keyboard events
     */
    private function handleEvent($event): void
    {
        if (!($event instanceof \PhpTui\Term\Event\KeyEvent)) {
            return;
        }

        $currentScreen = $this->getCurrentScreen();
        $action = $currentScreen->handleKeyEvent($event->code);

        // Handle navigation actions
        if ($action) {
            $this->handleNavigationAction($action, $currentScreen);
        }
    }

    /**
     * Handle navigation actions from screens
     */
    private function handleNavigationAction(string $action, $currentScreen): void
    {
        match ($action) {
            'quit' => $this->running = false,
            'back' => $this->handleBack($currentScreen),
            'travel', 'trade', 'upgrades', 'plans', 'ship_info' => $this->navigateToScreen($action),
            default => null,
        };
    }

    /**
     * Handle back navigation
     */
    private function handleBack($currentScreen): void
    {
        // Reset screen state when going back
        $currentScreen->resetState();

        if ($this->navigator->canNavigateBack()) {
            $this->navigator->goBack();
        } else {
            $this->running = false;
        }
    }

    /**
     * Navigate to a specific screen
     */
    private function navigateToScreen(string $screen): void
    {
        // Reset current screen state
        $currentScreen = $this->getCurrentScreen();
        $currentScreen->resetState();

        // Navigate to new screen
        $this->navigator->navigateTo($screen);
    }
}

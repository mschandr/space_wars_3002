<?php

namespace App\Console\Tui\Screens;

use App\Models\Player;
use PhpTui\Tui\Model\Display\Backend;

abstract class AbstractTuiScreen
{
    protected Player $player;
    protected int $selectedIndex = 0;
    protected ?string $statusMessage = null;
    protected ?string $errorMessage = null;

    public function __construct(Player $player)
    {
        $this->player = $player;
    }

    /**
     * Render the screen
     */
    abstract public function render($display): void;

    /**
     * Handle keyboard event
     * Returns navigation action or null
     */
    abstract public function handleKeyEvent($keyCode): ?string;

    /**
     * Check if navigation is allowed in this screen
     */
    public function canNavigate(): bool
    {
        return true;
    }

    /**
     * Reset screen state
     */
    public function resetState(): void
    {
        $this->selectedIndex = 0;
        $this->statusMessage = null;
        $this->errorMessage = null;
    }

    /**
     * Set error message
     */
    public function setError(string $message): void
    {
        $this->errorMessage = $message;
        $this->statusMessage = null;
    }

    /**
     * Set status message
     */
    public function setStatus(string $message): void
    {
        $this->statusMessage = $message;
        $this->errorMessage = null;
    }

    /**
     * Clear all messages
     */
    public function clearMessages(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
    }

    /**
     * Get selected index
     */
    public function getSelectedIndex(): int
    {
        return $this->selectedIndex;
    }

    /**
     * Set selected index
     */
    public function setSelectedIndex(int $index): void
    {
        $this->selectedIndex = max(0, $index);
    }

    /**
     * Reload player data
     */
    protected function reloadPlayer(): void
    {
        $this->player->refresh();
        $this->player->load([
            'currentLocation.children',
            'currentLocation.parent',
            'currentLocation.tradingHub',
            'activeShip.ship',
            'activeShip.cargo.mineral',
            'plans'
        ]);
    }
}

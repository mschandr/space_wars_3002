<?php

namespace App\Console\Services;

class TuiNavigationService
{
    private string $currentView = 'main';
    private array $viewHistory = [];

    /**
     * Navigate to a specific view
     */
    public function navigateTo(string $view): void
    {
        if ($this->currentView !== $view) {
            $this->viewHistory[] = $this->currentView;
            $this->currentView = $view;
        }
    }

    /**
     * Go back to the previous view
     */
    public function goBack(): void
    {
        if (!empty($this->viewHistory)) {
            $this->currentView = array_pop($this->viewHistory);
        } else {
            $this->currentView = 'main';
        }
    }

    /**
     * Get the current view name
     */
    public function getCurrentView(): string
    {
        return $this->currentView;
    }

    /**
     * Check if we can navigate back
     */
    public function canNavigateBack(): bool
    {
        return !empty($this->viewHistory);
    }

    /**
     * Check if currently on main view
     */
    public function isOnMainView(): bool
    {
        return $this->currentView === 'main';
    }

    /**
     * Reset to main view
     */
    public function reset(): void
    {
        $this->currentView = 'main';
        $this->viewHistory = [];
    }

    /**
     * Get the view history
     */
    public function getHistory(): array
    {
        return $this->viewHistory;
    }
}

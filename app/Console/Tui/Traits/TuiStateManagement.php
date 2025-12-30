<?php

namespace App\Console\Tui\Traits;

trait TuiStateManagement
{
    protected int $selectedIndex = 0;
    protected ?string $statusMessage = null;
    protected ?string $errorMessage = null;

    /**
     * Reset selection to first item
     */
    protected function resetSelection(): void
    {
        $this->selectedIndex = 0;
    }

    /**
     * Clear all messages
     */
    protected function clearMessages(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
    }

    /**
     * Set error message and clear status
     */
    protected function setErrorMessage(string $message): void
    {
        $this->errorMessage = $message;
        $this->statusMessage = null;
    }

    /**
     * Set status message and clear error
     */
    protected function setStatusMessage(string $message): void
    {
        $this->statusMessage = $message;
        $this->errorMessage = null;
    }

    /**
     * Check if there's an error message
     */
    protected function hasError(): bool
    {
        return $this->errorMessage !== null;
    }

    /**
     * Check if there's a status message
     */
    protected function hasStatus(): bool
    {
        return $this->statusMessage !== null;
    }

    /**
     * Get current selection index
     */
    protected function getSelection(): int
    {
        return $this->selectedIndex;
    }

    /**
     * Set selection index with bounds check
     */
    protected function setSelection(int $index, int $min = 0, ?int $max = null): void
    {
        $this->selectedIndex = max($min, $index);
        if ($max !== null) {
            $this->selectedIndex = min($max, $this->selectedIndex);
        }
    }
}

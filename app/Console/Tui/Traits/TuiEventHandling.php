<?php

namespace App\Console\Tui\Traits;

trait TuiEventHandling
{
    /**
     * Handle up/down navigation
     */
    protected function handleUpDown(int &$selectedIndex, int $minIndex, int $maxIndex, bool $isUp): void
    {
        if ($isUp) {
            $selectedIndex = max($minIndex, $selectedIndex - 1);
        } else {
            $selectedIndex = min($maxIndex, $selectedIndex + 1);
        }
    }

    /**
     * Handle escape key - returns true if should exit screen
     */
    protected function handleEscape(): bool
    {
        return true;
    }

    /**
     * Check if character is numeric
     */
    protected function isNumericChar(string $char): bool
    {
        return is_numeric($char) && strlen($char) === 1;
    }

    /**
     * Append numeric character to buffer
     */
    protected function appendToBuffer(string &$buffer, string $char, int $maxLength = 10): bool
    {
        if ($this->isNumericChar($char) && strlen($buffer) < $maxLength) {
            $buffer .= $char;
            return true;
        }
        return false;
    }

    /**
     * Remove last character from buffer
     */
    protected function backspaceBuffer(string &$buffer): bool
    {
        if (strlen($buffer) > 0) {
            $buffer = substr($buffer, 0, -1);
            return true;
        }
        return false;
    }

    /**
     * Parse buffer to integer with validation
     */
    protected function parseBufferToInt(string $buffer, int $min = 1, ?int $max = null): ?int
    {
        if (empty($buffer)) {
            return null;
        }

        $value = (int)$buffer;

        if ($value < $min) {
            return null;
        }

        if ($max !== null && $value > $max) {
            return null;
        }

        return $value;
    }
}

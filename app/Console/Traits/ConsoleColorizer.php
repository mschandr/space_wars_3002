<?php

namespace App\Console\Traits;

trait ConsoleColorizer
{
    // ANSI color codes
    private const COLORS = [
        'reset' => "\033[0m",
        'bold' => "\033[1m",
        'dim' => "\033[2m",

        // Stellar classifications
        'O' => "\033[38;5;45m",  // Bright blue (Blue Supergiant)
        'B' => "\033[38;5;75m",  // Blue-white
        'A' => "\033[38;5;231m", // White
        'F' => "\033[38;5;229m", // Yellow-white
        'G' => "\033[38;5;226m", // Yellow (Sun-like)
        'K' => "\033[38;5;214m", // Orange
        'M' => "\033[38;5;196m", // Red

        // Stars with/without planets
        'star_with_planets' => "\033[38;5;11m",     // Bright yellow
        'star_no_planets' => "\033[38;5;248m",    // Gray

        // Other POI types
        'black_hole' => "\033[38;5;57m",         // Dark purple
        'nebula' => "\033[38;5;165m",        // Purple/pink
        'anomaly' => "\033[38;5;46m",         // Bright green
        'planet' => "\033[38;5;34m",         // Green
        'gas_giant' => "\033[38;5;172m",        // Orange
        'moon' => "\033[38;5;250m",        // Light gray

        // UI elements
        'label' => "\033[38;5;33m",         // Bright blue
        'header' => "\033[38;5;226m",        // Yellow
        'border' => "\033[38;5;240m",        // Dark gray
        'highlight' => "\033[38;5;46m",         // Bright green
        'gate' => "\033[38;5;51m",         // Cyan (warp gate connections)
        'gate_hidden' => "\033[38;5;237m",        // Very dark gray (hidden gates)
        'pirate' => "\033[38;5;196m",        // Red (pirate-controlled lanes)
        'trade' => "\033[38;5;220m",        // Gold (trading hubs)
        'price_low' => "\033[38;5;82m",         // Bright green (good buy price)
        'price_high' => "\033[38;5;196m",        // Red (expensive)
        'supply_high' => "\033[38;5;46m",         // Green (abundant)
        'supply_low' => "\033[38;5;208m",        // Orange (scarce)
    ];

    protected function colorize(string $text, string $colorKey): string
    {
        $color = self::COLORS[$colorKey] ?? self::COLORS['reset'];

        return $color.$text.self::COLORS['reset'];
    }

    protected function clearScreen(): void
    {
        // Use command's output if this is a Command, otherwise use the injected command
        $output = property_exists($this, 'output') ? $this->output : $this->command->getOutput();
        $output->write("\033[2J\033[H");
    }

    /**
     * Get the visual length of a string (excluding ANSI escape codes)
     */
    protected function visualLength(string $text): int
    {
        return strlen(preg_replace('/\033\[[0-9;]*m/', '', $text));
    }

    /**
     * Strip ANSI escape codes from a string
     */
    protected function stripAnsi(string $text): string
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $text);
    }
}

<?php

namespace App\Console\Tui\Widgets;

use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Model\Widget\Borders;
use PhpTui\Tui\Model\Widget\HorizontalAlignment;
use PhpTui\Tui\Style\Color;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Line;

class ControlsWidget
{
    /**
     * Create a controls widget with optional status/error messages
     */
    public static function create(
        string|array $controls,
        ?string $errorMessage = null,
        ?string $statusMessage = null,
        HorizontalAlignment $alignment = HorizontalAlignment::Center
    ): BlockWidget {
        $lines = [];

        // Add control lines
        if (is_string($controls)) {
            $lines[] = Line::fromString($controls)->style(Style::default()->fg(Color::Cyan));
        } else {
            foreach ($controls as $control) {
                $lines[] = Line::fromString($control)->style(Style::default()->fg(Color::Cyan));
            }
        }

        // Add error message if present
        if ($errorMessage) {
            $lines[] = Line::fromString($errorMessage)->style(Style::default()->fg(Color::Red));
        }

        // Add status message if present
        if ($statusMessage) {
            $lines[] = Line::fromString($statusMessage)->style(Style::default()->fg(Color::Green));
        }

        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->title("CONTROLS")
            ->widget(
                ParagraphWidget::fromLines($lines)->alignment($alignment)
            );
    }

    /**
     * Create controls widget with custom alignment
     */
    public static function createLeft(
        string|array $controls,
        ?string $errorMessage = null,
        ?string $statusMessage = null
    ): BlockWidget {
        return self::create($controls, $errorMessage, $statusMessage, HorizontalAlignment::Left);
    }
}

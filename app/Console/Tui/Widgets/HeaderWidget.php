<?php

namespace App\Console\Tui\Widgets;

use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Model\Widget\Borders;
use PhpTui\Tui\Model\Widget\BorderType;
use PhpTui\Tui\Model\Widget\HorizontalAlignment;
use PhpTui\Tui\Style\Color;
use PhpTui\Tui\Style\Style;

class HeaderWidget
{
    /**
     * Create a standard header widget
     */
    public static function create(string $title = "SPACE WARS 3002 - PLAYER INTERFACE"): BlockWidget
    {
        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->borderType(BorderType::Rounded)
            ->borderStyle(Style::default()->fg(Color::Cyan))
            ->widget(
                ParagraphWidget::fromString($title)
                    ->style(Style::default()->fg(Color::Yellow)->bold())
                    ->alignment(HorizontalAlignment::Center)
            );
    }
}

<?php

namespace App\Console\Tui\Traits;

use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Model\Direction;
use PhpTui\Tui\Model\Layout;
use PhpTui\Tui\Model\Widget\Borders;
use PhpTui\Tui\Model\Widget\BorderType;
use PhpTui\Tui\Style\Color;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Line;

trait TuiRendering
{
    /**
     * Create a layout with given direction and constraints
     */
    protected function createLayout(Direction $direction, array $constraints, $area): array
    {
        return Layout::default()
            ->direction($direction)
            ->constraints($constraints)
            ->split($area);
    }

    /**
     * Render an error screen
     */
    protected function renderError($display, $area, string $title, string $message): void
    {
        $block = BlockWidget::default()
            ->borders(Borders::ALL)
            ->borderType(BorderType::Rounded)
            ->title($title)
            ->titleStyle(Style::default()->fg(Color::Red))
            ->widget(
                ParagraphWidget::fromString($message)
                    ->alignment(\PhpTui\Tui\Model\Widget\HorizontalAlignment::Center)
            );

        $display->render($block, $area);
    }

    /**
     * Create a styled text line
     */
    protected function createStyledLine(string $text, Color $color, bool $bold = false): Line
    {
        $style = Style::default()->fg($color);
        if ($bold) {
            $style = $style->bold();
        }
        return Line::fromString($text)->style($style);
    }

    /**
     * Create a selectable line (with background if selected)
     */
    protected function createSelectableLine(
        string $text,
        bool $isSelected,
        Color $textColor = Color::White,
        Color $selectedBg = Color::Blue
    ): Line {
        $style = Style::default()->fg($textColor);
        if ($isSelected) {
            $style = $style->bg($selectedBg);
        }
        return Line::fromString($text)->style($style);
    }

    /**
     * Create a block widget with standard styling
     */
    protected function createBlock(
        string $title,
        $widget,
        Color $titleColor = Color::Yellow,
        bool $rounded = false
    ): BlockWidget {
        $block = BlockWidget::default()
            ->borders(Borders::ALL)
            ->title($title)
            ->titleStyle(Style::default()->fg($titleColor))
            ->widget($widget);

        if ($rounded) {
            $block = $block->borderType(BorderType::Rounded);
        }

        return $block;
    }
}

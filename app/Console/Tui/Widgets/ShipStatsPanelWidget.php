<?php

namespace App\Console\Tui\Widgets;

use App\Models\Player;
use App\Models\PlayerShip;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\GaugeWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Model\Direction;
use PhpTui\Tui\Model\Layout;
use PhpTui\Tui\Model\Widget\Borders;
use PhpTui\Tui\Model\Widget\BorderType;
use PhpTui\Tui\Style\Color;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Line;

class ShipStatsPanelWidget
{
    /**
     * Render the ship stats panel with sub-layouts
     */
    public static function render(PlayerShip $ship, Player $player, $area, $display): void
    {
        // Create layout for ship stats
        $statsLayout = Layout::default()
            ->direction(Direction::Vertical)
            ->constraints([
                Constraint::length(8),  // Ship info
                Constraint::length(3),  // Fuel gauge
                Constraint::length(3),  // Hull gauge
                Constraint::min(0),     // Other stats
            ])
            ->split($area);

        // Render each section
        self::renderShipInfo($ship, $player, $display, $statsLayout[0]);
        self::renderFuelGauge($ship, $display, $statsLayout[1]);
        self::renderHullGauge($ship, $display, $statsLayout[2]);
        self::renderOtherStats($ship, $display, $statsLayout[3]);
    }

    /**
     * Render ship information section
     */
    private static function renderShipInfo(PlayerShip $ship, Player $player, $display, $area): void
    {
        $shipInfo = [
            Line::fromString("Ship: " . $ship->name)->style(Style::default()->fg(Color::Cyan)),
            Line::fromString("Class: " . $ship->ship->class),
            Line::fromString("Status: " . ucfirst($ship->status))
                ->style(Style::default()->fg($ship->status === 'operational' ? Color::Green : Color::Red)),
            Line::fromString(""),
            Line::fromString("Credits: " . number_format($player->credits, 2))
                ->style(Style::default()->fg(Color::Yellow)),
        ];

        $shipBlock = BlockWidget::default()
            ->borders(Borders::ALL)
            ->borderType(BorderType::Rounded)
            ->title("SHIP STATUS")
            ->titleStyle(Style::default()->fg(Color::Yellow))
            ->widget(ParagraphWidget::fromLines($shipInfo));

        $display->render($shipBlock, $area);
    }

    /**
     * Render fuel gauge
     */
    private static function renderFuelGauge(PlayerShip $ship, $display, $area): void
    {
        $fuelRatio = $ship->max_fuel > 0 ? $ship->current_fuel / $ship->max_fuel : 0;

        $fuelGauge = BlockWidget::default()
            ->borders(Borders::ALL)
            ->title("FUEL")
            ->widget(
                GaugeWidget::default()
                    ->ratio($fuelRatio)
                    ->label("Fuel: {$ship->current_fuel}/{$ship->max_fuel}")
                    ->gaugeStyle(Style::default()->fg(
                        $fuelRatio > 0.7 ? Color::Green : ($fuelRatio > 0.3 ? Color::Yellow : Color::Red)
                    ))
            );

        $display->render($fuelGauge, $area);
    }

    /**
     * Render hull gauge
     */
    private static function renderHullGauge(PlayerShip $ship, $display, $area): void
    {
        $hullRatio = $ship->max_hull > 0 ? $ship->hull / $ship->max_hull : 0;

        $hullGauge = BlockWidget::default()
            ->borders(Borders::ALL)
            ->title("HULL")
            ->widget(
                GaugeWidget::default()
                    ->ratio($hullRatio)
                    ->label("Hull: {$ship->hull}/{$ship->max_hull}")
                    ->gaugeStyle(Style::default()->fg(
                        $hullRatio > 0.7 ? Color::Green : ($hullRatio > 0.3 ? Color::Yellow : Color::Red)
                    ))
            );

        $display->render($hullGauge, $area);
    }

    /**
     * Render other stats section
     */
    private static function renderOtherStats(PlayerShip $ship, $display, $area): void
    {
        $otherStats = [
            Line::fromString("Cargo: {$ship->current_cargo}/{$ship->cargo_hold}"),
            Line::fromString("Weapons: {$ship->weapons}"),
            Line::fromString("Sensors: {$ship->sensors}"),
            Line::fromString("Warp Drive: {$ship->warp_drive}"),
        ];

        $otherBlock = BlockWidget::default()
            ->borders(Borders::ALL)
            ->title("COMPONENTS")
            ->widget(ParagraphWidget::fromLines($otherStats));

        $display->render($otherBlock, $area);
    }
}

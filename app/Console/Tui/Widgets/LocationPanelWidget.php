<?php

namespace App\Console\Tui\Widgets;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Player;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Model\Widget\Borders;
use PhpTui\Tui\Model\Widget\BorderType;
use PhpTui\Tui\Style\Color;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Line;

class LocationPanelWidget
{
    /**
     * Create a location panel widget
     */
    public static function create(Player $player): BlockWidget
    {
        $location = $player->currentLocation;

        if (!$location) {
            return BlockWidget::default()
                ->borders(Borders::ALL)
                ->title("LOCATION: UNKNOWN")
                ->titleStyle(Style::default()->fg(Color::Yellow))
                ->widget(ParagraphWidget::fromString("No location data available"));
        }

        $lines = self::buildLocationLines($location);

        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->borderType(BorderType::Rounded)
            ->title("CURRENT LOCATION")
            ->titleStyle(Style::default()->fg(Color::Yellow))
            ->widget(ParagraphWidget::fromLines($lines));
    }

    /**
     * Build location information lines
     */
    public static function buildLocationLines($location): array
    {
        $lines = [];

        // Get root star
        $star = $location->type === PointOfInterestType::STAR
            ? $location
            : $location->getRootStar();

        if ($star) {
            $lines[] = Line::fromString("System: " . $star->name)
                ->style(Style::default()->fg(Color::Green));
            $lines[] = Line::fromString("Coordinates: ({$star->x}, {$star->y})");

            if (isset($star->attributes['stellar_class'])) {
                $lines[] = Line::fromString("Class: " . $star->attributes['stellar_class']);
            }

            $lines[] = Line::fromString("");

            // Show planets
            $planets = $star->children;
            if ($planets->isNotEmpty()) {
                $lines[] = Line::fromString("ORBITAL BODIES:")
                    ->style(Style::default()->fg(Color::Yellow));
                foreach ($planets as $planet) {
                    $current = ($planet->id === $location->id) ? " ◄" : "";
                    $lines[] = Line::fromString("  [{$planet->orbital_index}] {$planet->name}{$current}");
                }
            }

            // Trading hub indicator
            if ($star->tradingHub && $star->tradingHub->is_active) {
                $lines[] = Line::fromString("");
                $lines[] = Line::fromString("⚡ Trading Hub Available")
                    ->style(Style::default()->fg(Color::Magenta));
            }
        }

        return $lines;
    }
}

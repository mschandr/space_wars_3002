<?php

namespace App\Console\Tui\Widgets;

use App\Models\PlayerShip;
use Illuminate\Support\Collection;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Model\Widget\Borders;
use PhpTui\Tui\Style\Color;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Line;

class GateListWidget
{
    /**
     * Create a gate list widget with fuel costs
     */
    public static function create(
        Collection $gates,
        PlayerShip $ship,
        int $selectedIndex
    ): BlockWidget {
        $items = [];

        foreach ($gates as $index => $gate) {
            $destination = $gate->destinationPoi;
            $distance = $gate->distance ?? $gate->calculateDistance();
            $fuelCost = self::calculateFuelCost($distance, $ship);
            $canAfford = $ship->current_fuel >= $fuelCost;

            $line = sprintf(
                "%-30s Distance: %6.1f  Fuel: %3d %s",
                $destination->name,
                $distance,
                $fuelCost,
                $canAfford ? '' : '[INSUFFICIENT FUEL]'
            );

            $style = Style::default()->fg($canAfford ? Color::White : Color::DarkGray);
            if ($index === $selectedIndex) {
                $style = $style->bg(Color::Blue);
            }

            // Add destination type info
            $typeInfo = "    Type: " . $destination->type->name;
            if ($destination->tradingHub && $destination->tradingHub->is_active) {
                $typeInfo .= " [Trading Hub]";
            }

            $items[] = Line::fromString($line)->style($style);
            $items[] = Line::fromString($typeInfo)->style(
                Style::default()->fg(Color::DarkGray)
            );
        }

        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->title("AVAILABLE DESTINATIONS")
            ->titleStyle(Style::default()->fg(Color::Yellow))
            ->widget(ParagraphWidget::fromLines($items));
    }

    /**
     * Calculate fuel cost for travel
     */
    private static function calculateFuelCost(float $distance, PlayerShip $ship): int
    {
        // Base fuel cost is distance divided by 10
        $baseCost = (int) ceil($distance / 10);

        // Warp drive reduces fuel consumption
        $efficiency = $ship->warp_drive ?? 1;
        $fuelCost = max(1, (int) floor($baseCost / $efficiency));

        return $fuelCost;
    }
}

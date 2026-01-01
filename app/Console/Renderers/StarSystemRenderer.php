<?php

namespace App\Console\Renderers;

use App\Console\Traits\ConsoleColorizer;
use App\Models\PointOfInterest;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class StarSystemRenderer
{
    use ConsoleColorizer;

    public function __construct(
        private Command $command,
        private int $termWidth
    ) {}

    public function render(PointOfInterest $star, Collection $gates): void
    {
        $this->clearScreen();
        $this->renderHeader($star);
        $this->renderStarDetails($star);
        $this->renderOrbitalBodies($star);
        $this->renderWarpGates($star, $gates);
        $this->renderTradingHubSummary($star);

        $this->command->newLine();
        $this->command->line($this->colorize('  Press [ESC] or [q] to return to galaxy view', 'dim'));
    }

    private function renderHeader(PointOfInterest $star): void
    {
        $this->command->line($this->colorize('═' . str_repeat('═', $this->termWidth - 2) . '═', 'border'));
        $this->command->line(
            $this->colorize('  STAR SYSTEM: ', 'header') .
            $this->colorize(strtoupper($star->name), 'highlight')
        );
        $this->command->line($this->colorize('═' . str_repeat('═', $this->termWidth - 2) . '═', 'border'));
        $this->command->newLine();
    }

    private function renderStarDetails(PointOfInterest $star): void
    {
        $stellarClass = $star->attributes['stellar_class'] ?? 'Unknown';
        $temperature  = $star->attributes['temperature'] ?? 'Unknown';

        $this->command->line($this->colorize('  Star Classification: ', 'label') .
                           $this->colorize($stellarClass, $stellarClass));

        // Format temperature only if it's numeric
        $tempDisplay = is_numeric($temperature) ? number_format($temperature) . ' K' : $temperature;
        $this->command->line($this->colorize('  Temperature: ', 'label') . $tempDisplay);

        $this->command->line($this->colorize('  Coordinates: ', 'label') .
                           "({$star->x}, {$star->y})");
        $this->command->newLine();
    }

    private function renderOrbitalBodies(PointOfInterest $star): void
    {
        $children = $star->children()->orderBy('orbital_index')->get();

        if ($children->isEmpty()) {
            $this->command->line($this->colorize('  No orbital bodies detected.', 'dim'));
        } else {
            $this->command->line($this->colorize('  ORBITAL BODIES:', 'header'));
            $this->command->newLine();

            foreach ($children as $child) {
                $this->renderOrbitalBody($child, 2);
            }
        }
    }

    private function renderOrbitalBody(PointOfInterest $poi, int $indent = 0): void
    {
        $prefix = str_repeat(' ', $indent);
        $icon = $poi->getDisplayIcon();
        $color = $poi->getDisplayColor();
        $orbitalIndex = $poi->orbital_index ?? '?';

        $line = $prefix . $this->colorize($icon, $color) . ' ' .
                $this->colorize("[$orbitalIndex]", 'label') . ' ' .
                $this->colorize($poi->name, $color) . ' ' .
                $this->colorize('(' . $poi->type->name . ')', 'dim');

        if (isset($poi->attributes['orbital_distance_au'])) {
            $distance = number_format($poi->attributes['orbital_distance_au'], 2);
            $line .= $this->colorize(" - {$distance} AU", 'dim');
        }

        if (isset($poi->attributes['mass_jupiter'])) {
            $mass = number_format($poi->attributes['mass_jupiter'], 2);
            $line .= $this->colorize(" - {$mass} M☉", 'dim');
        }

        $this->command->line($line);

        // Render trading hub if present
        if ($poi->tradingHub && $poi->tradingHub->is_active) {
            $hub = $poi->tradingHub;
            $hubLine = str_repeat(' ', $indent + 2) .
                       $this->colorize('⚓', 'trade') . ' ' .
                       $this->colorize('Trading Hub: ', 'label') .
                       $this->colorize($hub->name, 'trade') . ' ' .
                       $this->colorize("(Tier {$hub->tier})", 'dim');
            $this->command->line($hubLine);
        }

        // Render moons
        $moons = $poi->children()->orderBy('orbital_index')->get();
        if ($moons->isNotEmpty()) {
            foreach ($moons as $moon) {
                $this->renderOrbitalBody($moon, $indent + 4);
            }
        }
    }

    private function renderWarpGates(PointOfInterest $star, Collection $gates): void
    {
        $this->command->newLine();
        $outgoingGates = $gates->filter(fn($gate) => $gate->source_poi_id === $star->id);

        if ($outgoingGates->isEmpty()) {
            $this->command->line($this->colorize('  WARP GATES: ', 'header') .
                               $this->colorize('None', 'dim'));
            return;
        }

        $this->command->line($this->colorize('  WARP GATES:', 'header'));
        $this->command->newLine();

        foreach ($outgoingGates as $gate) {
            $destination = $gate->destinationPoi;
            if (!$destination) {
                continue;
            }

            $statusIcon = $gate->is_hidden ? '🔒' : '→';
            $statusText = $gate->is_hidden ? ' (Hidden)' : '';
            $distance = number_format($gate->distance, 2);

            $line = '    ' . $this->colorize($statusIcon, 'gate') . ' ' .
                    $this->colorize($destination->name, 'highlight') . ' ' .
                    $this->colorize("({$distance} units)", 'dim') .
                    $this->colorize($statusText, 'gate_hidden');

            $this->command->line($line);
        }
    }

    private function renderTradingHubSummary(PointOfInterest $star): void
    {
        $hub = $star->tradingHub;
        if (!$hub) {
            return;
        }

        $this->command->newLine();
        $this->command->line($this->colorize('  TRADING HUB:', 'header'));
        $this->command->newLine();

        $typeIcon = $hub->getTypeIcon();
        $salvageIcon = $hub->has_salvage_yard ? ' 🔧' : '';

        $this->command->line('    ' . $this->colorize($typeIcon, 'trade') . ' ' .
                           $this->colorize($hub->name, 'trade') . ' ' .
                           $this->colorize('(' . ucfirst($hub->type) . ')', 'dim') .
                           $salvageIcon);

        $this->command->line('    ' . $this->colorize('Minerals: ', 'label') .
                           $hub->inventories()->count() . ' types');
        $this->command->line('    ' . $this->colorize('Tax Rate: ', 'label') .
                           $hub->tax_rate . '%');

        if ($hub->has_salvage_yard) {
            $this->command->line('    ' . $this->colorize('Services: ', 'label') .
                               'Ship Salvage & Upgrades Available');
        }

        $this->command->newLine();
        $this->command->line('    ' . $this->colorize('Press [t] to view detailed prices', 'dim'));
    }
}

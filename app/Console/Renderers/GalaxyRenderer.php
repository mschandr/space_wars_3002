<?php

namespace App\Console\Renderers;

use App\Console\Traits\ConsoleColorizer;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class GalaxyRenderer
{
    use ConsoleColorizer;

    public function __construct(
        private readonly Command $command,
        private readonly int     $termWidth,
        private readonly int     $termHeight
    )
    {
    }

    public function render(
        Galaxy     $galaxy,
        Collection $pois,
        Collection $gates,
        bool       $showGates,
        array      &$poiMap,
        Collection $pirates = new Collection()
    ): void
    {
        $this->clearScreen();
        $this->renderHeader($galaxy, $pois);

        // Create empty canvas
        // Account for: header (3) + legend (7) + controls (5) = 15 lines of overhead
        $canvas = array_fill(0, $this->termHeight - 15, array_fill(0, $this->termWidth, ' '));

        // Get universe-level celestial objects
        $celestialObjects = $pois->filter(fn($poi) => $poi->type->isUniverseType())->values();

        if ($celestialObjects->isEmpty()) {
            $this->command->error('No celestial objects found in this galaxy.');
            return;
        }

        // Calculate bounds and scale
        [$minX, $maxX, $minY, $maxY, $scaleX, $scaleY] = $this->calculateScale($celestialObjects);

        // Plot points
        $this->plotPoints($canvas, $celestialObjects, $minX, $minY, $scaleX, $scaleY, $poiMap);

        // Draw gates if enabled
        if ($showGates && $gates->isNotEmpty()) {
            $this->drawGateConnections($canvas, $celestialObjects, $gates, $pirates, $minX, $minY, $scaleX, $scaleY);
        }

        // Render canvas
        foreach ($canvas as $row) {
            $this->command->line(implode('', $row));
        }

        $this->renderLegend($pirates->count());
    }

    private function renderHeader(Galaxy $galaxy, Collection $pois): void
    {
        $this->command->line($this->colorize('╔' . str_repeat('═', $this->termWidth - 2) . '╗', 'border'));

        $title   = "  GALAXY: {$galaxy->name}  ";
        $stats   = "POIs: {$pois->count()} | Size: {$galaxy->width}x{$galaxy->height}  ";
        $padding = $this->termWidth - strlen($title) - strlen($stats) - 2;

        $this->command->line(
            $this->colorize('║', 'border') .
            $this->colorize($title, 'header') .
            str_repeat(' ', max(0, $padding)) .
            $this->colorize($stats, 'dim') .
            $this->colorize('║', 'border')
        );

        $this->command->line($this->colorize('╚' . str_repeat('═', $this->termWidth - 2) . '╝', 'border'));
    }

    private function calculateScale(Collection $celestialObjects): array
    {
        $minX = $celestialObjects->min('x');
        $maxX = $celestialObjects->max('x');
        $minY = $celestialObjects->min('y');
        $maxY = $celestialObjects->max('y');

        // Add padding (10% on each side)
        $rangeX   = max($maxX - $minX, 1);
        $rangeY   = max($maxY - $minY, 1);
        $paddingX = $rangeX * 0.1;
        $paddingY = $rangeY * 0.1;

        $minX -= $paddingX;
        $maxX += $paddingX;
        $minY -= $paddingY;
        $maxY += $paddingY;

        $rangeX = $maxX - $minX;
        $rangeY = $maxY - $minY;

        $scaleX = ($this->termWidth - 1) / $rangeX;
        $scaleY = ($this->termHeight - 16) / $rangeY;

        return [$minX, $maxX, $minY, $maxY, $scaleX, $scaleY];
    }

    private function plotPoints(
        array      &$canvas,
        Collection $celestialObjects,
        float      $minX,
        float      $minY,
        float      $scaleX,
        float      $scaleY,
        array      &$poiMap
    ): void
    {
        $starIndex = 0;

        foreach ($celestialObjects as $poi) {
            $x = (int)round(($poi->x - $minX) * $scaleX);
            $y = (int)round(($poi->y - $minY) * $scaleY);

            $x = max(0, min($this->termWidth - 1, $x));
            $y = max(0, min($this->termHeight - 16, $y));

            $color = $poi->getCelestialColor();

            if ($poi->type === PointOfInterestType::STAR && $starIndex < 36) {
                $char               = $this->indexToChar($starIndex);
                $poiMap[$starIndex] = $poi;
                $starIndex++;
            } else {
                $char = $poi->getDisplayIcon();
            }

            $canvas[$y][$x] = $this->colorize($char, $color);
        }
    }

    private function indexToChar(int $index): string
    {
        // Available characters: 0-9, then a-z excluding reserved letters (g, h, r, t)
        $availableChars = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $reservedLetters = ['g', 'h', 'r', 't'];

        // Add letters a-z excluding reserved ones
        for ($i = 0; $i < 26; $i++) {
            $letter = chr(ord('a') + $i);
            if (!in_array($letter, $reservedLetters)) {
                $availableChars[] = $letter;
            }
        }

        return $availableChars[$index] ?? '?';
    }

    private function drawGateConnections(
        array      &$canvas,
        Collection $celestialObjects,
        Collection $gates,
        Collection $pirates,
        float      $minX,
        float      $minY,
        float      $scaleX,
        float      $scaleY
    ): void
    {
        foreach ($gates as $gate) {
            $source      = $celestialObjects->firstWhere('id', $gate->source_poi_id);
            $destination = $celestialObjects->firstWhere('id', $gate->destination_poi_id);

            if (!$source || !$destination) {
                continue;
            }

            $x1 = max(0, min($this->termWidth - 1, (int)round(($source->x - $minX) * $scaleX)));
            $y1 = max(0, min($this->termHeight - 16, (int)round(($source->y - $minY) * $scaleY)));
            $x2 = max(0, min($this->termWidth - 1, (int)round(($destination->x - $minX) * $scaleX)));
            $y2 = max(0, min($this->termHeight - 16, (int)round(($destination->y - $minY) * $scaleY)));

            // Check if this gate has pirates
            $hasPirates = $gate->warpLanePirate && $gate->warpLanePirate->is_active;

            $color = $gate->is_hidden ? 'gate_hidden' : ($hasPirates ? 'pirate' : 'gate');
            $this->drawLine($canvas, $x1, $y1, $x2, $y2, $color);
        }
    }

    private function drawLine(array &$canvas, int $x1, int $y1, int $x2, int $y2, string $color): void
    {
        $dx       = abs($x2 - $x1);
        $dy       = abs($y2 - $y1);
        $sx       = $x1 < $x2 ? 1 : -1;
        $sy       = $y1 < $y2 ? 1 : -1;
        $err      = $dx - $dy;
        $lineChar = $this->colorize('·', $color);

        while (true) {
            if ($canvas[$y1][$x1] === ' ') {
                $canvas[$y1][$x1] = $lineChar;
            }

            if ($x1 === $x2 && $y1 === $y2) {
                break;
            }

            $e2 = 2 * $err;
            if ($e2 > -$dy) {
                $err -= $dy;
                $x1  += $sx;
            }
            if ($e2 < $dx) {
                $err += $dx;
                $y1  += $sy;
            }
        }
    }

    private function renderLegend(int $pirateCount = 0): void
    {
        $this->command->newLine();
        $this->command->line($this->colorize('  LEGEND:', 'header'));

        $legends = [
            ['O', 'O', 'Blue Supergiant (Star)'], ['B', 'B', 'Blue-White Giant (Star)'], ['A', 'A', 'White Star'],
            ['F', 'F', 'Yellow-White Star'], ['G', 'G', 'Yellow Star (Sun-like)'], ['K', 'K', 'Orange Dwarf Star'],
            ['M', 'M', 'Red Dwarf Star'], ['◉', 'black_hole', 'Black Hole'], ['∞', 'nebula', 'Nebula'],
            ['?', 'anomaly', 'Anomaly'], ['●', 'planet', 'Rogue Planet'], ['☄', 'highlight', 'Comet'],
            ['·', 'gate', 'Warp Gate ([g] to toggle)'],
        ];

        // Add pirate legend entry if there are pirates
        if ($pirateCount > 0) {
            $legends[] = ['·', 'pirate', "Pirate-controlled lane ({$pirateCount})"];
        }

        $col1 = [];
        $col2 = [];
        $col3 = [];
        $col4 = [];

        foreach ($legends as $i => $legend) {
            [$char, $color, $desc] = $legend;
            $entry = '  ' . $this->colorize($char, $color) . ' = ' . $desc;

            if ($i < 4) {
                $col1[] = $entry;
            } elseif ($i < 8) {
                $col2[] = $entry;
            } elseif ($i < 12) {
                $col3[] = $entry;
            } else {
                $col4[] = $entry;
            }
        }

        for ($i = 0; $i < max(count($col1), count($col2), count($col3), count($col4)); $i++) {
            $left_width         = $this->visualLength($col1[$i] ?? '');
            $center_left_width  = $this->visualLength($col2[$i] ?? '');
            $center_right_width = $this->visualLength($col3[$i] ?? '');
            $right_width        = $this->visualLength($col4[$i] ?? '');

            $left         = ($col1[$i] ?? ''). str_pad('', 45 - $left_width);
            $center_left  = ($col2[$i] ?? ''). str_pad('', 45 - $center_left_width);
            $center_right = ($col3[$i] ?? ''). str_pad('', 35 - $center_right_width);
            $right        = ($col4[$i] ?? ''). str_pad('', 50 - $right_width);

            $this->command->line($left . $center_left . $center_right . $right);
        }
    }
}

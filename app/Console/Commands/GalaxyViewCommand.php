<?php

namespace App\Console\Commands;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Enums\PointsOfInterest\StellarClassification;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class GalaxyViewCommand extends Command
{
    protected $signature = 'galaxy:view {galaxy? : Galaxy ID or UUID}
                            {--width=120 : Terminal width for rendering}
                            {--height=40 : Terminal height for rendering}
                            {--show-hidden : Show hidden points of interest}';

    protected $description = 'Display ASCII visualization of a galaxy with interactive zoom';

    private Galaxy      $galaxy;
    private Collection  $pois;
    private int         $termWidth;
    private int         $termHeight;
    private bool        $showHidden;
    private array       $poiMap = [];
    private int         $currentView = 0; // 0 = galaxy view, >0 = star system ID

    // ANSI color codes
    private const COLORS = [
        'reset' => "\033[0m",
        'bold'  => "\033[1m",
        'dim'   => "\033[2m",

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
        'star_no_planets'   => "\033[38;5;248m",    // Gray

        // Other POI types
        'black_hole'    => "\033[38;5;57m",         // Dark purple
        'nebula'        => "\033[38;5;165m",        // Purple/pink
        'anomaly'       => "\033[38;5;46m",         // Bright green
        'planet'        => "\033[38;5;34m",         // Green
        'gas_giant'     => "\033[38;5;172m",        // Orange
        'moon'          => "\033[38;5;250m",        // Light gray

        // UI elements
        'label'         => "\033[38;5;33m",         // Bright blue
        'header'        => "\033[38;5;226m",        // Yellow
        'border'        => "\033[38;5;240m",        // Dark gray
        'highlight'     => "\033[38;5;46m",         // Bright green
    ];

    public function handle(): int
    {
        $this->termWidth    = (int) $this->option('width');
        $this->termHeight   = (int) $this->option('height');
        $this->showHidden   = $this->option('show-hidden');

        // Load galaxy
        $galaxyId = $this->argument('galaxy');
        $this->galaxy = $galaxyId
            ? Galaxy::where('id', $galaxyId)->orWhere('uuid', $galaxyId)->firstOrFail()
            : Galaxy::latest()->firstOrFail();

        // Load points of interest
        $query = $this->galaxy->pointsOfInterest()->with(['parent', 'children']);
        if (!$this->showHidden) {
            $query->where('is_hidden', false);
        }
        $this->pois = $query->get();

        if ($this->pois->isEmpty()) {
            $this->error('No points of interest found in this galaxy.');
            return self::FAILURE;
        }

        // Start interactive view
        $this->renderGalaxyView();
        $this->interactiveLoop();

        return self::SUCCESS;
    }

    private function interactiveLoop(): void
    {
        $this->info("\n" . $this->colorize('Controls:', 'header'));
        $this->line($this->colorize('  [1-9,a-z]', 'label') . ' - Zoom into numbered/lettered star system');
        $this->line($this->colorize('  [ESC/q]', 'label') . '   - Return to galaxy view or quit');
        $this->line($this->colorize('  [h]', 'label') . '       - Toggle hidden POIs');
        $this->line($this->colorize('  [r]', 'label') . '       - Refresh view');
        $this->newLine();

        // Enable non-blocking input
        system('stty -icanon -echo');

        $running = true;
        while ($running) {
            $char = $this->readChar();

            if ($char === false) {
                usleep(50000); // 50ms
                continue;
            }

            match (true) {
                $char === 'q' || $char === "\033" => $running = $this->handleQuit(),
                $char === 'h' => $this->toggleHidden(),
                $char === 'r' => $this->refreshView(),
                ctype_alnum($char) => $this->handleZoom($char),
                default => null,
            };
        }

        // Restore terminal settings
        system('stty sane');
    }

    private function readChar()
    {
        $read   = [STDIN];
        $write  = null;
        $except = null;
        $result = stream_select($read, $write, $except, 0, 100000);

        if ($result === false || $result === 0) {
            return false;
        }

        return fgetc(STDIN);
    }

    private function handleQuit(): bool
    {
        if ($this->currentView > 0) {
            $this->currentView = 0;
            $this->refreshView();
            return true;
        }

        $this->info("\n" . $this->colorize('Exiting galaxy viewer...', 'header'));
        return false;
    }

    private function toggleHidden(): void
    {
        $this->showHidden = !$this->showHidden;

        // Reload POIs
        $query = $this->galaxy->pointsOfInterest()->with(['parent', 'children']);
        if (!$this->showHidden) {
            $query->where('is_hidden', false);
        }
        $this->pois = $query->get();

        $this->refreshView();
    }

    private function refreshView(): void
    {
        $this->clearScreen();

        if ($this->currentView === 0) {
            $this->renderGalaxyView();
        } else {
            $this->renderStarSystemView($this->currentView);
        }
    }

    private function handleZoom(string $char): void
    {
        if ($this->currentView > 0) {
            // Already zoomed, ignore
            return;
        }

        $index = $this->charToIndex($char);

        if (isset($this->poiMap[$index])) {
            $poi = $this->poiMap[$index];

            // Only zoom into stars
            if ($poi->type === PointOfInterestType::STAR) {
                $this->currentView = $poi->id;
                $this->refreshView();
            }
        }
    }

    private function charToIndex(string $char): int
    {
        if (is_numeric($char)) {
            return (int) $char;
        }

        // a=10, b=11, ..., z=35
        return ord(strtolower($char)) - ord('a') + 10;
    }

    private function indexToChar(int $index): string
    {
        if ($index < 10) {
            return (string) $index;
        }

        return chr(ord('a') + $index - 10);
    }

    private function renderGalaxyView(): void
    {
        $this->clearScreen();

        // Header
        $this->renderHeader();

        // Create empty canvas
        $canvas = array_fill(0, $this->termHeight - 6, array_fill(0, $this->termWidth, ' '));

        // Scale factors
        $scaleX = $this->termWidth / $this->galaxy->width;
        $scaleY = ($this->termHeight - 6) / $this->galaxy->height;

        // Plot points and assign identifiers
        $this->poiMap = [];
        $stars = $this->pois->where('type', PointOfInterestType::STAR)->values();

        foreach ($stars as $index => $poi) {
            if ($index >= 36) break; // Max 36 stars (0-9, a-z)

            $x = (int) round($poi->x * $scaleX);
            $y = (int) round($poi->y * $scaleY);

            // Ensure within bounds
            $x = max(0, min($this->termWidth - 1, $x));
            $y = max(0, min($this->termHeight - 7, $y));

            $char   = $this->indexToChar($index);
            $color  = $this->getStarColor($poi);

            $canvas[$y][$x]         = $this->colorize($char, $color);
            $this->poiMap[$index]   = $poi;
        }

        // Render canvas
        foreach ($canvas as $row) {
            $this->line(implode('', $row));
        }

        // Footer with legend
        $this->renderLegend();
    }

    private function renderStarSystemView(int $starId): void
    {
        $star = $this->pois->firstWhere('id', $starId);

        if (!$star) {
            $this->currentView = 0;
            $this->refreshView();
            return;
        }

        $this->clearScreen();

        // Header
        $this->line($this->colorize('═' . str_repeat('═', $this->termWidth - 2) . '═', 'border'));
        $this->line(
            $this->colorize('  STAR SYSTEM: ', 'header') .
            $this->colorize(strtoupper($star->name), 'highlight')
        );
        $this->line($this->colorize('═' . str_repeat('═', $this->termWidth - 2) . '═', 'border'));
        $this->newLine();

        // Star details
        $stellarClass = $star->attributes['stellar_class'] ?? 'Unknown';
        $temperature  = $star->attributes['temperature'] ?? 'Unknown';
        $hasChildren  = $star->children()->exists();

        $this->line($this->colorize('  Star Classification: ', 'label') . $this->colorize($stellarClass, $stellarClass));
        $this->line($this->colorize('  Temperature: ', 'label') . number_format($temperature) . ' K');
        $this->line($this->colorize('  Coordinates: ', 'label') . "({$star->x}, {$star->y})");
        $this->newLine();

        // Orbital bodies
        $children = $star->children()->orderBy('orbital_index')->get();

        if ($children->isEmpty()) {
            $this->line($this->colorize('  No orbital bodies detected.', 'dim'));
        } else {
            $this->line($this->colorize('  ORBITAL BODIES:', 'header'));
            $this->newLine();

            foreach ($children as $child) {
                $this->renderOrbitalBody($child, 2);
            }
        }

        $this->newLine();
        $this->line($this->colorize('  Press [ESC] or [q] to return to galaxy view', 'dim'));
    }

    private function renderOrbitalBody(PointOfInterest $poi, int $indent = 0): void
    {
        $prefix         = str_repeat(' ', $indent);
        $icon           = $this->getPoiIcon($poi->type);
        $color          = $this->getPoiColor($poi->type);
        $name           = $poi->name;
        $orbitalIndex   = $poi->orbital_index ?? '?';

        // Main line
        $line = $prefix . $this->colorize($icon, $color) . ' ' .
                $this->colorize("[$orbitalIndex]", 'label') . ' ' .
                $this->colorize($name, $color) . ' ' .
                $this->colorize('(' . $poi->type->name . ')', 'dim');

        // Add orbital distance if available
        if (isset($poi->attributes['orbital_distance_au'])) {
            $distance = number_format($poi->attributes['orbital_distance_au'], 2);
            $line .= $this->colorize(" - {$distance} AU", 'dim');
        }

        // Add mass for gas giants
        if (isset($poi->attributes['mass_jupiter'])) {
            $mass = number_format($poi->attributes['mass_jupiter'], 2);
            $line .= $this->colorize(" - {$mass} M☉", 'dim');
        }

        $this->line($line);

        // Render moons if any
        $moons = $poi->children()->orderBy('orbital_index')->get();
        if ($moons->isNotEmpty()) {
            foreach ($moons as $moon) {
                $this->renderOrbitalBody($moon, $indent + 4);
            }
        }
    }

    private function renderHeader(): void
    {
        $this->line($this->colorize('╔' . str_repeat('═', $this->termWidth - 2) . '╗', 'border'));

        $title = "  GALAXY: {$this->galaxy->name}  ";
        $stats = "POIs: {$this->pois->count()} | Size: {$this->galaxy->width}x{$this->galaxy->height}  ";
        $padding = $this->termWidth - strlen($title) - strlen($stats);

        $this->line(
            $this->colorize('║', 'border') .
            $this->colorize($title, 'header') .
            str_repeat(' ', max(0, $padding)) .
            $this->colorize($stats, 'dim') .
            $this->colorize('║', 'border')
        );

        $this->line($this->colorize('╚' . str_repeat('═', $this->termWidth - 2) . '╝', 'border'));
    }

    private function renderLegend(): void
    {
        $this->newLine();
        $this->line($this->colorize('  LEGEND:', 'header'));

        $legends = [
            ['O', 'O', 'Blue Supergiant'],
            ['B', 'B', 'Blue-White Giant'],
            ['A', 'A', 'White Star'],
            ['F', 'F', 'Yellow-White'],
            ['G', 'G', 'Yellow Star (Sun-like)'],
            ['K', 'K', 'Orange Dwarf'],
            ['M', 'M', 'Red Dwarf'],
        ];

        $col1 = [];
        $col2 = [];

        foreach ($legends as $i => $legend) {
            [$char, $color, $desc] = $legend;
            $entry = '  ' . $this->colorize($char, $color) . ' = ' . $desc;

            if ($i < 4) {
                $col1[] = $entry;
            } else {
                $col2[] = $entry;
            }
        }

        // Display in two columns
        for ($i = 0; $i < max(count($col1), count($col2)); $i++) {
            $left = $col1[$i] ?? str_repeat(' ', 30);
            $right = $col2[$i] ?? '';
            $this->line($left . '    ' . $right);
        }
    }

    private function getStarColor(PointOfInterest $star): string
    {
        $stellarClass = $star->attributes['stellar_class'] ?? null;

        if ($stellarClass && isset(self::COLORS[$stellarClass])) {
            return $stellarClass;
        }

        // Fallback: check if star has children (planets)
        return $star->children()->exists() ? 'star_with_planets' : 'star_no_planets';
    }

    private function getPoiColor(PointOfInterestType $type): string
    {
        return match ($type) {
            PointOfInterestType::GAS_GIANT, PointOfInterestType::HOT_JUPITER, PointOfInterestType::ICE_GIANT    => 'gas_giant',
            PointOfInterestType::MOON                                                                           => 'moon',
            PointOfInterestType::BLACK_HOLE, PointOfInterestType::SUPER_MASSIVE_BLACK_HOLE                      => 'black_hole',
            PointOfInterestType::NEBULA                                                                         => 'nebula',
            PointOfInterestType::ANOMALY                                                                        => 'anomaly',
            default                                                                                             => 'planet',
        };
    }

    private function getPoiIcon(PointOfInterestType $type): string
    {
        return match ($type) {
            PointOfInterestType::STAR                                           => '★',
            PointOfInterestType::GAS_GIANT, PointOfInterestType::HOT_JUPITER    => '◉',
            PointOfInterestType::ICE_GIANT                                      => '◎',
            PointOfInterestType::TERRESTRIAL, PointOfInterestType::SUPER_EARTH  => '●',
            PointOfInterestType::LAVA                                           => '◆',
            PointOfInterestType::OCEAN                                          => '◐',
            PointOfInterestType::MOON                                           => '○',
            PointOfInterestType::ASTEROID_BELT                                  => '∴',
            PointOfInterestType::ASTEROID                                       => '·',
            PointOfInterestType::BLACK_HOLE                                     => '◯',
            PointOfInterestType::SUPER_MASSIVE_BLACK_HOLE                       => '◉',
            PointOfInterestType::NEBULA                                         => '∞',
            PointOfInterestType::ANOMALY                                        => '?',
            default                                                             => '•',
        };
    }

    private function colorize(string $text, string $colorKey): string
    {
        $color = self::COLORS[$colorKey] ?? self::COLORS['reset'];
        return $color . $text . self::COLORS['reset'];
    }

    private function clearScreen(): void
    {
        $this->output->write("\033[2J\033[H");
    }
}

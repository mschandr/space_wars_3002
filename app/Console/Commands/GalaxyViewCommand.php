<?php

namespace App\Console\Commands;

use App\Console\Renderers\GalaxyRenderer;
use App\Console\Renderers\SectorRenderer;
use App\Console\Renderers\StarSystemRenderer;
use App\Console\Renderers\TradingHubRenderer;
use App\Console\Traits\ConsoleColorizer;
use App\Console\Traits\TerminalInputHandler;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Sector;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class GalaxyViewCommand extends Command
{
    use ConsoleColorizer;
    use TerminalInputHandler;

    protected $signature = 'galaxy:view {galaxy? : Galaxy ID or UUID}
                            {--width=120 : Terminal width for rendering}
                            {--height=40 : Terminal height for rendering}
                            {--show-hidden : Show hidden points of interest}
                            {--show-gates : Show warp gate connections}';

    protected $description = 'Display ASCII visualization of a galaxy with interactive zoom';

    private Galaxy      $galaxy;
    private Collection  $pois;
    private Collection  $sectors;
    private Collection  $gates;
    private Collection  $pirates;
    private int         $termWidth;
    private int         $termHeight;
    private bool        $showHidden;
    private bool        $showGates;
    private array       $poiMap = [];
    private array       $sectorMap = [];
    private int         $viewLevel = 0; // 0 = sector grid, 1 = sector detail, 2 = star system
    private ?Sector     $currentSector = null;
    private ?int        $currentStarId = null;

    // Renderers
    private SectorRenderer $sectorRenderer;
    private GalaxyRenderer $galaxyRenderer;
    private StarSystemRenderer $starSystemRenderer;
    private TradingHubRenderer $tradingHubRenderer;

    public function handle(): int
    {
        $this->termWidth    = (int) $this->option('width');
        $this->termHeight   = (int) $this->option('height');
        $this->showHidden   = $this->option('show-hidden');
        $this->showGates    = $this->option('show-gates');

        // Initialize renderers
        $this->sectorRenderer = new SectorRenderer($this, $this->termWidth, $this->termHeight);
        $this->galaxyRenderer = new GalaxyRenderer($this, $this->termWidth, $this->termHeight);
        $this->starSystemRenderer = new StarSystemRenderer($this, $this->termWidth);
        $this->tradingHubRenderer = new TradingHubRenderer($this, $this->termWidth);

        // Load galaxy
        $galaxyId = $this->argument('galaxy');
        $this->galaxy = $galaxyId
            ? Galaxy::where('id', $galaxyId)->orWhere('uuid', $galaxyId)->firstOrFail()
            : Galaxy::latest()->firstOrFail();

        // Load data
        $this->loadGalaxyData();

        if ($this->pois->isEmpty()) {
            $this->error('No points of interest found in this galaxy.');
            return self::FAILURE;
        }

        // Start interactive view
        $this->renderCurrentView();
        $this->interactiveLoop();

        return self::SUCCESS;
    }

    private function loadGalaxyData(): void
    {
        // Load sectors
        $this->sectors = $this->galaxy->sectors()
            ->orderBy('grid_y')
            ->orderBy('grid_x')
            ->get();

        // Load points of interest
        $query = $this->galaxy->pointsOfInterest()->with(['parent', 'children.tradingHub', 'tradingHub', 'sector']);
        if (!$this->showHidden) {
            $query->where('is_hidden', false);
        }
        $this->pois = $query->get();

        // Load warp gates with pirate data
        $gateQuery = $this->galaxy->warpGates()->with([
            'sourcePoi',
            'destinationPoi',
            'warpLanePirate.captain.faction'
        ]);
        if (!$this->showHidden) {
            $gateQuery->where('is_hidden', false);
        }
        $this->gates = $gateQuery->get();

        // Extract active pirates for quick access
        $this->pirates = $this->gates
            ->pluck('warpLanePirate')
            ->filter()
            ->where('is_active', true);
    }

    private function interactiveLoop(): void
    {
        $this->displayControls();

        $running = true;
        while ($running) {
            // In sector grid view, use line input for multi-char codes
            if ($this->viewLevel === 0) {
                $this->info("\nEnter command: ");
                system('stty sane'); // Restore normal input
                $input = trim(fgets(STDIN));

                match (true) {
                    $input === 'q' => $running = false,
                    $input === 'h' => $this->toggleHidden(),
                    $input === 'g' => $this->toggleGates(),
                    $input === 'r' => $this->refreshView(),
                    $input !== '' => $this->handleZoom($input),
                    default => null,
                };
            } else {
                // In other views, use single-char non-blocking input
                system('stty -icanon -echo');

                $char = $this->readChar();

                if ($char === false) {
                    usleep(50000); // 50ms
                    continue;
                }

                match (true) {
                    $char === 'q' || $char === "\033" => $running = $this->handleQuit(),
                    $char === 'h' => $this->toggleHidden(),
                    $char === 'g' => $this->toggleGates(),
                    $char === 'r' => $this->refreshView(),
                    $char === 't' => $this->handleTradingHub(),
                    ctype_alnum($char) => $this->handleZoom($char),
                    default => null,
                };

                system('stty sane');
            }
        }

        // Restore terminal settings
        system('stty sane');
    }

    private function displayControls(): void
    {
        $this->info("\n" . $this->colorize('Controls:', 'header'));

        $col1Width = 40;
        $col2Width = 40;

        if ($this->viewLevel === 0) {
            // Sector grid controls
            $this->line(
                str_pad($this->colorize('  [0-9,a-z]', 'label') . ' - View sector', $col1Width) .
                str_pad($this->colorize('  [h]', 'label') . ' - Toggle hidden POIs', $col2Width) .
                $this->colorize('  [r]', 'label') . ' - Refresh view'
            );
            $this->line(
                str_pad($this->colorize('  [g]', 'label') . '       - Toggle warp gates', $col1Width) .
                str_pad('', $col2Width) .
                $this->colorize('  [q]', 'label') . ' - Quit'
            );
        } elseif ($this->viewLevel === 1) {
            // Sector detail controls
            $this->line(
                str_pad($this->colorize('  [0-9,a-z]', 'label') . ' - Zoom to star', $col1Width) .
                str_pad($this->colorize('  [h]', 'label') . ' - Toggle hidden POIs', $col2Width) .
                $this->colorize('  [r]', 'label') . ' - Refresh view'
            );
            $this->line(
                str_pad($this->colorize('  [g]', 'label') . '       - Toggle warp gates', $col1Width) .
                str_pad('', $col2Width) .
                $this->colorize('  [ESC]', 'label') . ' - Back to sectors'
            );
        } else {
            // Star system controls
            $this->line(
                str_pad($this->colorize('  [t]', 'label') . '       - View trading hub', $col1Width) .
                str_pad($this->colorize('  [h]', 'label') . ' - Toggle hidden POIs', $col2Width) .
                $this->colorize('  [r]', 'label') . ' - Refresh view'
            );
            $this->line(
                str_pad('', $col1Width) .
                str_pad('', $col2Width) .
                $this->colorize('  [ESC]', 'label') . ' - Back to sector'
            );
        }

        $this->newLine();
    }

    private function handleQuit(): bool
    {
        // Navigate back through view levels
        if ($this->viewLevel === 2) {
            // Star system -> Sector detail
            $this->viewLevel = 1;
            $this->currentStarId = null;
            $this->refreshView();
            return true;
        } elseif ($this->viewLevel === 1) {
            // Sector detail -> Sector grid
            $this->viewLevel = 0;
            $this->currentSector = null;
            $this->refreshView();
            return true;
        }

        // At sector grid level, quit
        $this->info("\n" . $this->colorize('Exiting galaxy viewer...', 'header'));
        return false;
    }

    private function toggleHidden(): void
    {
        $this->showHidden = !$this->showHidden;
        $this->loadGalaxyData();
        $this->refreshView();
    }

    private function toggleGates(): void
    {
        $this->showGates = !$this->showGates;
        $this->refreshView();
    }

    private function refreshView(): void
    {
        $this->renderCurrentView();
    }

    private function renderCurrentView(): void
    {
        if ($this->viewLevel === 0) {
            // Sector grid view
            if ($this->sectors->isEmpty()) {
                $this->warn("\nNo sectors found. Run: php artisan galaxy:generate-sectors {$this->galaxy->id}\n");
                $this->info("Falling back to full galaxy view...\n");
                // Fallback to old galaxy view
                $this->galaxyRenderer->render(
                    $this->galaxy,
                    $this->pois,
                    $this->gates,
                    $this->showGates,
                    $this->poiMap,
                    $this->pirates
                );
            } else {
                $this->sectorRenderer->render(
                    $this->galaxy,
                    $this->sectors,
                    $this->sectorMap
                );
            }
        } elseif ($this->viewLevel === 1) {
            // Sector detail view (stars in sector)
            if (!$this->currentSector) {
                $this->viewLevel = 0;
                $this->refreshView();
                return;
            }

            // Filter POIs by sector
            $sectorPois = $this->pois->filter(fn($poi) => $poi->sector_id === $this->currentSector->id);

            // Debug: Show count
            $starCount = $sectorPois->filter(fn($poi) => $poi->type === \App\Enums\PointsOfInterest\PointOfInterestType::STAR)->count();
            $this->info("Sector {$this->currentSector->name}: {$sectorPois->count()} POIs ({$starCount} stars)");

            if ($sectorPois->isEmpty()) {
                $this->warn("No POIs found in this sector. Press ESC to go back.");
                $this->info("\nSector bounds: [{$this->currentSector->x_min}-{$this->currentSector->x_max}, {$this->currentSector->y_min}-{$this->currentSector->y_max}]");
                return;
            }

            $this->galaxyRenderer->render(
                $this->galaxy,
                $sectorPois,
                $this->gates,
                $this->showGates,
                $this->poiMap,
                $this->pirates
            );
        } else {
            // Star system view
            $star = $this->pois->firstWhere('id', $this->currentStarId);
            if ($star) {
                $this->starSystemRenderer->render($star, $this->gates);
            } else {
                $this->viewLevel = 1;
                $this->currentStarId = null;
                $this->refreshView();
            }
        }
    }

    private function handleZoom(string $char): void
    {
        $index = $this->charToIndex($char);

        if ($this->viewLevel === 0) {
            // Zooming from sector grid to sector detail
            if (isset($this->sectorMap[$index])) {
                $this->currentSector = $this->sectorMap[$index];
                $this->viewLevel = 1;
                $this->refreshView();
            }
        } elseif ($this->viewLevel === 1) {
            // Zooming from sector detail to star system
            if (isset($this->poiMap[$index])) {
                $poi = $this->poiMap[$index];

                if ($poi->type === PointOfInterestType::STAR) {
                    $this->currentStarId = $poi->id;
                    $this->viewLevel = 2;
                    $this->refreshView();
                }
            }
        }
    }

    private function handleTradingHub(): void
    {
        if ($this->viewLevel !== 2) {
            return; // Not viewing a star system
        }

        $star = $this->pois->firstWhere('id', $this->currentStarId);
        if (!$star) {
            return;
        }

        // Check if star has direct trading hub
        $hub = $star->tradingHub;

        // If not, look for trading hubs on planets in this system
        if (!$hub) {
            $planetsWithHubs = $star->children->filter(fn($planet) => $planet->tradingHub && $planet->tradingHub->is_active);

            if ($planetsWithHubs->isEmpty()) {
                return; // No trading hubs in this system
            }

            // If there's only one hub, use it
            if ($planetsWithHubs->count() === 1) {
                $hub = $planetsWithHubs->first()->tradingHub;
            } else {
                // Multiple hubs - let user choose (for now, just use the first one)
                $hub = $planetsWithHubs->first()->tradingHub;
            }
        }

        $this->tradingHubRenderer->render($hub);

        // Wait for input
        system('stty -icanon -echo');
        while (true) {
            $char = $this->readChar();
            if ($char === 'q' || $char === "\033") {
                break;
            }
            if ($char === 'a') {
                $this->tradingHubRenderer->toggleShowAll();
                $this->tradingHubRenderer->render($hub);
            }
            usleep(50000);
        }
        system('stty sane');

        $this->refreshView();
    }

    private function charToIndex(string $input): int
    {
        $input = strtolower(trim($input));

        // Single character codes: 0-9, a-z (excluding g,h,r,t)
        $singleChars = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $reservedLetters = ['g', 'h', 'r', 't'];

        for ($i = 0; $i < 26; $i++) {
            $letter = chr(ord('a') + $i);
            if (!in_array($letter, $reservedLetters)) {
                $singleChars[] = $letter;
            }
        }

        // Check single char
        if (strlen($input) === 1) {
            $index = array_search($input, $singleChars, true);
            return $index !== false ? $index : -1;
        }

        // Check double char (aa, ab, ac, ...)
        if (strlen($input) === 2) {
            $letters = [];
            for ($i = 0; $i < 26; $i++) {
                $letter = chr(ord('a') + $i);
                if (!in_array($letter, $reservedLetters)) {
                    $letters[] = $letter;
                }
            }

            $first = $input[0];
            $second = $input[1];

            $firstIndex = array_search($first, $letters);
            $secondIndex = array_search($second, $letters);

            if ($firstIndex !== false && $secondIndex !== false) {
                $letterCount = count($letters);
                $doubleIndex = ($firstIndex * $letterCount) + $secondIndex;
                return count($singleChars) + $doubleIndex;
            }
        }

        return -1;
    }
}

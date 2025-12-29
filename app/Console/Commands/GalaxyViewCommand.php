<?php

namespace App\Console\Commands;

use App\Console\Renderers\GalaxyRenderer;
use App\Console\Renderers\StarSystemRenderer;
use App\Console\Renderers\TradingHubRenderer;
use App\Console\Traits\ConsoleColorizer;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class GalaxyViewCommand extends Command
{
    use ConsoleColorizer;

    protected $signature = 'galaxy:view {galaxy? : Galaxy ID or UUID}
                            {--width=120 : Terminal width for rendering}
                            {--height=40 : Terminal height for rendering}
                            {--show-hidden : Show hidden points of interest}
                            {--show-gates : Show warp gate connections}';

    protected $description = 'Display ASCII visualization of a galaxy with interactive zoom';

    private Galaxy      $galaxy;
    private Collection  $pois;
    private Collection  $gates;
    private int         $termWidth;
    private int         $termHeight;
    private bool        $showHidden;
    private bool        $showGates;
    private array       $poiMap = [];
    private int         $currentView = 0; // 0 = galaxy view, >0 = star system ID

    // Renderers
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
        // Load points of interest
        $query = $this->galaxy->pointsOfInterest()->with(['parent', 'children', 'tradingHub']);
        if (!$this->showHidden) {
            $query->where('is_hidden', false);
        }
        $this->pois = $query->get();

        // Load warp gates
        $gateQuery = $this->galaxy->warpGates()->with(['sourcePoi', 'destinationPoi']);
        if (!$this->showHidden) {
            $gateQuery->where('is_hidden', false);
        }
        $this->gates = $gateQuery->get();
    }

    private function interactiveLoop(): void
    {
        $this->info("\n" . $this->colorize('Controls:', 'header'));

        // Display controls in 3 columns
        $col1Width = 40;
        $col2Width = 40;

        $this->line(
            str_pad($this->colorize('  [0-9,a-z]', 'label') . ' - Zoom to star', $col1Width) .
            str_pad($this->colorize('  [h]', 'label') . ' - Toggle hidden POIs', $col2Width) .
            $this->colorize('  [r]', 'label') . ' - Refresh view'
        );
        $this->line(
            str_pad($this->colorize('  [t]', 'label') . '       - View trading hub', $col1Width) .
            str_pad($this->colorize('  [g]', 'label') . ' - Toggle warp gates', $col2Width) .
            $this->colorize('  [ESC/q]', 'label') . ' - Back/Quit'
        );
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
                $char === 'g' => $this->toggleGates(),
                $char === 'r' => $this->refreshView(),
                $char === 't' => $this->handleTradingHub(),
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
        if ($this->currentView === 0) {
            $this->galaxyRenderer->render(
                $this->galaxy,
                $this->pois,
                $this->gates,
                $this->showGates,
                $this->poiMap
            );
        } else {
            $star = $this->pois->firstWhere('id', $this->currentView);
            if ($star) {
                $this->starSystemRenderer->render($star, $this->gates);
            } else {
                $this->currentView = 0;
                $this->refreshView();
            }
        }
    }

    private function handleZoom(string $char): void
    {
        if ($this->currentView > 0) {
            return; // Already zoomed
        }

        $index = $this->charToIndex($char);

        if (isset($this->poiMap[$index])) {
            $poi = $this->poiMap[$index];

            if ($poi->type === PointOfInterestType::STAR) {
                $this->currentView = $poi->id;
                $this->refreshView();
            }
        }
    }

    private function handleTradingHub(): void
    {
        if ($this->currentView === 0) {
            return; // Not viewing a star system
        }

        $star = $this->pois->firstWhere('id', $this->currentView);
        if (!$star) {
            return;
        }

        $hub = $star->tradingHub;
        if (!$hub) {
            return;
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

    private function charToIndex(string $char): int
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

        $index = array_search(strtolower($char), $availableChars, true);
        return $index !== false ? $index : -1;
    }
}

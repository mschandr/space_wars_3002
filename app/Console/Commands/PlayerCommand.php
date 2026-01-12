<?php

namespace App\Console\Commands;

use App\Console\Traits\ConsoleColorizer;
use App\Console\Traits\TerminalInputHandler;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Services\FuelRegenerationService;
use App\Services\TravelService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Player Command
 *
 * Interactive command-line interface for Space Wars 3002
 * Similar to galaxy:view but for player interactions
 */
class PlayerCommand extends Command
{
    use ConsoleColorizer, TerminalInputHandler;

    protected $signature = 'player {player_id}
                            {--width=0 : Terminal width (0 = auto-detect)}
                            {--height=0 : Terminal height (0 = auto-detect)}
                            {--refresh=30 : Auto-refresh interval in seconds (default: 30)}';

    protected $description = 'Interactive player interface (new version)';

    private Player $player;

    private TravelService $travelService;

    private FuelRegenerationService $fuelRegenService;

    private int $termWidth;

    private int $termHeight;

    private int $scanRadius = 100;

    private array $nearbyPOIs = [];

    private array $poiMap = [];

    private string $viewMode = 'space'; // 'space', 'system', 'station'

    private ?PointOfInterest $currentStar = null;

    private int $savedScanRadius = 100; // Cache for scan radius

    public function handle(): int
    {
        // Auto-detect terminal size if not specified
        $this->detectTerminalSize();

        // Load player
        $playerId = $this->argument('player_id');

        try {
            $this->player = Player::with([
                'currentLocation.children',
                'currentLocation.tradingHub',
                'currentLocation.outgoingGates.destinationPoi',
                'activeShip.ship',
                'activeShip.cargo.mineral',
            ])->findOrFail($playerId);
        } catch (ModelNotFoundException $e) {
            $this->error("Player with ID {$playerId} not found.");

            return 1;
        }

        if (! $this->player->activeShip) {
            $this->error('Player has no active ship.');

            return 1;
        }

        $this->travelService = app(TravelService::class);
        $this->fuelRegenService = app(FuelRegenerationService::class);

        // Regenerate fuel on startup
        $this->fuelRegenService->regenerateFuel($this->player->activeShip);

        // Determine initial view mode
        $this->determineViewMode();

        // Main loop
        $this->mainLoop();

        return 0;
    }

    private function detectTerminalSize(): void
    {
        $width = (int) $this->option('width');
        $height = (int) $this->option('height');

        if ($width === 0 || $height === 0) {
            $size = exec('stty size 2>/dev/null');
            if ($size && preg_match('/(\d+)\s+(\d+)/', $size, $matches)) {
                $this->termHeight = $height ?: max(30, (int) $matches[1]);
                $this->termWidth = $width ?: max(120, (int) $matches[2]);
            } else {
                $this->termHeight = $height ?: 30;
                $this->termWidth = $width ?: 120;
            }
        } else {
            $this->termWidth = $width;
            $this->termHeight = $height;
        }
    }

    private function determineViewMode(): void
    {
        $location = $this->player->currentLocation;

        if ($location->tradingHub()->exists()) {
            $this->viewMode = 'station';
        } elseif ($location->type === PointOfInterestType::STAR && $location->children()->exists()) {
            $this->viewMode = 'system';
            $this->currentStar = $location;
        } else {
            $this->viewMode = 'space';
        }
    }

    private function mainLoop(): void
    {
        $running = true;
        $lastRender = 0;
        $autoRefreshInterval = max(1, (int) $this->option('refresh')); // Auto-refresh interval from option

        while ($running) {
            $currentTime = time();
            $shouldRender = false;

            // Check if auto-refresh interval has passed
            if ($currentTime - $lastRender >= $autoRefreshInterval) {
                $shouldRender = true;
            }

            // Always render on first iteration or when explicitly requested
            if ($lastRender === 0) {
                $shouldRender = true;
            }

            // Only render when needed
            if ($shouldRender) {
                // Regenerate fuel before rendering
                $this->fuelRegenService->regenerateFuel($this->player->activeShip);
                $this->player->activeShip->refresh();

                $this->render();
                $this->displayControls();
                $lastRender = $currentTime;
            }

            system('stty -icanon -echo');
            $input = $this->readChar();
            system('stty sane');

            if ($input === false) {
                // Sleep for 200ms instead of 50ms to reduce CPU usage
                usleep(200000);

                continue;
            }

            $result = $this->handleInput($input);
            if ($result === 'quit') {
                $running = false;
            } elseif ($result === 'refresh') {
                // Force immediate render on next loop
                $lastRender = 0;
            }
        }

        system('stty sane');
        $this->newLine();
        $this->info('Exiting Space Wars 3002...');
        $this->newLine();
    }

    private function render(): void
    {
        $this->clearScreen();

        match ($this->viewMode) {
            'space' => $this->renderDeepSpace(),
            'system' => $this->renderStarSystem(),
            'station' => $this->renderStation(),
        };
    }

    private function renderDeepSpace(): void
    {
        // Load nearby POIs
        $this->loadNearbyPOIs();

        // Render header
        $this->renderHeader('Deep Interstellar Space');

        // Calculate dimensions
        $dividerX = (int) ($this->termWidth * 0.6);
        $canvasHeight = $this->termHeight - 8;

        // Render spatial canvas (left panel)
        $this->renderSpatialCanvas($dividerX, $canvasHeight);

        // Render ship status (right panel) - position it properly
        // We need to move cursor back up since we've already drawn the canvas
        $this->renderShipStatusOverlay($dividerX + 2, 4);

        // Render vertical divider
        for ($y = 3; $y < $this->termHeight - 4; $y++) {
            echo sprintf("\033[%d;%dH│", $y, $dividerX);
        }
    }

    private function renderStarSystem(): void
    {
        if (! $this->currentStar) {
            $this->viewMode = 'space';
            $this->render();

            return;
        }

        $this->renderHeader(sprintf('Star System: %s', $this->currentStar->name));

        // Star info
        $stellarClass = $this->currentStar->attributes['stellar_class'] ?? 'Unknown';
        $this->line(sprintf('  Stellar Class: %s | Coordinates: (%d, %d)',
            $this->colorize($stellarClass, 'star_yellow'),
            $this->currentStar->x,
            $this->currentStar->y
        ));
        $this->newLine();

        // List planets
        $planets = $this->currentStar->children()->orderBy('orbital_index')->get();

        if ($planets->isEmpty()) {
            $this->line('  No planets in this system');
        } else {
            $this->line('  '.$this->colorize('Orbital Bodies:', 'header'));
            $this->newLine();

            foreach ($planets as $index => $planet) {
                $icon = $planet->getDisplayIcon();

                // Build info string
                $info = [];

                // Inhabited status with population
                if ($planet->is_inhabited) {
                    $population = $planet->attributes['population'] ?? 0;
                    if ($population > 0) {
                        $popFormatted = $population >= 1_000_000_000
                            ? number_format($population / 1_000_000_000, 1).'B'
                            : ($population >= 1_000_000
                                ? number_format($population / 1_000_000, 1).'M'
                                : ($population >= 1_000
                                    ? number_format($population / 1_000, 1).'K'
                                    : number_format($population)));
                        $info[] = $this->colorize("Pop: {$popFormatted}", 'highlight');
                    } else {
                        $info[] = $this->colorize('[INHABITED]', 'highlight');
                    }
                }

                // Trading hub indicator
                if ($planet->tradingHub) {
                    $info[] = $this->colorize('[TRADING HUB]', 'success');
                }

                $infoStr = ! empty($info) ? ' '.implode(' ', $info) : '';

                $this->line(sprintf('  [%d] %s %s - %s%s',
                    $index + 1,
                    $icon,
                    $this->colorize($planet->name, 'label'),
                    $planet->type->label(),
                    $infoStr
                ));
            }
        }

        $this->newLine();
        $dividerX = (int) ($this->termWidth * 0.65);
        $this->renderShipStatusOverlay($dividerX, 8);

        // Vertical divider
        for ($y = 3; $y < $this->termHeight - 4; $y++) {
            echo sprintf("\033[%d;%dH│", $y, $dividerX);
        }
    }

    private function renderStation(): void
    {
        $location = $this->player->currentLocation;
        $hub = $location->tradingHub;

        $this->renderHeader(sprintf('Station: %s', $hub->name ?? $location->name));

        $this->line('  '.$this->colorize('Available Services:', 'header'));
        $this->newLine();

        if ($hub) {
            $this->line('  (T) Trading Hub - Buy and sell minerals');

            if ($hub->hasShipyard()) {
                $this->line('  (S) Shipyard - Purchase new ships');
            }

            if ($hub->hasService('repair')) {
                $this->line('  (R) Repair Shop - Fix ship damage');
            }

            if ($hub->hasService('upgrades')) {
                $this->line('  (U) Upgrade Shop - Enhance ship components');
            }
        }

        $this->newLine();
        $dividerX = (int) ($this->termWidth * 0.65);
        $this->renderShipStatusOverlay($dividerX, 8);

        // Vertical divider
        for ($y = 3; $y < $this->termHeight - 4; $y++) {
            echo sprintf("\033[%d;%dH│", $y, $dividerX);
        }
    }

    private function loadNearbyPOIs(): void
    {
        $location = $this->player->currentLocation;

        $this->nearbyPOIs = PointOfInterest::where('galaxy_id', $this->player->galaxy_id)
            ->where('is_hidden', false)
            ->whereIn('type', [
                PointOfInterestType::STAR,
                PointOfInterestType::BLACK_HOLE,
                PointOfInterestType::NEBULA,
            ])
            ->whereRaw(
                'SQRT(POW(CAST(x AS SIGNED) - ?, 2) + POW(CAST(y AS SIGNED) - ?, 2)) <= ?',
                [$location->x, $location->y, $this->scanRadius]
            )
            ->with(['children.tradingHub', 'tradingHub'])
            ->get()
            ->toArray();
    }

    private function renderSpatialCanvas(int $width, int $height): void
    {
        $canvas = array_fill(0, $height, array_fill(0, $width - 2, ' '));

        $pois = collect($this->nearbyPOIs);
        if ($pois->isEmpty()) {
            $this->line('  No stars within sensor range (radius: '.$this->scanRadius.' units)');
            $this->line('  Press (S) to increase sensor range');

            return;
        }

        // Calculate bounds
        $minX = $pois->min('x');
        $maxX = $pois->max('x');
        $minY = $pois->min('y');
        $maxY = $pois->max('y');

        // Add padding
        $rangeX = max($maxX - $minX, 1);
        $rangeY = max($maxY - $minY, 1);
        $minX -= $rangeX * 0.15;
        $maxX += $rangeX * 0.15;
        $minY -= $rangeY * 0.15;
        $maxY += $rangeY * 0.15;

        $rangeX = $maxX - $minX;
        $rangeY = $maxY - $minY;

        $scaleX = ($width - 4) / max($rangeX, 1);
        $scaleY = ($height - 2) / max($rangeY, 1);

        // Plot POIs
        $starIndex = 0;
        $this->poiMap = []; // Reset map
        foreach ($pois as $poi) {
            $x = (int) round(($poi['x'] - $minX) * $scaleX);
            $y = (int) round(($poi['y'] - $minY) * $scaleY);

            $x = max(0, min($width - 4, $x));
            $y = max(0, min($height - 1, $y));

            $isCurrent = $poi['id'] === $this->player->current_poi_id;

            if ($isCurrent) {
                $canvas[$y][$x] = $this->colorize('@', 'player');
            } elseif ($poi['type'] === PointOfInterestType::STAR->value && $starIndex < 30) {
                $char = $this->indexToChar($starIndex);
                $this->poiMap[$starIndex] = $poi;
                $stellarClass = $poi['attributes']['stellar_class'] ?? null;
                $color = $this->getStarColor($stellarClass);

                // Check if star or its children have trading hub
                $hasTradingHub = ! empty($poi['trading_hub']) || collect($poi['children'] ?? [])->some(fn ($child) => ! empty($child['trading_hub']));
                if ($hasTradingHub && $x + 1 < $width - 2) {
                    // Show star with [T] indicator for trading hub
                    $canvas[$y][$x] = $this->colorize($char, $color);
                    $canvas[$y][$x + 1] = $this->colorize('T', 'success');
                } else {
                    $canvas[$y][$x] = $this->colorize($char, $color);
                }
                $starIndex++;
            }
        }

        // Render canvas
        foreach ($canvas as $row) {
            $this->line('  '.implode('', $row));
        }

        // Legend
        $this->newLine();
        $this->line('  '.$this->colorize('@', 'player').' = You | '.$this->colorize('T', 'success').' = Trading Hub | Scan radius: '.$this->scanRadius.' units | Stars: '.$starIndex);
    }

    private function renderShipStatusOverlay(int $x, int $y): void
    {
        $ship = $this->player->activeShip;
        $currentY = $y;

        // Ship Status header
        echo sprintf("\033[%d;%dH%s", $currentY, $x, $this->colorize('Ship Status', 'header'));
        $currentY += 2;

        // Ship name
        echo sprintf("\033[%d;%dHName: %s", $currentY, $x, $ship->ship->name);
        $currentY++;

        // Hull status
        $hullPercent = ($ship->hull / $ship->max_hull) * 100;
        $hullStatus = $hullPercent < 100 ? $this->colorize('Damaged', 'danger') : $this->colorize('Functional', 'success');
        echo sprintf("\033[%d;%dHClass: %s (%s)", $currentY, $x, $ship->ship->class, $hullStatus);
        $currentY++;

        echo sprintf("\033[%d;%dHHull: %d/%d", $currentY, $x, $ship->hull, $ship->max_hull);
        $currentY += 2;

        // Fuel with color coding
        $fuelPercent = ($ship->current_fuel / $ship->max_fuel) * 100;
        $fuelColor = $fuelPercent < 20 ? 'danger' : ($fuelPercent < 50 ? 'warning' : 'success');
        echo sprintf("\033[%d;%dHFuel: %s / %s", $currentY, $x,
            $this->colorize(number_format($ship->current_fuel, 0), $fuelColor),
            number_format($ship->max_fuel, 0)
        );
        $currentY++;

        // Fuel regeneration rate
        $regenInfo = $this->fuelRegenService->getRegenerationInfo($ship);
        echo sprintf("\033[%d;%dH  +%.1f/hr", $currentY, $x, $regenInfo['regen_rate_per_hour']);
        $currentY += 2;

        // Components
        echo sprintf("\033[%d;%dHWeapons: Level %d/10", $currentY, $x, min(10, $ship->weapons));
        $currentY++;

        echo sprintf("\033[%d;%dHWarp Drive: Level %d/10", $currentY, $x, $ship->warp_drive);
        $currentY++;

        echo sprintf("\033[%d;%dHSensors: Level %d/10", $currentY, $x, $ship->sensors);
        $currentY += 2;

        // Cargo
        $cargoPercent = ($ship->current_cargo / $ship->cargo_hold) * 100;
        $cargoColor = $cargoPercent > 90 ? 'warning' : 'label';
        echo sprintf("\033[%d;%dHCargo: %s", $currentY, $x,
            $this->colorize(sprintf('%d/%d', $ship->current_cargo, $ship->cargo_hold), $cargoColor)
        );
        $currentY++;

        // Cargo items (first 3)
        $cargoItems = $ship->cargo()->with('mineral')->limit(3)->get();
        foreach ($cargoItems as $cargo) {
            echo sprintf("\033[%d;%dH  %s: %d", $currentY, $x, substr($cargo->mineral->name, 0, 12), $cargo->quantity);
            $currentY++;
        }

        $currentY += 2;

        // Credits and Level
        echo sprintf("\033[%d;%dHCredits", $currentY, $x);
        $currentY++;
        echo sprintf("\033[%d;%dH%s", $currentY, $x, $this->colorize(number_format($this->player->credits, 0), 'highlight'));

        $levelX = $x + 20;
        echo sprintf("\033[%d;%dHLevel: %d", $currentY - 1, $levelX, $this->player->level);
        echo sprintf("\033[%d;%dHXP: %s", $currentY, $levelX, number_format($this->player->xp, 0));
    }

    private function renderHeader(string $title): void
    {
        $location = $this->player->currentLocation;
        $locationInfo = sprintf('@ %s (%d, %d)', $location->name, $location->x, $location->y);

        $this->line($this->colorize('╔'.str_repeat('═', $this->termWidth - 2).'╗', 'border'));

        $padding = $this->termWidth - mb_strlen($title) - mb_strlen($locationInfo) - 6;
        $this->line(
            $this->colorize('║ ', 'border').
            $this->colorize($title, 'header').
            str_repeat(' ', max(0, $padding)).
            $this->colorize($locationInfo, 'dim').
            $this->colorize(' ║', 'border')
        );

        $this->line($this->colorize('╚'.str_repeat('═', $this->termWidth - 2).'╝', 'border'));
    }

    private function displayControls(): void
    {
        $separator = str_repeat('─', $this->termWidth);
        echo sprintf("\033[%d;0H%s", $this->termHeight - 3, $this->colorize($separator, 'border'));

        $controls = match ($this->viewMode) {
            'space' => '[0-9,a-z] Select Star  (J)ump  (G)ates  (S)can  (R)efresh  (Q)uit',
            'system' => '[1-9] Select Planet  (L)and  (M)ine  (C)olony  (B)ack to Space  (Q)uit',
            'station' => '(T)rade  (S)hipyard  (R)epair  (U)pgrade  (B)ack to Space  (Q)uit',
        };

        echo sprintf("\033[%d;2H%s", $this->termHeight - 2, $controls);

        // Add refresh interval indicator on the right
        $refreshInterval = (int) $this->option('refresh');
        if ($refreshInterval > 0) {
            $refreshInfo = sprintf('Auto-refresh: %ds', $refreshInterval);
            $infoX = $this->termWidth - strlen($refreshInfo) - 2;
            echo sprintf("\033[%d;%dH%s", $this->termHeight - 2, $infoX, $this->colorize($refreshInfo, 'dim'));
        }
    }

    private function handleInput(string $input): string
    {
        return match ($this->viewMode) {
            'space' => $this->handleSpaceInput($input),
            'system' => $this->handleSystemInput($input),
            'station' => $this->handleStationInput($input),
        };
    }

    private function handleSpaceInput(string $input): string
    {
        return match ($input) {
            'q', 'Q', "\033" => 'quit',
            'r', 'R' => 'refresh',
            'j', 'J' => $this->promptJump(),
            'g', 'G' => $this->showGates(),
            's', 'S' => $this->performScan(),
            default => $this->selectStar($input),
        };
    }

    private function handleSystemInput(string $input): string
    {
        return match ($input) {
            'q', 'Q' => 'quit',
            'b', 'B', "\033" => $this->backToSpace(),
            'l', 'L' => $this->landOnPlanet(),
            'm', 'M' => $this->mineResources(),
            'c', 'C' => $this->manageColony(),
            default => $this->selectPlanet($input),
        };
    }

    private function handleStationInput(string $input): string
    {
        return match ($input) {
            'q', 'Q' => 'quit',
            'b', 'B', "\033" => $this->backToSpace(),
            't', 'T' => $this->openTrading(),
            's', 'S' => $this->openShipyard(),
            'r', 'R' => $this->openRepair(),
            'u', 'U' => $this->openUpgrade(),
            default => 'continue',
        };
    }

    private function promptJump(): string
    {
        system('stty sane');
        $this->clearScreen();

        $this->line($this->colorize('═══ JUMP TO COORDINATES ═══', 'header'));
        $this->newLine();

        // Show current location
        $location = $this->player->currentLocation;
        $this->line(sprintf('Current Location: %s (%d, %d)', $location->name, $location->x, $location->y));
        $this->newLine();

        // Prompt for coordinates
        $targetX = (int) $this->ask('Enter target X coordinate');
        $targetY = (int) $this->ask('Enter target Y coordinate');

        // Validate coordinates
        if ($targetX < 0 || $targetX > $this->player->galaxy->width || $targetY < 0 || $targetY > $this->player->galaxy->height) {
            $this->error('Invalid coordinates! Must be within galaxy bounds.');
            sleep(2);

            return 'refresh';
        }

        // Calculate distance and fuel cost
        $distance = sqrt(pow($targetX - $location->x, 2) + pow($targetY - $location->y, 2));
        $fuelCost = $this->travelService->calculateFuelCost($distance, $this->player->activeShip);

        $this->line(sprintf('Distance: %.1f units', $distance));
        $this->line(sprintf('Fuel required: %.1f', $fuelCost));
        $this->line(sprintf('Current fuel: %.1f', $this->player->activeShip->current_fuel));
        $this->newLine();

        // Check if enough fuel
        if ($this->player->activeShip->current_fuel < $fuelCost) {
            $this->error('Insufficient fuel for this jump!');
            sleep(2);

            return 'refresh';
        }

        // Confirm jump
        if (! $this->confirm('Execute jump?', true)) {
            return 'refresh';
        }

        // Find or create empty space POI at coordinates
        $targetPOI = PointOfInterest::firstOrCreate([
            'galaxy_id' => $this->player->galaxy_id,
            'x' => $targetX,
            'y' => $targetY,
        ], [
            'type' => PointOfInterestType::EMPTY_SPACE,
            'name' => sprintf('Coordinates (%d, %d)', $targetX, $targetY),
            'attributes' => [],
        ]);

        // Execute jump
        $this->player->activeShip->current_fuel -= $fuelCost;
        $this->player->activeShip->save();

        $this->player->current_poi_id = $targetPOI->id;
        $this->player->save();

        // Award XP
        $xp = max(10, (int) ($distance * 5));
        $this->player->addExperience($xp);

        $this->info(sprintf('Jump successful! Arrived at (%d, %d)', $targetX, $targetY));
        $this->info(sprintf('Fuel consumed: %.1f | XP earned: %d', $fuelCost, $xp));
        sleep(2);

        // Reload player
        $this->player->refresh();
        $this->player->load(['currentLocation', 'activeShip']);

        $this->determineViewMode();

        return 'refresh';
    }

    private function showGates(): string
    {
        $gates = $this->player->currentLocation->outgoingGates()
            ->where('is_hidden', false)
            ->with('destinationPoi')
            ->get();

        if ($gates->isEmpty()) {
            echo sprintf("\033[%d;0H", $this->termHeight - 1);
            $this->warn('No warp gates available from this location');
            sleep(1);

            return 'refresh';
        }

        system('stty sane');
        $this->clearScreen();

        $this->line($this->colorize('═══ WARP GATES ═══', 'header'));
        $this->newLine();

        $this->line('Available warp gates:');
        $this->newLine();

        foreach ($gates as $index => $gate) {
            $dest = $gate->destinationPoi;
            $distance = $gate->distance ?? $gate->calculateDistance();
            $fuelCost = $this->travelService->calculateFuelCost($distance, $this->player->activeShip);

            $canAfford = $this->player->activeShip->current_fuel >= $fuelCost;
            $color = $canAfford ? 'success' : 'danger';

            $this->line(sprintf('[%d] %s (%s) - Distance: %.1f - Fuel: %s',
                $index + 1,
                $dest->name,
                $dest->type->label(),
                $distance,
                $this->colorize(sprintf('%.1f', $fuelCost), $color)
            ));
        }

        $this->newLine();
        $this->line(sprintf('Current Fuel: %.1f', $this->player->activeShip->current_fuel));
        $this->newLine();

        $choice = $this->ask('Select gate number (or press Enter to cancel)');

        if (empty($choice) || ! is_numeric($choice)) {
            return 'refresh';
        }

        $gateIndex = (int) $choice - 1;
        if (! isset($gates[$gateIndex])) {
            $this->error('Invalid gate number');
            sleep(1);

            return 'refresh';
        }

        $gate = $gates[$gateIndex];
        $result = $this->travelService->executeTravel($this->player, $gate);

        if ($result['success']) {
            $this->info($result['message']);
            if (isset($result['xp_earned'])) {
                $this->info(sprintf('XP earned: %d', $result['xp_earned']));
            }
            sleep(2);

            // Reload player
            $this->player->refresh();
            $this->player->load(['currentLocation.children', 'currentLocation.tradingHub', 'activeShip']);

            $this->determineViewMode();

            return 'refresh';
        } else {
            $this->error($result['message']);
            sleep(2);

            return 'refresh';
        }
    }

    private function performScan(): string
    {
        $this->scanRadius += 50;
        echo sprintf("\033[%d;0H", $this->termHeight - 1);
        $this->info("Sensor range increased to {$this->scanRadius} units");
        sleep(1);

        return 'refresh';
    }

    private function selectStar(string $input): string
    {
        $index = $this->charToIndex($input);

        if (isset($this->poiMap[$index])) {
            $poi = $this->poiMap[$index];
            $this->currentStar = PointOfInterest::with('children')->find($poi['id']);

            if ($this->currentStar && $this->currentStar->children()->exists()) {
                $this->viewMode = 'system';

                return 'refresh';
            }
        }

        return 'continue';
    }

    private function backToSpace(): string
    {
        $this->viewMode = 'space';
        $this->currentStar = null;
        // Don't reset scan radius - keep it cached

        return 'refresh';
    }

    private function indexToChar(int $index): string
    {
        $chars = array_merge(range('0', '9'), range('a', 'z'));
        $reserved = ['g', 'h', 'j', 'q', 'r', 's', 't'];
        $available = collect($chars)->reject(fn ($c) => in_array($c, $reserved))->values()->toArray();

        return $available[$index] ?? '?';
    }

    private function charToIndex(string $char): int
    {
        $chars = array_merge(range('0', '9'), range('a', 'z'));
        $reserved = ['g', 'h', 'j', 'q', 'r', 's', 't'];
        $available = collect($chars)->reject(fn ($c) => in_array($c, $reserved))->values();

        $index = $available->search(strtolower($char));

        return $index !== false ? $index : -1;
    }

    private function getStarColor(?string $class): string
    {
        return match ($class) {
            'O', 'B' => 'star_blue',
            'A' => 'star_white',
            'F' => 'star_bright',
            'G' => 'star_yellow',
            'K' => 'star_orange',
            'M' => 'star_red',
            default => 'star_yellow',
        };
    }

    private function selectPlanet(string $input): string
    {
        if (! is_numeric($input) || ! $this->currentStar) {
            return 'continue';
        }

        $planetIndex = (int) $input - 1;
        $planets = $this->currentStar->children()->orderBy('orbital_index')->get();

        if (! isset($planets[$planetIndex])) {
            return 'continue';
        }

        $planet = $planets[$planetIndex];

        // Show planet details
        echo sprintf("\033[%d;0H", $this->termHeight - 1);
        $this->info(sprintf('Selected: %s (%s) - Press (L) to land, (M) to mine, (C) for colony',
            $planet->name,
            $planet->type->label()
        ));
        sleep(2);

        return 'refresh';
    }

    private function landOnPlanet(): string
    {
        if (! $this->currentStar) {
            echo sprintf("\033[%d;0H", $this->termHeight - 1);
            $this->warn('Not in a star system');
            sleep(1);

            return 'refresh';
        }

        // CRITICAL: Check if player is actually at the star (prevent bypass loophole)
        if ($this->player->current_poi_id !== $this->currentStar->id) {
            echo sprintf("\033[%d;0H", $this->termHeight - 1);
            $this->error('You must travel to this star system first! (Use warp gates or jump coordinates)');
            sleep(2);

            return 'refresh';
        }

        system('stty sane');
        $this->clearScreen();

        $this->line($this->colorize('═══ LAND ON PLANET ═══', 'header'));
        $this->newLine();

        $planets = $this->currentStar->children()->orderBy('orbital_index')->get();

        if ($planets->isEmpty()) {
            $this->error('No planets in this system');
            sleep(1);

            return 'refresh';
        }

        foreach ($planets as $index => $planet) {
            $this->line(sprintf('[%d] %s (%s)', $index + 1, $planet->name, $planet->type->label()));
        }

        $this->newLine();
        $choice = $this->ask('Select planet number (or press Enter to cancel)');

        if (empty($choice) || ! is_numeric($choice)) {
            return 'refresh';
        }

        $planetIndex = (int) $choice - 1;
        if (! isset($planets[$planetIndex])) {
            $this->error('Invalid planet number');
            sleep(1);

            return 'refresh';
        }

        $planet = $planets[$planetIndex];

        // Move player to planet
        $this->player->current_poi_id = $planet->id;
        $this->player->save();

        $this->info(sprintf('Landed on %s', $planet->name));
        sleep(1);

        // Reload
        $this->player->refresh();
        $this->player->load(['currentLocation.children', 'currentLocation.tradingHub', 'activeShip']);

        $this->determineViewMode();

        return 'refresh';
    }

    private function mineResources(): string
    {
        echo sprintf("\033[%d;0H", $this->termHeight - 1);
        $this->warn('Mining feature - Coming soon! Will extract minerals from planets/asteroids.');
        sleep(2);

        return 'refresh';
    }

    private function manageColony(): string
    {
        echo sprintf("\033[%d;0H", $this->termHeight - 1);
        $this->warn('Colony management - Coming soon! Establish and manage colonies.');
        sleep(2);

        return 'refresh';
    }

    private function openTrading(): string
    {
        $location = $this->player->currentLocation;
        $hub = $location->tradingHub;

        if (! $hub) {
            echo sprintf("\033[%d;0H", $this->termHeight - 1);
            $this->error('No trading hub at this location');
            sleep(1);

            return 'refresh';
        }

        system('stty sane');
        $this->clearScreen();

        $this->line($this->colorize('═══ TRADING HUB: '.$hub->name.' ═══', 'header'));
        $this->newLine();

        // Simple trading interface
        $inventories = $hub->inventories()->with('mineral')->get();

        if ($inventories->isEmpty()) {
            $this->warn('No minerals available for trade');
            sleep(1);

            return 'refresh';
        }

        $this->line('Available Minerals:');
        $this->newLine();

        foreach ($inventories as $index => $inventory) {
            $this->line(sprintf('[%d] %s - Stock: %d - Buy: %s cr - Sell: %s cr',
                $index + 1,
                $inventory->mineral->name,
                $inventory->quantity,
                number_format($inventory->sell_price, 0),
                number_format($inventory->buy_price, 0)
            ));
        }

        $this->newLine();
        $this->line(sprintf('Your Credits: %s', number_format($this->player->credits, 0)));
        $this->line(sprintf('Cargo: %d/%d', $this->player->activeShip->current_cargo, $this->player->activeShip->cargo_hold));
        $this->newLine();

        $this->info('Full trading interface coming soon! Press any key to continue...');
        fread(STDIN, 1);

        return 'refresh';
    }

    private function openShipyard(): string
    {
        $location = $this->player->currentLocation;
        $hub = $location->tradingHub;

        if (! $hub || ! $hub->hasShipyard()) {
            echo sprintf("\033[%d;0H", $this->termHeight - 1);
            $this->error('No shipyard at this location');
            sleep(1);

            return 'refresh';
        }

        system('stty sane');
        $this->clearScreen();

        $this->line($this->colorize('═══ SHIPYARD ═══', 'header'));
        $this->newLine();

        $ships = $hub->ships()->with('ship')->where('quantity', '>', 0)->get();

        if ($ships->isEmpty()) {
            $this->warn('No ships available for purchase');
            sleep(1);

            return 'refresh';
        }

        foreach ($ships as $index => $hubShip) {
            $this->line(sprintf('[%d] %s (%s) - Price: %s cr - Stock: %d',
                $index + 1,
                $hubShip->ship->name,
                $hubShip->ship->class,
                number_format($hubShip->current_price, 0),
                $hubShip->quantity
            ));
        }

        $this->newLine();
        $this->line(sprintf('Your Credits: %s', number_format($this->player->credits, 0)));
        $this->newLine();

        $this->info('Full shipyard interface coming soon! Press any key to continue...');
        fread(STDIN, 1);

        return 'refresh';
    }

    private function openRepair(): string
    {
        $location = $this->player->currentLocation;
        $hub = $location->tradingHub;

        if (! $hub || ! $hub->hasService('repair')) {
            echo sprintf("\033[%d;0H", $this->termHeight - 1);
            $this->error('No repair shop at this location');
            sleep(1);

            return 'refresh';
        }

        system('stty sane');
        $this->clearScreen();

        $repairService = app(\App\Services\ShipRepairService::class);
        $repairInfo = $repairService->getRepairInfo($this->player->activeShip);

        $this->line($this->colorize('═══ REPAIR SHOP ═══', 'header'));
        $this->newLine();

        $this->line(sprintf('Ship: %s', $this->player->activeShip->ship->name));
        $this->line(sprintf('Hull: %d/%d', $this->player->activeShip->hull, $this->player->activeShip->max_hull));
        $this->newLine();

        if ($repairInfo['total_repair_cost'] === 0) {
            $this->info('Ship is in perfect condition!');
            sleep(1);

            return 'refresh';
        }

        $this->line('Repair Options:');
        $this->newLine();

        if ($repairInfo['needs_hull_repair']) {
            $this->line(sprintf('[1] Repair Hull - Cost: %s cr', number_format($repairInfo['hull_repair_cost'], 0)));
        }

        if ($repairInfo['needs_component_repair']) {
            $this->line(sprintf('[2] Repair Components - Cost: %s cr', number_format($repairInfo['component_repair_cost'], 0)));
        }

        $this->line(sprintf('[3] Complete Overhaul - Cost: %s cr', number_format($repairInfo['total_repair_cost'], 0)));

        $this->newLine();
        $this->line(sprintf('Your Credits: %s', number_format($this->player->credits, 0)));
        $this->newLine();

        $choice = $this->ask('Select option (or press Enter to cancel)');

        if (empty($choice) || ! is_numeric($choice)) {
            return 'refresh';
        }

        $result = match ((int) $choice) {
            1 => $repairService->repairHull($this->player, $this->player->activeShip),
            2 => $repairService->repairComponents($this->player, $this->player->activeShip),
            3 => $repairService->repairAll($this->player, $this->player->activeShip),
            default => ['success' => false, 'message' => 'Invalid option'],
        };

        if ($result['success']) {
            $this->info($result['message']);
            $this->player->refresh();
            $this->player->load(['activeShip']);
        } else {
            $this->error($result['message']);
        }

        sleep(2);

        return 'refresh';
    }

    private function openUpgrade(): string
    {
        $location = $this->player->currentLocation;
        $hub = $location->tradingHub;

        if (! $hub || ! $hub->hasService('upgrades')) {
            echo sprintf("\033[%d;0H", $this->termHeight - 1);
            $this->error('No upgrade shop at this location');
            sleep(1);

            return 'refresh';
        }

        system('stty sane');
        $this->clearScreen();

        $upgradeService = app(\App\Services\ShipUpgradeService::class);
        $upgradeInfo = $upgradeService->getUpgradeInfo($this->player->activeShip);

        $this->line($this->colorize('═══ UPGRADE SHOP ═══', 'header'));
        $this->newLine();

        $this->line(sprintf('Ship: %s', $this->player->activeShip->ship->name));
        $this->newLine();

        $upgradeableComponents = array_filter($upgradeInfo, fn ($info) => $info['can_upgrade']);

        if (empty($upgradeableComponents)) {
            $this->info('All components are fully upgraded!');
            sleep(1);

            return 'refresh';
        }

        $this->line('Available Upgrades:');
        $this->newLine();

        $index = 1;
        $componentKeys = [];
        foreach ($upgradeableComponents as $component => $info) {
            $componentKeys[$index] = $component;
            $componentLabel = match ($component) {
                'weapons' => 'Weapons',
                'sensors' => 'Sensors',
                'warp_drive' => 'Warp Drive',
                'max_hull' => 'Hull Plating',
                'cargo_hold' => 'Cargo Hold',
                'max_fuel' => 'Fuel Tank',
                default => $component,
            };

            $this->line(sprintf('[%d] %s: Lv%d→%d (%d→%d) - Cost: %s cr',
                $index,
                $componentLabel,
                $info['current_level'],
                $info['current_level'] + 1,
                $info['current_value'],
                $info['next_value'],
                number_format($info['upgrade_cost'], 0)
            ));
            $index++;
        }

        $this->newLine();
        $this->line(sprintf('Your Credits: %s', number_format($this->player->credits, 0)));
        $this->newLine();

        $choice = $this->ask('Select upgrade (or press Enter to cancel)');

        if (empty($choice) || ! is_numeric($choice) || ! isset($componentKeys[(int) $choice])) {
            return 'refresh';
        }

        $component = $componentKeys[(int) $choice];
        $result = $upgradeService->upgrade($this->player->activeShip, $component);

        if ($result['success']) {
            $this->info($result['message']);
            $this->player->refresh();
            $this->player->load(['activeShip']);
        } else {
            $this->error($result['message']);
        }

        sleep(2);

        return 'refresh';
    }

    private function clearScreen(): void
    {
        echo "\033[2J\033[H";
    }
}

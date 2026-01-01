<?php

namespace App\Console\Commands;

use App\Console\Services\LocationValidator;
use App\Console\Shops\ComponentShopHandler;
use App\Console\Shops\MineralTradingHandler;
use App\Console\Shops\PirateEncounterHandler;
use App\Console\Shops\PlansShopHandler;
use App\Console\Shops\ShipShopHandler;
use App\Console\Shops\RepairShopHandler;
use App\Console\Traits\ConsoleBoxRenderer;
use App\Console\Traits\ConsoleColorizer;
use App\Console\Traits\TerminalInputHandler;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Player;
use App\Services\PirateEncounterService;
use Illuminate\Console\Command;

class PlayerInterfaceCommand extends Command
{
    use ConsoleColorizer;
    use TerminalInputHandler;
    use ConsoleBoxRenderer;

    protected $signature = 'player:interface {player_id}';

    protected $description = 'Display player interface with current location and ship status';

    private Player $player;
    private int $termWidth = 120;

    public function handle()
    {
        $playerId = $this->argument('player_id');

        // Load player with all relationships
        $this->player = Player::with([
            'currentLocation.children',
            'currentLocation.parent',
            'activeShip.ship',
            'activeShip.cargo.mineral'
        ])->find($playerId);

        if (!$this->player) {
            $this->error("Player with ID {$playerId} not found.");
            return 1;
        }

        if (!$this->player->activeShip) {
            $this->error("Player has no active ship.");
            return 1;
        }

        $this->renderInterface();
        $this->interactiveLoop();

        return 0;
    }

    private function renderInterface(): void
    {
        $this->clearScreen();
        $this->renderHeader();
        $this->renderPlayerStats();
        $this->newLine();

        // Two-column layout: System view on left, Ship stats on right
        $this->renderTwoColumnLayout();

        $this->newLine();
        $this->renderControls();
    }

    private function interactiveLoop(): void
    {
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
                $char === 'q' || $char === "\033" => $running = false,
                $char === 'r' => $this->refreshInterface(),
                $char === 'u' => $this->showUpgrades(),
                $char === 's' => $this->showShipInfo(),
                $char === 'c' => $this->showCargo(),
                $char === 'p' => $this->showComponentShop(),
                $char === 'l' => $this->showPlansShop(),
                $char === 'm' => $this->showRepairShop(),
                $char === 't' => $this->showTradingInterface(),
                $char === 'w' => $this->showTravelInterface(),
                $char === 'b' => $this->showShipShop(),
                default => null,
            };
        }

        // Restore terminal settings
        system('stty sane');
        $this->clearScreen();
        $this->info('Exiting player interface...');
    }

    private function refreshInterface(): void
    {
        // Reload player data
        $this->player->refresh();
        $this->player->load([
            'currentLocation.children',
            'currentLocation.parent',
            'activeShip.ship',
            'activeShip.cargo.mineral'
        ]);

        $this->renderInterface();
    }

    private function showUpgrades(): void
    {
        system('stty sane');
        $this->clearScreen();

        $ship = $this->player->activeShip;
        $upgradeService = app(\App\Services\ShipUpgradeService::class);

        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->line($this->colorize('  SHIP UPGRADES', 'header'));
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->newLine();

        $this->line($this->colorize('  Player Credits: ', 'label') . $this->colorize(number_format($this->player->credits, 2), 'trade'));
        $this->newLine();

        $upgradeInfo = $upgradeService->getUpgradeInfo($ship);

        $this->table(
            ['Component', 'Current', 'Level', 'Max', 'Can Upgrade', 'Cost', 'Next Value'],
            collect($upgradeInfo)->map(function ($info, $component) {
                return [
                    $component,
                    $info['current_value'],
                    $info['current_level'],
                    $info['max_level'],
                    $info['can_upgrade'] ? 'Yes' : 'No',
                    $info['upgrade_cost'] ?? 'N/A',
                    $info['next_value'] ?? 'N/A',
                ];
            })->toArray()
        );

        $this->newLine();
        $this->line($this->colorize('  Press any key to return...', 'dim'));

        system('stty -icanon -echo');
        fgetc(STDIN);

        $this->refreshInterface();
    }

    private function showShipInfo(): void
    {
        system('stty sane');
        $this->clearScreen();

        $ship = $this->player->activeShip;
        $shipTemplate = $ship->ship;

        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->line($this->colorize('  SHIP INFORMATION', 'header'));
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->newLine();

        $this->line($this->colorize('  Ship Name: ', 'label') . $ship->name);
        $this->line($this->colorize('  Ship Class: ', 'label') . $shipTemplate->class);
        $this->line($this->colorize('  Ship Type: ', 'label') . $shipTemplate->name);
        $this->line($this->colorize('  Status: ', 'label') . $this->colorize(ucfirst($ship->status),
            $ship->status === 'operational' ? 'highlight' : 'dim'));
        $this->newLine();

        $this->line($this->colorize('  Description:', 'header'));
        $this->line('  ' . $shipTemplate->description);
        $this->newLine();

        $this->line($this->colorize('  Current Stats:', 'header'));

        // Hull
        $hullPercent = ($ship->hull / $ship->max_hull) * 100;
        $hullColor = $hullPercent > 70 ? 'highlight' : ($hullPercent > 30 ? 'trade' : 'dim');
        $this->line($this->colorize('    Hull: ', 'label') .
                   $this->colorize($ship->hull . '/' . $ship->max_hull, $hullColor) .
                   $this->colorize(' (' . round($hullPercent) . '%)', 'dim'));

        // Fuel
        $ship->regenerateFuel();
        $fuelPercent = ($ship->current_fuel / $ship->max_fuel) * 100;
        $fuelColor = $fuelPercent > 70 ? 'highlight' : ($fuelPercent > 30 ? 'trade' : 'dim');
        $this->line($this->colorize('    Fuel: ', 'label') .
                   $this->colorize($ship->current_fuel . '/' . $ship->max_fuel, $fuelColor) .
                   $this->colorize(' (' . round($fuelPercent) . '%)', 'dim'));

        // Cargo
        $cargoPercent = $ship->cargo_hold > 0 ? ($ship->current_cargo / $ship->cargo_hold) * 100 : 0;
        $this->line($this->colorize('    Cargo Hold: ', 'label') .
                   $this->colorize($ship->current_cargo . '/' . $ship->cargo_hold, 'highlight') .
                   $this->colorize(' (' . round($cargoPercent) . '% full)', 'dim'));

        // Other stats
        $this->line($this->colorize('    Weapons: ', 'label') . $this->colorize($ship->weapons, 'highlight'));
        $this->line($this->colorize('    Sensors: ', 'label') . $this->colorize($ship->sensors, 'highlight'));
        $this->line($this->colorize('    Warp Drive: ', 'label') . $this->colorize($ship->warp_drive, 'highlight'));

        $this->newLine();
        $this->line($this->colorize('  Base Template:', 'header'));
        $this->line($this->colorize('    Base Cargo: ', 'dim') . $shipTemplate->cargo_capacity);
        $this->line($this->colorize('    Base Hull: ', 'dim') . $shipTemplate->hull_strength);
        $this->line($this->colorize('    Speed: ', 'dim') . $shipTemplate->speed);
        $this->line($this->colorize('    Rarity: ', 'dim') . ucfirst($shipTemplate->rarity));

        $this->newLine();
        $this->line($this->colorize('  Press any key to return...', 'dim'));

        system('stty -icanon -echo');
        fgetc(STDIN);

        $this->refreshInterface();
    }

    private function showCargo(): void
    {
        system('stty sane');
        $this->clearScreen();

        $ship = $this->player->activeShip;

        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->line($this->colorize('  CARGO MANIFEST', 'header'));
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->newLine();

        $cargoUsed = $ship->cargo->sum('quantity');
        $this->line($this->colorize('  Cargo Capacity: ', 'label') .
                   $this->colorize("{$cargoUsed}/{$ship->cargo_hold}", 'highlight'));
        $this->newLine();

        if ($ship->cargo->isEmpty()) {
            $this->line($this->colorize('  Cargo hold is empty.', 'dim'));
        } else {
            $cargoData = $ship->cargo->map(function ($cargo) {
                return [
                    $cargo->mineral->symbol,
                    $cargo->mineral->name,
                    $cargo->quantity,
                    number_format($cargo->mineral->base_value, 2),
                    number_format($cargo->quantity * $cargo->mineral->base_value, 2),
                ];
            })->toArray();

            $this->table(
                ['Symbol', 'Mineral', 'Quantity', 'Unit Value', 'Total Value'],
                $cargoData
            );

            $totalValue = $ship->cargo->sum(function ($cargo) {
                return $cargo->quantity * $cargo->mineral->base_value;
            });

            $this->newLine();
            $this->line($this->colorize('  Total Cargo Value: ', 'label') .
                       $this->colorize(number_format($totalValue, 2) . ' credits', 'trade'));
        }

        $this->newLine();
        $this->line($this->colorize('  Press any key to return...', 'dim'));

        system('stty -icanon -echo');
        fgetc(STDIN);

        $this->refreshInterface();
    }

    private function showComponentShop(): void
    {
        $tradingHub = LocationValidator::getTradingHub($this->player);

        if (!$tradingHub || !$tradingHub->is_active) {
            system('stty sane');
            $this->clearScreen();
            $this->error('No active trading hub at this location.');
            $this->newLine();
            $this->line($this->colorize('  Component upgrades are only available at trading hubs.', 'dim'));
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            system('stty -icanon -echo');
            fgetc(STDIN);
            $this->refreshInterface();
            return;
        }

        $handler = new ComponentShopHandler($this, $this->termWidth);
        $handler->show($this->player, $tradingHub);

        system('stty sane');
        $this->refreshInterface();
    }

    private function showRepairShop(): void
    {
        $tradingHub = LocationValidator::getTradingHub($this->player);

        if (!$tradingHub || !$tradingHub->is_active) {
            system('stty sane');
            $this->clearScreen();
            $this->error('No active trading hub at this location.');
            $this->newLine();
            $this->line($this->colorize('  Repair services are only available at trading hubs.', 'dim'));
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            system('stty -icanon -echo');
            fgetc(STDIN);
            $this->refreshInterface();
            return;
        }

        $handler = new RepairShopHandler($this, $this->termWidth);
        $handler->show($this->player, $tradingHub);

        system('stty sane');
        $this->refreshInterface();
    }

    private function showShipShop(): void
    {
        $tradingHub = LocationValidator::getTradingHub($this->player);

        if (!$tradingHub || !$tradingHub->is_active) {
            system('stty sane');
            $this->clearScreen();
            $this->error('No active trading hub at this location.');
            $this->newLine();
            $this->line($this->colorize('  Shipyard services are only available at trading hubs.', 'dim'));
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            system('stty -icanon -echo');
            fgetc(STDIN);
            $this->refreshInterface();
            return;
        }

        // Check if this trading hub has a shipyard
        if (!$tradingHub->hasShipyard()) {
            system('stty sane');
            $this->clearScreen();
            $this->error('This trading hub does not have a shipyard.');
            $this->newLine();
            $this->line($this->colorize('  Not all trading hubs sell ships. Try a premium or major trading hub.', 'dim'));
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            system('stty -icanon -echo');
            fgetc(STDIN);
            $this->refreshInterface();
            return;
        }

        $handler = new ShipShopHandler($this, $this->termWidth);
        $handler->show($this->player, $tradingHub);

        system('stty sane');
        $this->refreshInterface();
    }

    private function clearScreen(): void
    {
        $this->output->write("\033[2J\033[H");
    }

    private function renderHeader(): void
    {
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->line(
            $this->colorize('  SPACE WARS 3002 - PLAYER INTERFACE', 'header')
        );
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->newLine();
    }

    private function renderPlayerStats(): void
    {
        $callSign = $this->colorize($this->player->call_sign, 'highlight');
        $credits = $this->colorize(number_format($this->player->credits, 2), 'trade');
        $level = $this->colorize($this->player->level, 'highlight');
        $xp = $this->colorize(number_format($this->player->experience), 'dim');

        $this->line("  " . $this->colorize('CAPTAIN:', 'label') . " {$callSign}  |  " .
                   $this->colorize('CREDITS:', 'label') . " {$credits}  |  " .
                   $this->colorize('LEVEL:', 'label') . " {$level}  |  " .
                   $this->colorize('XP:', 'label') . " {$xp}");
    }

    private function renderTwoColumnLayout(): void
    {
        $leftColumn = $this->getSystemViewColumn();
        $rightColumn = $this->getShipStatsColumn();

        $leftLines = explode("\n", $leftColumn);
        $rightLines = explode("\n", $rightColumn);

        // Remove trailing empty line if exists
        if (end($leftLines) === '') {
            array_pop($leftLines);
        }
        if (end($rightLines) === '') {
            array_pop($rightLines);
        }

        $maxLines = max(count($leftLines), count($rightLines));

        for ($i = 0; $i < $maxLines; $i++) {
            $left = $leftLines[$i] ?? '';
            $right = $rightLines[$i] ?? '';

            // Calculate actual visual width of left column (strip ANSI codes)
            $leftPlain = preg_replace('/\033\[[0-9;]*m/', '', $left);
            $leftVisualWidth = mb_strlen($leftPlain);

            // Pad left column to exactly 60 characters
            $leftPadding = str_repeat(' ', max(0, 60 - $leftVisualWidth));

            // Each box is exactly 60 chars wide, add 2 spaces between columns
            $this->line($left . $leftPadding . '  ' . $right);
        }
    }

    private function getSystemViewColumn(): string
    {
        $output = '';
        $location = $this->player->currentLocation;

        if (!$location) {
            return $this->colorize('  LOCATION: Unknown', 'dim');
        }

        // Header
        $headerText = 'CURRENT LOCATION';
        $headerPadding = 57 - mb_strlen($headerText);

        $output .= $this->colorize('╔' . str_repeat('═', 58) . '╗', 'border') . "\n";
        $output .= $this->colorize('║', 'border') . ' ' .
                   $this->colorize($headerText, 'header') .
                   str_repeat(' ', $headerPadding) .
                   $this->colorize('║', 'border') . "\n";
        $output .= $this->colorize('╠' . str_repeat('═', 58) . '╣', 'border') . "\n";

        // Get root star
        $star = $location->type === PointOfInterestType::STAR
            ? $location
            : $location->getRootStar();

        if ($star) {
            $output .= $this->formatBoxLine('System: ' . $star->name);
            $output .= $this->formatBoxLine('Coordinates: (' . $star->x . ', ' . $star->y . ')');

            if (isset($star->attributes['stellar_class'])) {
                $output .= $this->formatBoxLine('Class: ' . $star->attributes['stellar_class']);
            }

            $output .= $this->colorize('╟' . str_repeat('─', 58) . '╢', 'border') . "\n";

            // Show planets
            $planets = $star->children;
            if ($planets->isNotEmpty()) {
                $output .= $this->formatBoxLine('ORBITAL BODIES:', true);
                $output .= $this->formatBoxLine('');

                foreach ($planets as $planet) {
                    $icon = $planet->getDisplayIcon();
                    $current = ($planet->id === $location->id) ? ' ◄' : '';
                    $output .= $this->formatBoxLine("  {$icon} [{$planet->orbital_index}] {$planet->name}{$current}");
                }
            } else {
                $output .= $this->formatBoxLine('No orbital bodies');
            }
        } else {
            $output .= $this->formatBoxLine('System: ' . $location->name);
            $output .= $this->formatBoxLine('Type: ' . $location->type->name);
            $output .= $this->formatBoxLine('Coordinates: (' . $location->x . ', ' . $location->y . ')');
        }

        $output .= $this->colorize('╚' . str_repeat('═', 58) . '╝', 'border') . "\n";

        return $output;
    }

    private function getShipStatsColumn(): string
    {
        $output = '';
        $ship = $this->player->activeShip;

        // Regenerate fuel before displaying
        $ship->regenerateFuel();

        $headerText = 'SHIP STATUS';
        $headerPadding = 57 - mb_strlen($headerText);

        $output .= $this->colorize('╔' . str_repeat('═', 58) . '╗', 'border') . "\n";
        $output .= $this->colorize('║', 'border') . ' ' .
                   $this->colorize($headerText, 'header') .
                   str_repeat(' ', $headerPadding) .
                   $this->colorize('║', 'border') . "\n";
        $output .= $this->colorize('╠' . str_repeat('═', 58) . '╣', 'border') . "\n";

        $output .= $this->formatBoxLine('Name: ' . ($ship->name ?? 'Unnamed'));
        $output .= $this->formatBoxLine('Class: ' . $ship->ship->class);
        $output .= $this->formatBoxLine('Status: ' . ucfirst($ship->status));

        $output .= $this->colorize('╟' . str_repeat('─', 58) . '╢', 'border') . "\n";

        // Ship components
        $output .= $this->formatBoxLine('COMPONENTS:', true);
        $output .= $this->formatBoxLine('');
        $output .= $this->formatBoxLine('  Fuel:       ' . $ship->current_fuel . '/' . $ship->max_fuel);
        $output .= $this->formatBoxLine('  Hull:       ' . $ship->hull . '/' . $ship->max_hull);
        $output .= $this->formatBoxLine('  Weapons:    ' . $ship->weapons);
        $output .= $this->formatBoxLine('  Sensors:    Level ' . $ship->sensors);
        $output .= $this->formatBoxLine('  Warp Drive: Level ' . $ship->warp_drive);

        $output .= $this->colorize('╟' . str_repeat('─', 58) . '╢', 'border') . "\n";

        // Cargo
        $cargoUsed = 0;
        $cargoDetails = [];

        foreach ($ship->cargo as $cargo) {
            $cargoUsed += $cargo->quantity;
            $cargoDetails[] = "  {$cargo->mineral->symbol}: {$cargo->quantity} units";
        }

        $output .= $this->formatBoxLine('CARGO:', true);
        $output .= $this->formatBoxLine('  Capacity: ' . $cargoUsed . '/' . $ship->cargo_hold);
        $output .= $this->formatBoxLine('');

        if (empty($cargoDetails)) {
            $output .= $this->formatBoxLine('  (Empty)');
        } else {
            foreach ($cargoDetails as $detail) {
                $output .= $this->formatBoxLine($detail);
            }
        }

        $output .= $this->colorize('╚' . str_repeat('═', 58) . '╝', 'border') . "\n";

        return $output;
    }

    private function formatBoxLine(string $content, bool $bold = false): string
    {
        $plainContent = preg_replace('/\033\[[0-9;]*m/', '', $content);
        $padding = max(0, 57 - mb_strlen($plainContent));

        if ($bold) {
            $content = $this->colorize($content, 'header');
        }

        return $this->colorize('║', 'border') . ' ' . $content . str_repeat(' ', $padding) . $this->colorize('║', 'border') . "\n";
    }

    private function renderControls(): void
    {
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->line($this->colorize('  CONTROLS:', 'header'));
        $this->newLine();

        // Check if at trading hub for upgrade access
        $atTradingHub = LocationValidator::isAtTradingHub($this->player);
        $atPlansHub = LocationValidator::isAtPlansHub($this->player);

        $col1Width = 40;
        $col2Width = 40;

        // First row
        $line1_col1 = $this->colorize('  [u]', 'label') . ' - View upgrade info';
        $line1_col1_plain = preg_replace('/\033\[[0-9;]*m/', '', $line1_col1);
        $line1_col1_padding = str_repeat(' ', max(0, $col1Width - mb_strlen($line1_col1_plain)));

        if ($atTradingHub) {
            $line1_col2 = $this->colorize('  [p]', 'label') . ' - Purchase upgrades';
        } else {
            $line1_col2 = $this->colorize('  [p]', 'dim') . ' - ' . $this->colorize('Upgrades (Trading Hub only)', 'dim');
        }
        $line1_col2_plain = preg_replace('/\033\[[0-9;]*m/', '', $line1_col2);
        $line1_col2_padding = str_repeat(' ', max(0, $col2Width - mb_strlen($line1_col2_plain)));

        $line1_col3 = $this->colorize('  [s]', 'label') . ' - Ship information';

        $this->line($line1_col1 . $line1_col1_padding . $line1_col2 . $line1_col2_padding . $line1_col3);

        // Second row
        $line2_col1 = $this->colorize('  [c]', 'label') . ' - Cargo manifest';
        $line2_col1_plain = preg_replace('/\033\[[0-9;]*m/', '', $line2_col1);
        $line2_col1_padding = str_repeat(' ', max(0, $col1Width - mb_strlen($line2_col1_plain)));

        $line2_col2 = $this->colorize('  [r]', 'label') . ' - Refresh display';
        $line2_col2_plain = preg_replace('/\033\[[0-9;]*m/', '', $line2_col2);
        $line2_col2_padding = str_repeat(' ', max(0, $col2Width - mb_strlen($line2_col2_plain)));

        $line2_col3 = $this->colorize('  [q/ESC]', 'label') . ' - Exit';

        $this->line($line2_col1 . $line2_col1_padding . $line2_col2 . $line2_col2_padding . $line2_col3);

        // Third row
        if ($atPlansHub) {
            $line3_col1 = $this->colorize('  [l]', 'label') . ' - Browse upgrade plans';
        } else {
            $line3_col1 = $this->colorize('  [l]', 'dim') . ' - ' . $this->colorize('Plans (Special hubs only)', 'dim');
        }
        $line3_col1_plain = preg_replace('/\033\[[0-9;]*m/', '', $line3_col1);
        $line3_col1_padding = str_repeat(' ', max(0, $col1Width - mb_strlen($line3_col1_plain)));

        if ($atTradingHub) {
            $line3_col2 = $this->colorize('  [t]', 'label') . ' - Trade minerals';
        } else {
            $line3_col2 = $this->colorize('  [t]', 'dim') . ' - ' . $this->colorize('Trade (Trading Hub only)', 'dim');
        }
        $line3_col2_plain = preg_replace('/\033\[[0-9;]*m/', '', $line3_col2);
        $line3_col2_padding = str_repeat(' ', max(0, $col2Width - mb_strlen($line3_col2_plain)));

        $line3_col3 = $this->colorize('  [w]', 'label') . ' - Warp gate travel';

        $this->line($line3_col1 . $line3_col1_padding . $line3_col2 . $line3_col2_padding . $line3_col3);

        // Fourth row - Repair & Shipyard
        if ($atTradingHub) {
            $line4_col1 = $this->colorize('  [m]', 'label') . ' - Repair & Refit';
        } else {
            $line4_col1 = $this->colorize('  [m]', 'dim') . ' - ' . $this->colorize('Repair (Trading Hub only)', 'dim');
        }
        $line4_col1_plain = preg_replace('/\033\[[0-9;]*m/', '', $line4_col1);
        $line4_col1_padding = str_repeat(' ', max(0, $col1Width - mb_strlen($line4_col1_plain)));

        if ($atTradingHub) {
            $line4_col2 = $this->colorize('  [b]', 'label') . ' - Browse ships (Shipyard)';
        } else {
            $line4_col2 = $this->colorize('  [b]', 'dim') . ' - ' . $this->colorize('Shipyard (Trading Hub only)', 'dim');
        }

        $this->line($line4_col1 . $line4_col1_padding . $line4_col2);

        $this->newLine();
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
    }

    private function showPlansShop(): void
    {
        if (!LocationValidator::isAtPlansHub($this->player)) {
            system('stty sane');
            $this->clearScreen();
            $this->error('This trading hub does not sell upgrade plans.');
            $this->newLine();
            $this->line($this->colorize('  Upgrade plans are only available at special trading hubs (~5% of all hubs).', 'dim'));
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            system('stty -icanon -echo');
            fgetc(STDIN);
            $this->refreshInterface();
            return;
        }

        $tradingHub = LocationValidator::getTradingHub($this->player);
        $handler = new PlansShopHandler($this, $this->termWidth);
        $handler->show($this->player, $tradingHub);

        $this->refreshInterface();
    }

    private function showTradingInterface(): void
    {
        $tradingHub = LocationValidator::getTradingHub($this->player);

        if (!$tradingHub || !$tradingHub->is_active) {
            system('stty sane');
            $this->clearScreen();
            $this->error('No active trading hub at this location.');
            $this->newLine();
            $this->line($this->colorize('  Mineral trading is only available at trading hubs.', 'dim'));
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            system('stty -icanon -echo');
            fgetc(STDIN);
            $this->refreshInterface();
            return;
        }

        $handler = new MineralTradingHandler($this, $this->termWidth);
        $handler->show($this->player, $tradingHub);

        $this->refreshInterface();
    }

    private function showTravelInterface(): void
    {
        system('stty sane');

        $location = $this->player->currentLocation;
        if (!$location) {
            $this->clearScreen();
            $this->error('You must be at a location to travel.');
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            system('stty -icanon -echo');
            fgetc(STDIN);
            $this->refreshInterface();
            return;
        }

        // Load available warp gates
        $gates = $location->outgoingGates()
            ->with('destinationPoi')
            ->where('is_hidden', false)
            ->where('status', 'active')
            ->get();

        if ($gates->isEmpty()) {
            $this->clearScreen();
            $this->error('No warp gates available from this location.');
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            system('stty -icanon -echo');
            fgetc(STDIN);
            $this->refreshInterface();
            return;
        }

        $running = true;
        while ($running) {
            // Reload player and ship data
            $this->player->refresh();
            $this->player->load('activeShip');
            $ship = $this->player->activeShip;
            $ship->regenerateFuel();

            $this->clearScreen();

            // Header
            $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
            $this->line($this->colorize('  WARP GATE TRAVEL', 'header'));
            $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
            $this->newLine();

            $this->line($this->colorize('  Current Location: ', 'label') . $location->name);
            $this->line($this->colorize('  Fuel: ', 'label') .
                       $this->colorize($ship->current_fuel . '/' . $ship->max_fuel, 'highlight'));
            $this->newLine();

            $this->line($this->colorize('  AVAILABLE DESTINATIONS:', 'header'));
            $this->newLine();

            // Display available gates (limit to first 9)
            $displayGates = $gates->take(9);
            foreach ($displayGates as $index => $gate) {
                $number = $index + 1;
                $destination = $gate->destinationPoi;
                $distance = $gate->distance ?? $gate->calculateDistance();

                // Calculate fuel cost (distance affects fuel consumption)
                $fuelCost = $this->calculateFuelCost($distance, $ship);

                $canAfford = $ship->current_fuel >= $fuelCost;
                $statusColor = $canAfford ? 'highlight' : 'dim';
                $status = $canAfford ? '' : ' ' . $this->colorize('[INSUFFICIENT FUEL]', 'dim');

                $this->line(
                    $this->colorize("  [{$number}] ", 'label') .
                    $this->colorize($destination->name, $statusColor) .
                    $this->colorize(' - Distance: ' . round($distance, 1), 'dim') .
                    $this->colorize(' - Fuel Cost: ' . $fuelCost, $statusColor) .
                    $status
                );

                // Show destination type
                $typeInfo = '      Type: ' . $destination->type->name;
                if ($destination->type === PointOfInterestType::STAR) {
                    $tradingHub = $destination->tradingHub;
                    if ($tradingHub && $tradingHub->is_active) {
                        $typeInfo .= $this->colorize(' [Trading Hub]', 'trade');
                    }
                }
                $this->line($this->colorize($typeInfo, 'dim'));
                $this->newLine();
            }

            $this->line($this->colorize(str_repeat('─', $this->termWidth), 'border'));
            $this->line('  ' . $this->colorize('[1-9]', 'label') . ' Select destination  |  ' .
                       $this->colorize('[q]', 'label') . ' Cancel');
            $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));

            // Get input
            system('stty -icanon -echo');
            $char = fgetc(STDIN);

            if ($char === 'q' || $char === "\033") {
                $running = false;
            } elseif (is_numeric($char) && $char >= '1' && $char <= '9') {
                $selectedIndex = (int)$char - 1;
                if ($selectedIndex < $displayGates->count()) {
                    $selectedGate = $displayGates[$selectedIndex];
                    $this->executeTravel($selectedGate);
                    $running = false;
                }
            }
        }

        $this->refreshInterface();
    }

    private function calculateFuelCost(float $distance, $ship): int
    {
        // Base fuel cost is distance divided by 10
        $baseCost = (int) ceil($distance / 10);

        // Warp drive reduces fuel consumption
        // Higher warp drive = better efficiency
        $efficiency = $ship->warp_drive ?? 1;
        $fuelCost = max(1, (int) floor($baseCost / $efficiency));

        return $fuelCost;
    }

    private function executeTravel($gate): void
    {
        $ship = $this->player->activeShip;
        $destination = $gate->destinationPoi;
        $distance = $gate->distance ?? $gate->calculateDistance();
        $fuelCost = $this->calculateFuelCost($distance, $ship);

        $this->clearScreen();
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->line($this->colorize('  CONFIRM TRAVEL', 'header'));
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->newLine();

        $this->line($this->colorize('  Destination: ', 'label') . $this->colorize($destination->name, 'highlight'));
        $this->line($this->colorize('  Distance: ', 'label') . round($distance, 1) . ' units');
        $this->line($this->colorize('  Fuel Cost: ', 'label') . $this->colorize($fuelCost, 'trade'));
        $this->newLine();
        $this->line($this->colorize('  Current Fuel: ', 'label') . $ship->current_fuel);
        $this->line($this->colorize('  Fuel After Travel: ', 'label') .
                   $this->colorize($ship->current_fuel - $fuelCost, 'highlight'));
        $this->newLine();

        if (!$ship->consumeFuel($fuelCost)) {
            $this->error('  INSUFFICIENT FUEL!');
            $this->newLine();
            $this->line($this->colorize('  You need ' . $fuelCost . ' fuel but only have ' . $ship->current_fuel, 'dim'));
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            fgetc(STDIN);
            return;
        }

        // Check for pirate encounters
        $pirateService = app(PirateEncounterService::class);
        if ($pirateService->hasPiratePresence($gate)) {
            $encounter = $pirateService->getEncounter($gate);

            if ($encounter) {
                $pirateHandler = new PirateEncounterHandler($this, $this->termWidth);
                $outcome = $pirateHandler->handleEncounter($this->player, $encounter);

                // Handle outcomes
                if ($outcome === 'dead') {
                    // Player died - ship destroyed, respawned at trading hub
                    // Reload player data
                    $this->player->refresh();
                    $this->player->load('currentLocation.children', 'currentLocation.parent');

                    // Don't update location - PlayerDeathService already did that
                    // Show message and return to main interface
                    $this->clearScreen();
                    $this->line($this->colorize('  You have respawned at ' . $this->player->currentLocation->name, 'highlight'));
                    $this->newLine();
                    $this->line($this->colorize('  Press any key to continue...', 'dim'));
                    fgetc(STDIN);
                    return;
                } elseif ($outcome === 'escaped') {
                    // Successfully escaped - refund some fuel? Or just continue
                    $this->clearScreen();
                    $this->line($this->colorize('  ✓ You escaped the pirates!', 'highlight'));
                    $this->newLine();
                    $this->line($this->colorize('  Press any key to continue...', 'dim'));
                    fgetc(STDIN);
                    // Don't proceed with travel - stay at current location
                    return;
                } elseif ($outcome === 'surrendered') {
                    // Surrendered - cargo/upgrades lost but can continue
                    // Reload player and ship data
                    $this->player->refresh();
                    $this->player->load('activeShip');
                    // Continue with travel
                } elseif ($outcome === 'victory') {
                    // Won the fight - reload data and continue
                    $this->player->refresh();
                    $this->player->load('activeShip');
                    // Continue with travel
                }
            }
        }

        // Track last trading hub for respawn
        if ($destination->tradingHub && $destination->tradingHub->is_active) {
            $this->player->last_trading_hub_poi_id = $destination->id;
        }

        // Update player location
        $this->player->current_poi_id = $destination->id;
        $this->player->save();

        // Reload location relationship
        $this->player->load('currentLocation.children', 'currentLocation.parent');

        // Success message
        $this->line($this->colorize('  ✓ TRAVEL SUCCESSFUL!', 'highlight'));
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->newLine();
        $this->line('  You have arrived at: ' . $this->colorize($destination->name, 'highlight'));
        $this->line('  Fuel remaining: ' . $this->colorize($ship->current_fuel, 'trade'));
        $this->newLine();
        $this->line($this->colorize('  Press any key to continue...', 'dim'));
        fgetc(STDIN);
    }
}

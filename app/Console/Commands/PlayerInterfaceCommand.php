<?php

namespace App\Console\Commands;

use App\Console\Traits\ConsoleColorizer;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Player;
use Illuminate\Console\Command;

class PlayerInterfaceCommand extends Command
{
    use ConsoleColorizer;

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
        $this->renderFooter();
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

        $maxLines = max(count($leftLines), count($rightLines));
        $leftWidth = 60;

        for ($i = 0; $i < $maxLines; $i++) {
            $left = $leftLines[$i] ?? '';
            $right = $rightLines[$i] ?? '';

            // Remove ANSI codes for length calculation
            $leftPlain = preg_replace('/\033\[[0-9;]*m/', '', $left);
            $padding = str_repeat(' ', max(0, $leftWidth - mb_strlen($leftPlain)));

            $this->line($left . $padding . $right);
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
        $output .= $this->colorize('╔' . str_repeat('═', 58) . '╗', 'border') . "\n";
        $output .= $this->colorize('║', 'border') . ' ' .
                   $this->colorize('CURRENT LOCATION', 'header') .
                   str_repeat(' ', 42) .
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

        $output .= $this->colorize('╚' . str_repeat('═', 58) . '╝', 'border');

        return $output;
    }

    private function getShipStatsColumn(): string
    {
        $output = '';
        $ship = $this->player->activeShip;

        // Regenerate fuel before displaying
        $ship->regenerateFuel();

        $output .= $this->colorize('╔' . str_repeat('═', 58) . '╗', 'border') . "\n";
        $output .= $this->colorize('║', 'border') . ' ' .
                   $this->colorize('SHIP STATUS', 'header') .
                   str_repeat(' ', 47) .
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

        $output .= $this->colorize('╚' . str_repeat('═', 58) . '╝', 'border');

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

    private function renderFooter(): void
    {
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->line($this->colorize('  Use player:interface {player_id} to refresh this display', 'dim'));
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
    }
}

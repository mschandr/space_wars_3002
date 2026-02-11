<?php

namespace App\Console\Shops;

use App\Console\Traits\ConsoleBoxRenderer;
use App\Console\Traits\ConsoleColorizer;
use App\Console\Traits\TerminalInputHandler;
use App\Models\Player;
use App\Models\TradingHub;
use App\Services\ShipUpgradeService;
use Illuminate\Console\Command;

class ComponentShopHandler
{
    use ConsoleBoxRenderer;
    use ConsoleColorizer;
    use TerminalInputHandler;

    private Command $command;

    private int $termWidth;

    public function __construct(Command $command, int $termWidth = 120)
    {
        $this->command = $command;
        $this->termWidth = $termWidth;
    }

    /**
     * Display the component shop interface
     */
    public function show(Player $player, TradingHub $tradingHub): void
    {
        system('stty sane');

        $upgradeService = app(ShipUpgradeService::class);

        $running = true;
        while ($running) {
            // Reload player and ship data to get latest values
            $player->refresh();
            $player->load('activeShip.ship');
            $ship = $player->activeShip;

            $this->clearScreen();

            // Header
            $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
            $this->line($this->colorize('  COMPONENT SHOP - SHIP UPGRADES', 'header'));
            $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
            $this->newLine();

            // Location and player info
            $this->line($this->colorize('  Trading Hub: ', 'label').$this->colorize($tradingHub->name, 'trade'));
            $this->line($this->colorize('  Ship: ', 'label').$ship->name);
            $this->line($this->colorize('  Credits Available: ', 'label').
                       $this->colorize(number_format($player->credits, 2), 'trade'));
            $this->newLine();

            // Get upgrade info (recalculated each loop with fresh data)
            $upgradeInfo = $upgradeService->getUpgradeInfo($ship);
            $components = ['max_fuel', 'max_hull', 'weapons', 'cargo_hold', 'sensors', 'warp_drive'];

            // Display components with numbers
            $this->line($this->colorize('  AVAILABLE UPGRADES:', 'header'));
            $this->newLine();

            foreach ($components as $index => $component) {
                $info = $upgradeInfo[$component];
                $number = $index + 1;

                $statusColor = $info['can_upgrade'] ? 'highlight' : 'dim';
                $status = $info['can_upgrade'] ? 'Available' : 'MAX LEVEL';

                $line = '  '.$this->colorize("[$number]", 'label').' '.
                        $this->colorize(strtoupper(str_replace('_', ' ', $component)), 'header').
                        str_repeat(' ', 20 - strlen($component));

                $line .= $this->colorize('Level: ', 'dim').
                         $this->colorize($info['current_level'], 'highlight').
                         $this->colorize('/'.$info['max_level'], 'dim').'  ';

                $line .= $this->colorize('Value: ', 'dim').
                         $this->colorize($info['current_value'], 'highlight');

                if ($info['can_upgrade']) {
                    $line .= $this->colorize(' → ', 'dim').
                             $this->colorize($info['next_value'], 'trade');
                    $line .= '  '.$this->colorize('Cost: ', 'dim').
                             $this->colorize(number_format($info['upgrade_cost']), 'trade').' credits';
                } else {
                    $line .= '  '.$this->colorize('[MAX]', 'dim');
                }

                $this->line($line);
            }

            $this->newLine();
            $this->line($this->colorize(str_repeat('─', $this->termWidth), 'border'));
            $this->newLine();
            $this->line($this->colorize('  [1-6]', 'label').' - Purchase upgrade    '.
                       $this->colorize('[ESC/q]', 'label').' - Back to main interface');
            $this->newLine();
            $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));

            // TODO: (Input Handling) fgetc(STDIN) reads one byte. If user types "12", the "2" stays
            // in the input buffer and gets processed as the next input. Consider flushing the input
            // buffer after reading, or switching to fgets() with validation.
            system('stty -icanon -echo');
            $char = fgetc(STDIN);

            if ($char === 'q' || $char === "\033") {
                $running = false;
            } elseif (is_numeric($char) && $char >= '1' && $char <= '6') {
                $componentIndex = (int) $char - 1;
                $component = $components[$componentIndex];
                $this->purchaseUpgrade($player, $component, $upgradeInfo[$component], $upgradeService);
            }
        }

        system('stty sane');
    }

    /**
     * Handle the purchase upgrade flow
     */
    private function purchaseUpgrade(Player $player, string $component, array $info, ShipUpgradeService $upgradeService): void
    {
        system('stty sane');
        $this->clearScreen();

        $ship = $player->activeShip;
        $componentName = strtoupper(str_replace('_', ' ', $component));

        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->line($this->colorize('  CONFIRM PURCHASE', 'header'));
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->newLine();

        if (! $info['can_upgrade']) {
            $this->line($this->colorize('  This component is already at maximum level!', 'dim'));
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            system('stty -icanon -echo');
            fgetc(STDIN);

            return;
        }

        $this->line($this->colorize('  Component: ', 'label').$this->colorize($componentName, 'highlight'));
        $this->line($this->colorize('  Current Level: ', 'label').$info['current_level']);
        $this->line($this->colorize('  Current Value: ', 'label').$info['current_value']);
        $this->newLine();
        $this->line($this->colorize('  Upgrade to Level: ', 'label').$this->colorize($info['current_level'] + 1, 'trade'));
        $this->line($this->colorize('  New Value: ', 'label').$this->colorize($info['next_value'], 'trade'));
        $this->newLine();
        $this->line($this->colorize('  Cost: ', 'label').
                   $this->colorize(number_format($info['upgrade_cost']).' credits', 'trade'));
        $this->line($this->colorize('  Your Credits: ', 'label').
                   $this->colorize(number_format($player->credits, 2), 'highlight'));

        if ($player->credits < $info['upgrade_cost']) {
            $this->newLine();
            $this->line($this->colorize('  INSUFFICIENT CREDITS!', 'dim'));
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            system('stty -icanon -echo');
            fgetc(STDIN);

            return;
        }

        $this->newLine();
        $this->line($this->colorize(str_repeat('─', $this->termWidth), 'border'));
        $this->newLine();
        $this->line($this->colorize('  Confirm purchase? ', 'header').
                   $this->colorize('[y]', 'label').' Yes  '.
                   $this->colorize('[n]', 'label').' No');
        $this->newLine();

        system('stty -icanon -echo');
        $confirm = fgetc(STDIN);

        if (strtolower($confirm) === 'y') {
            $result = $upgradeService->upgrade($ship, $component);

            $this->clearScreen();
            $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));

            if ($result['success']) {
                $this->line($this->colorize('  ✓ UPGRADE SUCCESSFUL!', 'trade'));
                $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
                $this->newLine();
                $this->line($this->colorize('  '.$result['message'], 'highlight'));
                $this->line($this->colorize('  New Value: ', 'label').$this->colorize($result['new_value'], 'trade'));
                $this->line($this->colorize('  Credits Spent: ', 'label').number_format($result['cost']));
                $this->line($this->colorize('  Credits Remaining: ', 'label').
                           $this->colorize(number_format($player->credits, 2), 'trade'));

                // Reload player data
                $player->refresh();
            } else {
                $this->line($this->colorize('  ✗ UPGRADE FAILED', 'dim'));
                $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
                $this->newLine();
                $this->line($this->colorize('  '.$result['message'], 'dim'));
            }
        } else {
            $this->clearScreen();
            $this->line($this->colorize('  Purchase cancelled.', 'dim'));
        }

        $this->newLine();
        $this->line($this->colorize('  Press any key to continue...', 'dim'));
        system('stty -icanon -echo');
        fgetc(STDIN);
    }

    /**
     * Proxy method to output a line
     */
    private function line(string $text): void
    {
        $this->command->line($text);
    }

    /**
     * Proxy method to output a newline
     */
    private function newLine(int $count = 1): void
    {
        $this->command->newLine($count);
    }

    /**
     * Proxy method to output an error
     */
    private function error(string $text): void
    {
        $this->command->error($text);
    }
}

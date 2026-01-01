<?php

namespace App\Console\Shops;

use App\Console\Traits\ConsoleBoxRenderer;
use App\Console\Traits\ConsoleColorizer;
use App\Console\Traits\TerminalInputHandler;
use App\Models\Plan;
use App\Models\Player;
use App\Models\TradingHub;
use Illuminate\Console\Command;

class PlansShopHandler
{
    use ConsoleColorizer;
    use ConsoleBoxRenderer;
    use TerminalInputHandler;

    private Command $command;
    private int $termWidth;

    public function __construct(Command $command, int $termWidth = 120)
    {
        $this->command = $command;
        $this->termWidth = $termWidth;
    }

    /**
     * Display the plans shop interface
     */
    public function show(Player $player, TradingHub $tradingHub): void
    {
        system('stty sane');

        // Load available plans for this hub
        $plans = $tradingHub->plans;

        if ($plans->isEmpty()) {
            $this->clearScreen();
            $this->error('No plans available at this trading hub.');
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            system('stty -icanon -echo');
            fgetc(STDIN);
            return;
        }

        $running = true;
        while ($running) {
            // Reload player data to get latest values
            $player->refresh();
            $player->load('plans');

            $this->clearScreen();

            // Header
            $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
            $this->line($this->colorize('  UPGRADE PLANS SHOP', 'header'));
            $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
            $this->newLine();

            $this->line('  Trading Hub: ' . $this->colorize($tradingHub->name, 'trade'));
            $this->line('  Credits Available: ' . $this->colorize(number_format($player->credits, 2), 'highlight'));
            $this->newLine();

            $this->line($this->colorize('  AVAILABLE PLANS:', 'header'));
            $this->newLine();

            // Display plans (limit to first 9 for keyboard selection)
            $displayPlans = $plans->take(9);
            foreach ($displayPlans as $index => $plan) {
                $number = $index + 1;
                $ownedCount = $player->getPlanCount($plan->id);
                $currentBonus = $ownedCount * $plan->additional_levels;
                $projectedBonus = $currentBonus + $plan->additional_levels;

                $this->line($this->colorize("  [{$number}] ", 'label') . $this->colorize(strtoupper($plan->getFullName()), 'trade'));
                $this->line('      ' . $plan->description);
                $this->line('      ' . $this->colorize('Grants: +' . $plan->additional_levels . ' ' . $plan->getComponentDisplayName() . ' upgrade levels', 'highlight'));
                $this->line('      ' . $this->colorize('Price: ' . number_format($plan->price, 2) . ' credits', 'dim'));

                if ($plan->requirements && isset($plan->requirements['min_level'])) {
                    $this->line('      ' . $this->colorize('Requires: Level ' . $plan->requirements['min_level'], 'dim'));
                }

                if ($ownedCount > 0) {
                    $this->line('      ' . $this->colorize("You own: {$ownedCount}x (Total bonus: +{$currentBonus}, becomes +{$projectedBonus})", 'highlight'));
                } else {
                    $this->line('      ' . $this->colorize("You own: 0 (Total bonus: +0, becomes +{$projectedBonus})", 'dim'));
                }

                $this->newLine();
            }

            $this->line($this->colorize(str_repeat('─', $this->termWidth), 'border'));
            $this->line('  ' . $this->colorize('[1-9]', 'label') . ' Select plan  |  ' . $this->colorize('[q]', 'label') . ' Return to interface');
            $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));

            // Get input
            system('stty -icanon -echo');
            $char = fgetc(STDIN);

            if ($char === 'q' || $char === "\033") {
                $running = false;
            } elseif (is_numeric($char) && $char >= '1' && $char <= '9') {
                $selectedIndex = (int)$char - 1;
                if ($selectedIndex < $displayPlans->count()) {
                    $selectedPlan = $displayPlans[$selectedIndex];
                    $this->purchasePlanFlow($player, $selectedPlan, $tradingHub);
                }
            }
        }
    }

    /**
     * Handle the purchase plan flow
     */
    private function purchasePlanFlow(Player $player, Plan $plan, TradingHub $hub): void
    {
        // Get current ownership
        $ownedCount = $player->getPlanCount($plan->id);
        $currentBonus = $ownedCount * $plan->additional_levels;
        $projectedBonus = $currentBonus + $plan->additional_levels;

        $this->clearScreen();

        // Display plan details
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->line($this->colorize('  PURCHASE PLAN - CONFIRMATION', 'header'));
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->newLine();

        $this->line('  Plan: ' . $this->colorize($plan->getFullName(), 'trade'));
        $this->line('  Component: ' . $this->colorize($plan->getComponentDisplayName(), 'highlight'));
        $this->line('  Additional Levels: ' . $this->colorize('+' . $plan->additional_levels, 'highlight'));
        $this->line('  Price: ' . $this->colorize(number_format($plan->price, 2) . ' credits', 'trade'));
        $this->newLine();

        $this->line('  Your Credits: ' . $this->colorize(number_format($player->credits, 2), 'highlight'));
        $this->line('  Credits After Purchase: ' . $this->colorize(number_format($player->credits - $plan->price, 2), 'dim'));
        $this->newLine();

        if ($ownedCount > 0) {
            $this->line('  Current Ownership: ' . $this->colorize("{$ownedCount}x (+{$currentBonus} total)", 'highlight'));
            $this->line('  After Purchase: ' . $this->colorize(($ownedCount + 1) . "x (+{$projectedBonus} total)", 'trade'));
        } else {
            $this->line('  Current Ownership: ' . $this->colorize('None', 'dim'));
            $this->line('  After Purchase: ' . $this->colorize("1x (+{$projectedBonus} total)", 'trade'));
        }
        $this->newLine();

        $this->line($this->colorize(str_repeat('─', $this->termWidth), 'border'));
        $this->line('  Confirm purchase? ' . $this->colorize('[y/n]', 'label'));
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));

        // Get confirmation
        $char = fgetc(STDIN);

        if ($char === 'y' || $char === 'Y') {
            // Attempt purchase
            $result = $player->purchasePlan($plan);

            $this->clearScreen();
            $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));

            if ($result['success']) {
                $this->line($this->colorize('  ✓ SUCCESS', 'trade'));
                $this->newLine();
                $this->line('  ' . $result['message']);
                $this->newLine();
                $this->line('  You can now upgrade ' . $this->colorize($plan->getComponentDisplayName(), 'highlight') . ' beyond the normal maximum!');
            } else {
                $this->line($this->colorize('  ✗ PURCHASE FAILED', 'dim'));
                $this->newLine();
                $this->line('  ' . $result['message']);

                if (isset($result['requirements'])) {
                    $this->newLine();
                    $this->line('  Requirements:');
                    if (isset($result['requirements']['min_level'])) {
                        $this->line('    - Minimum Level: ' . $result['requirements']['min_level']);
                    }
                }
            }

            $this->newLine();
            $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            fgetc(STDIN);
        }
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

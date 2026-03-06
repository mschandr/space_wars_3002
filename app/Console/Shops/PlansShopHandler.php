<?php

namespace App\Console\Shops;

use App\Models\Plan;
use App\Models\Player;
use App\Models\TradingHub;

class PlansShopHandler extends BaseShopHandler
{
    /**
     * Display the plans shop interface
     */
    public function show(Player $player, TradingHub $tradingHub): void
    {
        $this->resetTerminal();
        $plans = $tradingHub->plans;

        if ($plans->isEmpty()) {
            $this->displayNoPlansAvailable();
            return;
        }

        $running = true;
        while ($running) {
            $player->refresh();
            $player->load('plans');

            $this->clearScreen();
            $this->renderShopHeader('UPGRADE PLANS SHOP');
            $this->displayPlayerInfo($tradingHub, $player);

            $displayPlans = $plans->take(9);
            $this->displayAvailablePlans($player, $displayPlans);
            $running = $this->handlePlanSelection($player, $displayPlans, $tradingHub);
        }
    }

    /**
     * Display message when no plans available.
     */
    private function displayNoPlansAvailable(): void
    {
        $this->clearScreen();
        $this->error('No plans available at this trading hub.');
        $this->newLine();
        $this->waitForAnyKey();
    }

    /**
     * Display player and hub info.
     */
    private function displayPlayerInfo(TradingHub $tradingHub, Player $player): void
    {
        $this->line('  Trading Hub: '.$this->colorize($tradingHub->name, 'trade'));
        $this->line('  Credits Available: '.$this->colorize(number_format($player->credits, 2), 'highlight'));
        $this->newLine();
    }

    /**
     * Display available plans.
     */
    private function displayAvailablePlans(Player $player, $displayPlans): void
    {
        $this->line($this->colorize('  AVAILABLE PLANS:', 'header'));
        $this->newLine();

        foreach ($displayPlans as $index => $plan) {
            $this->displayPlanEntry($index, $plan, $player);
        }
    }

    /**
     * Display a single plan entry.
     */
    private function displayPlanEntry(int $index, Plan $plan, Player $player): void
    {
        $number = $index + 1;
        $ownedCount = $player->getPlanCount($plan->id);
        $currentBonus = $ownedCount * $plan->additional_levels;
        $projectedBonus = $currentBonus + $plan->additional_levels;

        $this->line($this->colorize("  [{$number}] ", 'label').$this->colorize(strtoupper($plan->getFullName()), 'trade'));
        $this->line('      '.$plan->description);
        $this->line('      '.$this->colorize('Grants: +'.$plan->additional_levels.' '.$plan->getComponentDisplayName().' upgrade levels', 'highlight'));
        $this->line('      '.$this->colorize('Price: '.number_format($plan->price, 2).' credits', 'dim'));

        if ($plan->requirements && isset($plan->requirements['min_level'])) {
            $this->line('      '.$this->colorize('Requires: Level '.$plan->requirements['min_level'], 'dim'));
        }

        $this->displayPlanOwnership($ownedCount, $currentBonus, $projectedBonus);
        $this->newLine();
    }

    /**
     * Display plan ownership info.
     */
    private function displayPlanOwnership(int $ownedCount, int $currentBonus, int $projectedBonus): void
    {
        if ($ownedCount > 0) {
            $this->line('      '.$this->colorize("You own: {$ownedCount}x (Total bonus: +{$currentBonus}, becomes +{$projectedBonus})", 'highlight'));
        } else {
            $this->line('      '.$this->colorize("You own: 0 (Total bonus: +0, becomes +{$projectedBonus})", 'dim'));
        }
    }

    /**
     * Handle plan selection input.
     *
     * @return bool Whether to continue running
     */
    private function handlePlanSelection(Player $player, $displayPlans, TradingHub $tradingHub): bool
    {
        $this->renderSeparator();
        $this->line('  '.$this->colorize('[1-9]', 'label').' Select plan  |  '.$this->colorize('[q]', 'label').' Return to interface');
        $this->renderBorder();

        $char = $this->readChar();

        if ($this->isQuitKey($char)) {
            return false;
        }

        if (is_numeric($char) && $char >= '1' && $char <= '9') {
            $selectedIndex = (int) $char - 1;
            if ($selectedIndex < $displayPlans->count()) {
                $selectedPlan = $displayPlans[$selectedIndex];
                $this->purchasePlanFlow($player, $selectedPlan, $tradingHub);
            }
        }

        return true;
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
        $this->renderShopHeader('PURCHASE PLAN - CONFIRMATION');

        $this->line('  Plan: '.$this->colorize($plan->getFullName(), 'trade'));
        $this->line('  Component: '.$this->colorize($plan->getComponentDisplayName(), 'highlight'));
        $this->line('  Additional Levels: '.$this->colorize('+'.$plan->additional_levels, 'highlight'));
        $this->line('  Price: '.$this->colorize(number_format($plan->price, 2).' credits', 'trade'));
        $this->newLine();

        $this->line('  Your Credits: '.$this->colorize(number_format($player->credits, 2), 'highlight'));
        $this->line('  Credits After Purchase: '.$this->colorize(number_format($player->credits - $plan->price, 2), 'dim'));
        $this->newLine();

        if ($ownedCount > 0) {
            $this->line('  Current Ownership: '.$this->colorize("{$ownedCount}x (+{$currentBonus} total)", 'highlight'));
            $this->line('  After Purchase: '.$this->colorize(($ownedCount + 1)."x (+{$projectedBonus} total)", 'trade'));
        } else {
            $this->line('  Current Ownership: '.$this->colorize('None', 'dim'));
            $this->line('  After Purchase: '.$this->colorize("1x (+{$projectedBonus} total)", 'trade'));
        }
        $this->newLine();

        $this->renderSeparator();
        $this->line('  Confirm purchase? '.$this->colorize('[y/n]', 'label'));
        $this->renderBorder();

        // Get confirmation
        $char = fgetc(STDIN);

        if ($char === 'y' || $char === 'Y') {
            // Attempt purchase
            $result = $player->purchasePlan($plan);

            $this->clearScreen();
            $this->renderBorder();

            if ($result['success']) {
                $this->line($this->colorize('  ✓ SUCCESS', 'trade'));
                $this->newLine();
                $this->line('  '.$result['message']);
                $this->newLine();
                $this->line('  You can now upgrade '.$this->colorize($plan->getComponentDisplayName(), 'highlight').' beyond the normal maximum!');
            } else {
                $this->line($this->colorize('  ✗ PURCHASE FAILED', 'dim'));
                $this->newLine();
                $this->line('  '.$result['message']);

                if (isset($result['requirements'])) {
                    $this->newLine();
                    $this->line('  Requirements:');
                    if (isset($result['requirements']['min_level'])) {
                        $this->line('    - Minimum Level: '.$result['requirements']['min_level']);
                    }
                }
            }

            $this->newLine();
            $this->renderBorder();
            $this->newLine();
            $this->waitForAnyKey();
        }
    }
}

<?php

namespace App\Console\Shops;

use App\Models\Player;
use App\Models\TradingHub;
use App\Services\ShipRepairService;

class RepairShopHandler extends BaseShopHandler
{
    /**
     * Display the repair shop interface
     */
    public function show(Player $player, TradingHub $tradingHub): void
    {
        $this->resetTerminal();
        $repairService = app(ShipRepairService::class);

        $running = true;
        while ($running) {
            $player->refresh();
            $player->load('activeShip.ship');
            $ship = $player->activeShip;

            $this->clearScreen();
            $this->renderShopHeader('REPAIR & REFIT - SHIP MAINTENANCE');
            $this->displayPlayerInfo($tradingHub, $ship, $player);

            $repairInfo = $repairService->getRepairInfo($ship);

            if ($repairInfo['total_repair_cost'] > 0) {
                $running = $this->displayRepairsAvailable($repairInfo, $player, $ship, $repairService);
            } else {
                $running = $this->displayPerfectCondition();
            }
        }
    }

    /**
     * Display player and ship header information.
     */
    private function displayPlayerInfo(TradingHub $tradingHub, $ship, Player $player): void
    {
        $this->line($this->colorize('  Trading Hub: ', 'label').$this->colorize($tradingHub->name, 'trade'));
        $this->line($this->colorize('  Ship: ', 'label').$ship->name);
        $this->line($this->colorize('  Credits Available: ', 'label').
                   $this->colorize('$'.number_format($player->credits, 2), 'trade'));
        $this->newLine();
    }

    /**
     * Display ship status and repair options.
     *
     * @return bool Whether to continue running
     */
    private function displayRepairsAvailable(array $repairInfo, Player $player, $ship, ShipRepairService $repairService): bool
    {
        $this->displayShipStatus($ship, $repairInfo);
        $this->displayDamagedComponents($repairInfo);
        $this->displayRepairCostAndOptions($repairInfo, $player);

        $choice = strtolower($this->command->ask('Choose an option'));

        return $this->handleRepairChoice($choice, $repairInfo, $player, $ship, $repairService);
    }

    /**
     * Display ship hull status.
     */
    private function displayShipStatus($ship, array $repairInfo): void
    {
        $this->line($this->colorize('  SHIP STATUS:', 'header'));
        $this->newLine();

        $hullPercent = $ship->max_hull > 0 ? (int) (($ship->hull / $ship->max_hull) * 100) : 100;
        $hullColor = $hullPercent >= 80 ? 'highlight' : ($hullPercent >= 50 ? 'trade' : 'pirate');

        $this->line('  '.$this->colorize('Hull Integrity: ', 'label').
                   $this->colorize("{$ship->hull}/{$ship->max_hull}", $hullColor).
                   $this->colorize(" ({$hullPercent}%)", 'dim'));

        if ($repairInfo['needs_hull_repair']) {
            $this->line('    '.$this->colorize('Damage: ', 'dim').
                       $this->colorize("{$repairInfo['hull_damage']} hull points", 'pirate'));
            $this->line('    '.$this->colorize('Repair Cost: ', 'dim').
                       $this->colorize('$'.number_format($repairInfo['hull_repair_cost']), 'trade'));
        } else {
            $this->line('    '.$this->colorize('✓ No damage', 'highlight'));
        }

        $this->newLine();
    }

    /**
     * Display damaged components list.
     */
    private function displayDamagedComponents(array $repairInfo): void
    {
        if (empty($repairInfo['downgraded_components'])) {
            return;
        }

        $this->line($this->colorize('  DAMAGED COMPONENTS:', 'header'));
        $this->newLine();

        foreach ($repairInfo['downgraded_components'] as $component) {
            $this->line('  '.$this->colorize($component['name'].':', 'label'));
            $this->line('    '.$this->colorize('Current: ', 'dim').
                       $this->colorize($component['current'], 'pirate').
                       $this->colorize(' → Should be: ', 'dim').
                       $this->colorize($component['should_be'], 'highlight'));
            $this->line('    '.$this->colorize('Repair Cost: ', 'dim').
                       $this->colorize('$'.number_format($component['repair_cost']), 'trade'));
        }
        $this->newLine();
    }

    /**
     * Display total repair cost and available options.
     */
    private function displayRepairCostAndOptions(array $repairInfo, Player $player): void
    {
        $this->renderSeparator();
        $this->newLine();

        $canAfford = $player->credits >= $repairInfo['total_repair_cost'];
        $costColor = $canAfford ? 'trade' : 'pirate';

        $this->line('  '.$this->colorize('TOTAL REPAIR COST: ', 'label').
                   $this->colorize('$'.number_format($repairInfo['total_repair_cost']), $costColor));

        if (! $canAfford) {
            $shortage = $repairInfo['total_repair_cost'] - $player->credits;
            $this->line('  '.$this->colorize('⚠ Insufficient funds (need $'.number_format($shortage).' more)', 'pirate'));
        }

        $this->newLine();
        $this->renderSeparator();
        $this->newLine();

        $this->line('  '.$this->colorize('[R]', 'label').' Repair All - Fix everything');

        if ($repairInfo['needs_hull_repair']) {
            $this->line('  '.$this->colorize('[H]', 'label').' Hull Only - Repair hull damage only ($'.
                       number_format($repairInfo['hull_repair_cost']).')');
        }

        if ($repairInfo['needs_component_repair']) {
            $this->line('  '.$this->colorize('[C]', 'label').' Components Only - Repair damaged components ($'.
                       number_format($repairInfo['component_repair_cost']).')');
        }

        $this->line('  '.$this->colorize('[Q]', 'label').' Cancel - Return to hub');
        $this->newLine();
    }

    /**
     * Handle user's repair choice.
     *
     * @return bool Whether to continue running
     */
    private function handleRepairChoice(string $choice, array $repairInfo, Player $player, $ship, ShipRepairService $repairService): bool
    {
        return match ($choice) {
            'r' => ($this->showResult($repairService->repairAll($player, $ship)), true)[1],
            'h' => $repairInfo['needs_hull_repair']
                ? ($this->showResult($repairService->repairHull($player, $ship)), true)[1]
                : true,
            'c' => $repairInfo['needs_component_repair']
                ? ($this->showResult($repairService->repairComponents($player, $ship)), true)[1]
                : true,
            'q' => false,
            default => ($this->command->error('Invalid choice.'), sleep(1), true),
        };
    }

    /**
     * Display perfect condition message.
     *
     * @return bool Whether to continue running
     */
    private function displayPerfectCondition(): bool
    {
        $this->line('  '.$this->colorize('✓ Your ship is in perfect condition!', 'highlight'));
        $this->newLine();
        $this->line('  '.$this->colorize('[Q]', 'label').' Return to hub');
        $this->newLine();

        $choice = strtolower($this->command->ask(''));
        return $choice !== 'q' && $choice !== '';
    }

    /**
     * Show repair result
     */
    private function showResult(array $result): void
    {
        $this->newLine();

        if ($result['success']) {
            $this->line('  '.$this->colorize('✓ REPAIR COMPLETE', 'highlight'));
            $this->newLine();
            $this->line('  '.$result['message']);
            $this->line('  '.$this->colorize('Cost: $'.number_format($result['cost']), 'trade'));
        } else {
            $this->line('  '.$this->colorize('✗ REPAIR FAILED', 'pirate'));
            $this->newLine();
            $this->line('  '.$result['message']);
        }

        $this->newLine();
        $this->waitForAnyKey();
    }
}

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
            // Reload player and ship data to get latest values
            $player->refresh();
            $player->load('activeShip.ship');
            $ship = $player->activeShip;

            $this->clearScreen();

            // Header
            $this->renderShopHeader('REPAIR & REFIT - SHIP MAINTENANCE');

            // Location and player info
            $this->line($this->colorize('  Trading Hub: ', 'label').$this->colorize($tradingHub->name, 'trade'));
            $this->line($this->colorize('  Ship: ', 'label').$ship->name);
            $this->line($this->colorize('  Credits Available: ', 'label').
                       $this->colorize('$'.number_format($player->credits, 2), 'trade'));
            $this->newLine();

            // Get repair info
            $repairInfo = $repairService->getRepairInfo($ship);

            // Display ship status
            $this->line($this->colorize('  SHIP STATUS:', 'header'));
            $this->newLine();

            // Hull status
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

            // Component status
            if (! empty($repairInfo['downgraded_components'])) {
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

            // Total repair cost
            if ($repairInfo['total_repair_cost'] > 0) {
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

                // Options
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

                // Get input
                $choice = strtolower($this->command->ask('Choose an option'));

                switch ($choice) {
                    case 'r':
                        $result = $repairService->repairAll($player, $ship);
                        $this->showResult($result);
                        break;

                    case 'h':
                        if ($repairInfo['needs_hull_repair']) {
                            $result = $repairService->repairHull($player, $ship);
                            $this->showResult($result);
                        }
                        break;

                    case 'c':
                        if ($repairInfo['needs_component_repair']) {
                            $result = $repairService->repairComponents($player, $ship);
                            $this->showResult($result);
                        }
                        break;

                    case 'q':
                        $running = false;
                        break;

                    default:
                        $this->command->error('Invalid choice.');
                        sleep(1);
                        break;
                }
            } else {
                // Ship is in perfect condition
                $this->line('  '.$this->colorize('✓ Your ship is in perfect condition!', 'highlight'));
                $this->newLine();
                $this->line('  '.$this->colorize('[Q]', 'label').' Return to hub');
                $this->newLine();

                $choice = strtolower($this->command->ask(''));
                if ($choice === 'q' || $choice === '') {
                    $running = false;
                }
            }
        }
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

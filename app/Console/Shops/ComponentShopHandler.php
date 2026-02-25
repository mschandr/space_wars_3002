<?php

namespace App\Console\Shops;

use App\Enums\SlotType;
use App\Models\Player;
use App\Models\PlayerShipComponent;
use App\Models\TradingHub;
use App\Services\ComponentUpgradeService;

class ComponentShopHandler extends BaseShopHandler
{
    /**
     * Display the component shop interface
     */
    public function show(Player $player, TradingHub $tradingHub): void
    {
        $this->resetTerminal();

        $upgradeService = app(ComponentUpgradeService::class);

        $running = true;
        while ($running) {
            // Reload player and ship data to get latest values
            $player->refresh();
            $player->load('activeShip.ship', 'activeShip.components.component');
            $ship = $player->activeShip;

            $this->clearScreen();

            // Header
            $this->renderShopHeader('COMPONENT SHOP - UPGRADES');

            // Location and player info
            $this->line($this->colorize('  Trading Hub: ', 'label').$this->colorize($tradingHub->name, 'trade'));
            $this->line($this->colorize('  Ship: ', 'label').$ship->name);
            $this->line($this->colorize('  Credits Available: ', 'label').
                       $this->colorize(number_format($player->credits, 2), 'trade'));
            $this->newLine();

            // Group installed components by slot type
            $components = $ship->components->sortBy('slot_index');
            $upgradeableComponents = [];
            $displayIndex = 1;

            $this->line($this->colorize('  INSTALLED COMPONENTS:', 'header'));
            $this->newLine();

            foreach (SlotType::cases() as $slotType) {
                $slotComponents = $components->filter(
                    fn ($c) => ($c->slot_type instanceof SlotType ? $c->slot_type : SlotType::tryFrom($c->slot_type)) === $slotType
                );

                if ($slotComponents->isEmpty()) {
                    continue;
                }

                $this->line($this->colorize('  '.$slotType->label().':', 'dim'));

                foreach ($slotComponents as $installed) {
                    $info = $upgradeService->getUpgradeInfo($installed);
                    $upgradeableComponents[$displayIndex] = $installed;

                    $statusColor = $info['can_upgrade'] ? 'highlight' : 'dim';

                    $line = '    '.$this->colorize("[$displayIndex]", 'label').' '.
                            $this->colorize($installed->component->name, $info['can_upgrade'] ? 'header' : 'dim');

                    $line .= '  '.$this->colorize('Lvl ', 'dim').
                             $this->colorize($info['current_level'], 'highlight').
                             $this->colorize('/'.$info['max_level'], 'dim');

                    if ($info['can_upgrade']) {
                        $line .= '  '.$this->colorize('Cost: ', 'dim').
                                 $this->colorize(number_format($info['upgrade_cost']), 'trade').' credits';
                    } else {
                        $line .= '  '.$this->colorize('[MAX]', 'dim');
                    }

                    $this->line($line);
                    $displayIndex++;
                }

                $this->newLine();
            }

            if (empty($upgradeableComponents)) {
                $this->line($this->colorize('  No components installed on this ship.', 'dim'));
                $this->newLine();
            }

            $maxIndex = $displayIndex - 1;

            $this->line($this->colorize(str_repeat('─', $this->termWidth), 'border'));
            $this->newLine();
            $this->line($this->colorize('  [1-'.$maxIndex.']', 'label').' - Upgrade component    '.
                       $this->colorize('[ESC/q]', 'label').' - Back to main interface');
            $this->newLine();
            $this->renderBorder();

            $char = $this->readChar();

            if ($this->isQuitKey($char)) {
                $running = false;
            } elseif (is_numeric($char) && (int) $char >= 1 && (int) $char <= $maxIndex) {
                $selectedComponent = $upgradeableComponents[(int) $char] ?? null;
                if ($selectedComponent) {
                    $this->purchaseUpgrade($player, $selectedComponent, $upgradeService);
                }
            }
        }

        $this->resetTerminal();
    }

    /**
     * Handle the purchase upgrade flow
     */
    private function purchaseUpgrade(Player $player, PlayerShipComponent $installed, ComponentUpgradeService $upgradeService): void
    {
        $this->resetTerminal();
        $this->clearScreen();

        $info = $upgradeService->getUpgradeInfo($installed);

        $this->renderShopHeader('CONFIRM UPGRADE');

        if (! $info['can_upgrade']) {
            $this->line($this->colorize('  '.($info['locked_reason'] ?? 'Cannot upgrade this component.'), 'dim'));
            $this->newLine();
            $this->waitForAnyKey();

            return;
        }

        $this->line($this->colorize('  Component: ', 'label').$this->colorize($info['component_name'], 'highlight'));
        $this->line($this->colorize('  Rarity: ', 'label').$this->colorize($info['rarity_label'], 'highlight'));
        $this->line($this->colorize('  Current Level: ', 'label').$info['current_level'].' / '.$info['max_level']);
        $this->newLine();

        // Show effect changes
        $this->line($this->colorize('  Effect Changes:', 'header'));
        foreach ($info['current_effects'] as $stat => $currentValue) {
            if (is_numeric($currentValue) && isset($info['next_effects'][$stat])) {
                $this->line($this->colorize('    '.ucfirst(str_replace('_', ' ', $stat)).': ', 'label').
                           $this->colorize(round($currentValue, 2), 'highlight').
                           $this->colorize(' → ', 'dim').
                           $this->colorize(round($info['next_effects'][$stat], 2), 'trade'));
            }
        }

        $this->newLine();
        $this->line($this->colorize('  Cost: ', 'label').
                   $this->colorize(number_format($info['upgrade_cost']).' credits', 'trade'));
        $this->line($this->colorize('  Your Credits: ', 'label').
                   $this->colorize(number_format($player->credits, 2), 'highlight'));

        if ($player->credits < $info['upgrade_cost']) {
            $this->showInsufficientCredits($info['upgrade_cost'], $player->credits);

            return;
        }

        $this->newLine();
        $this->renderSeparator();
        $this->newLine();
        $this->line($this->colorize('  Confirm upgrade? ', 'header').
                   $this->colorize('[y]', 'label').' Yes  '.
                   $this->colorize('[n]', 'label').' No');
        $this->newLine();

        $confirm = $this->readChar();

        if (strtolower($confirm) === 'y') {
            $result = $upgradeService->upgradeComponent($player, $installed);

            $this->clearScreen();
            $this->renderBorder();

            if ($result['success']) {
                $this->line($this->colorize('  UPGRADE SUCCESSFUL!', 'trade'));
                $this->renderBorder();
                $this->newLine();
                $this->line($this->colorize('  '.$result['message'], 'highlight'));
                $this->line($this->colorize('  Credits Spent: ', 'label').number_format($result['cost']));
                $this->line($this->colorize('  Credits Remaining: ', 'label').
                           $this->colorize(number_format($player->credits, 2), 'trade'));

                $player->refresh();
            } else {
                $this->line($this->colorize('  UPGRADE FAILED', 'dim'));
                $this->renderBorder();
                $this->newLine();
                $this->line($this->colorize('  '.$result['message'], 'dim'));
            }
        } else {
            $this->clearScreen();
            $this->line($this->colorize('  Upgrade cancelled.', 'dim'));
        }

        $this->newLine();
        $this->waitForAnyKey();
    }
}

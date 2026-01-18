<?php

namespace App\Console\Renderers;

use App\Console\Traits\ConsoleColorizer;
use App\Models\TradingHub;
use Illuminate\Console\Command;

class TradingHubRenderer
{
    use ConsoleColorizer;

    public function __construct(
        private Command $command,
        private int $termWidth
    ) {}

    private bool $showAllMinerals = false;

    public function toggleShowAll(): void
    {
        $this->showAllMinerals = ! $this->showAllMinerals;
    }

    public function render(TradingHub $hub): void
    {
        $this->clearScreen();
        $this->renderHeader($hub);
        $this->renderHubInfo($hub);
        $this->renderMineralPrices($hub);

        $this->command->newLine(2);
        $this->command->line($this->colorize('  Controls: ', 'header').
                           $this->colorize('[a]', 'label').' - Toggle show all minerals | '.
                           $this->colorize('[ESC/q]', 'label').' - Return');
    }

    private function renderHeader(TradingHub $hub): void
    {
        $this->command->line($this->colorize('╔'.str_repeat('═', $this->termWidth - 2).'╗', 'border'));
        $this->command->line(
            $this->colorize('║ ', 'border').
            $this->colorize('TRADING HUB: ', 'header').
            $this->colorize(strtoupper($hub->name), 'trade').
            str_repeat(' ', max(0, $this->termWidth - strlen($hub->name) - 18)).
            $this->colorize('║', 'border')
        );
        $this->command->line($this->colorize('╚'.str_repeat('═', $this->termWidth - 2).'╝', 'border'));
        $this->command->newLine();
    }

    private function renderHubInfo(TradingHub $hub): void
    {
        $this->command->line($this->colorize('  HUB INFORMATION:', 'header'));
        $this->command->line('    '.$this->colorize('Type: ', 'label').
                           ucfirst($hub->type).' ('.$hub->gate_count.' gates)');
        $this->command->line('    '.$this->colorize('Tax Rate: ', 'label').
                           $hub->tax_rate.'%');
        $this->command->line('    '.$this->colorize('Salvage Yard: ', 'label').
                           ($hub->has_salvage_yard ? 'Yes' : 'No'));

        if (! empty($hub->services)) {
            $services = implode(', ', array_map('ucwords', str_replace('_', ' ', $hub->services)));
            $this->command->line('    '.$this->colorize('Services: ', 'label').$services);
        }

        $this->command->newLine();
    }

    private function renderMineralPrices(TradingHub $hub): void
    {
        $inventories = $hub->inventories()
            ->with('mineral')
            ->orderBy('sell_price')
            ->get();

        if ($inventories->isEmpty()) {
            $this->command->line($this->colorize('  No minerals in stock', 'dim'));

            return;
        }

        $this->command->line($this->colorize('  MINERAL PRICES:', 'header'));
        $this->command->newLine();

        // Table header
        $this->command->line(
            '    '.
            $this->padRight($this->colorize('Mineral', 'label'), 25).
            $this->padRight($this->colorize('Rarity', 'label'), 12).
            $this->padRight($this->colorize('Buy @', 'label'), 16).
            $this->padRight($this->colorize('Sell @', 'label'), 16).
            $this->padRight($this->colorize('Stock', 'label'), 18).
            $this->colorize('Supply', 'label')
        );
        $this->command->line('    '.str_repeat('─', $this->termWidth - 8));

        // Show minerals based on toggle
        $displayLimit = $this->showAllMinerals ? $inventories->count() : 15;
        $displayInventories = $inventories->take($displayLimit);

        foreach ($displayInventories as $inv) {
            $mineral = $inv->mineral;
            // Use base_value for comparison (rarity is already in base_value)
            $marketValue = $mineral->base_value;
            $priceRatio = $inv->sell_price / $marketValue;

            $priceColor = match (true) {
                $priceRatio < 0.8 => 'price_low',
                $priceRatio > 1.2 => 'price_high',
                default => 'reset',
            };

            $supplyColor = match (true) {
                $inv->supply_level >= 75 => 'supply_high',
                $inv->supply_level <= 25 => 'supply_low',
                default => 'reset',
            };

            $rarityColor = match ($mineral->rarity->value) {
                'abundant', 'common' => 'dim',
                'uncommon', 'rare' => 'reset',
                'very_rare', 'epic' => 'highlight',
                'legendary', 'mythic' => 'trade',
            };

            $mineralName = substr($mineral->name, 0, 25);
            $rarity = ucfirst($mineral->rarity->value);
            $buyPrice = '$'.number_format($inv->buy_price, 2);
            $sellPrice = '$'.number_format($inv->sell_price, 2);
            $quantity = number_format($inv->quantity);
            $supply = (string) $inv->supply_level;

            $this->command->line(
                '    '.
                $this->padRight($this->colorize($mineralName, $rarityColor), 25).
                $this->padRight($this->colorize($rarity, $rarityColor), 12).
                $this->padRight($this->colorize($buyPrice, $priceColor), 16).
                $this->padRight($this->colorize($sellPrice, $priceColor), 16).
                $this->padRight($quantity, 18).
                $this->colorize($supply, $supplyColor)
            );
        }

        if (! $this->showAllMinerals && $inventories->count() > 15) {
            $this->command->newLine();
            $this->command->line('    '.$this->colorize('... and '.
                               ($inventories->count() - 15).' more minerals (press [a] to show all)', 'dim'));
        }

        if ($this->showAllMinerals) {
            $this->command->newLine();
            $this->command->line('    '.$this->colorize('Showing all '.$inventories->count().' minerals', 'dim'));
        }

        $this->renderLegend();
        $this->renderBestDeals($inventories);
    }

    private function renderLegend(): void
    {
        $this->command->newLine();
        $this->command->line('    '.$this->colorize('Legend:', 'header').' '.
                           $this->colorize('Buy @ ', 'label').'= Hub buys from you | '.
                           $this->colorize('Sell @ ', 'label').'= Hub sells to you | '.
                           $this->colorize('Supply ', 'label').'= Availability (0-100)');

        $this->command->newLine();
        $this->command->line('    '.$this->colorize('Green prices', 'price_low').' = Good deal | '.
                           $this->colorize('Red prices', 'price_high').' = Expensive | '.
                           $this->colorize('Green supply', 'supply_high').' = Abundant | '.
                           $this->colorize('Orange supply', 'supply_low').' = Scarce');
    }

    private function renderBestDeals($inventories): void
    {
        $deals = $inventories->map(function ($inv) {
            $mineral = $inv->mineral;
            // Use base_value for comparison (rarity is already in base_value)
            $marketValue = $mineral->base_value;
            $discount = (($marketValue - $inv->sell_price) / $marketValue) * 100;

            return [
                'mineral' => $mineral,
                'inventory' => $inv,
                'discount' => $discount,
            ];
        })->filter(fn ($deal) => $deal['discount'] > 5)
            ->sortByDesc('discount')
            ->take(3);

        if ($deals->isEmpty()) {
            return;
        }

        $this->command->newLine();
        $this->command->line($this->colorize('  💰 BEST DEALS:', 'header'));

        foreach ($deals as $deal) {
            $this->command->line(sprintf(
                '    • %s at $%s (%s%% below market)',
                $this->colorize($deal['mineral']->name, 'price_low'),
                number_format($deal['inventory']->sell_price, 2),
                number_format($deal['discount'], 1)
            ));
        }
    }

    /**
     * Pad a string to a specific width (accounting for ANSI codes)
     */
    private function padRight(string $text, int $width): string
    {
        $visualLen = $this->visualLength($text);
        $padding = max(0, $width - $visualLen);

        return $text.str_repeat(' ', $padding);
    }
}

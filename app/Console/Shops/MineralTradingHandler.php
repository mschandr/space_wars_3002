<?php

namespace App\Console\Shops;

use App\Console\Traits\ConsoleBoxRenderer;
use App\Console\Traits\ConsoleColorizer;
use App\Console\Traits\TerminalInputHandler;
use App\Models\Player;
use App\Models\PlayerCargo;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use Illuminate\Console\Command;

class MineralTradingHandler
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
     * Display the mineral trading interface
     */
    public function show(Player $player, TradingHub $tradingHub): void
    {
        system('stty sane');

        $running = true;
        while ($running) {
            // Reload data
            $player->refresh();
            $player->load('activeShip.cargo.mineral');
            $ship = $player->activeShip;

            // Load hub inventory
            $hubInventories = $tradingHub->inventories()->with('mineral')->where('quantity', '>', 0)->get();
            $playerCargo = $ship->cargo;

            $this->clearScreen();

            // Header
            $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
            $this->line($this->colorize('  MINERAL TRADING', 'header'));
            $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
            $this->newLine();

            $this->line('  Trading Hub: ' . $this->colorize($tradingHub->name, 'trade'));
            $this->line('  Credits: ' . $this->colorize(number_format($player->credits, 2), 'highlight'));

            $cargoUsed = $ship->current_cargo;
            $cargoTotal = $ship->cargo_hold;
            $this->line('  Cargo Space: ' . $this->colorize("{$cargoUsed}/{$cargoTotal} units", 'highlight'));
            $this->newLine();

            // Market Events (Drug Wars style!)
            $eventService = app(\App\Services\MarketEventService::class);
            $activeEvents = $eventService->getActiveEventsForHub($tradingHub);
            if ($activeEvents->isNotEmpty()) {
                $this->line($this->colorize('  📢 ACTIVE MARKET EVENTS:', 'highlight'));
                foreach ($activeEvents->take(3) as $event) { // Show max 3 events
                    $mineralName = $event->mineral ? $event->mineral->name : 'All Minerals';
                    $multiplierPercent = (int)(($event->price_multiplier - 1) * 100);
                    $indicator = $event->event_type->isPriceIncrease() ? '📈' : '📉';
                    $color = $event->event_type->isPriceIncrease() ? 'pirate' : 'trade';

                    $this->line('     ' . $indicator . ' ' .
                               $this->colorize($event->description, $color) .
                               ' ' . $this->colorize('[' . $event->getTimeRemainingString() . ']', 'dim'));
                }
                $this->newLine();
            }

            // BUY section
            $this->line($this->colorize('  BUY FROM HUB (Hub sells to you):', 'header'));
            $this->newLine();

            if ($hubInventories->isEmpty()) {
                $this->line($this->colorize('  No minerals available for purchase', 'dim'));
            } else {
                // Header row
                $headerLine = sprintf('  %4s  %-30s %15s %12s', '', 'Mineral', 'Price', 'Stock');
                $this->line($this->colorize($headerLine, 'dim'));
                $this->line($this->colorize('  ' . str_repeat('─', 65), 'dim'));

                $displayInventories = $hubInventories->take(9);
                foreach ($displayInventories as $index => $inventory) {
                    $number = $index + 1;
                    $mineral = $inventory->mineral;

                    // Format with proper column alignment
                    $dataLine = sprintf(
                        '  %4s  %-30s %15s %12s',
                        "[b{$number}]",
                        $mineral->name,
                        number_format($inventory->sell_price, 2),
                        number_format($inventory->quantity, 0)
                    );
                    $this->line($dataLine);
                }
            }
            $this->newLine();

            // SELL section
            $this->line($this->colorize('  SELL TO HUB (Hub buys from you):', 'header'));
            $this->newLine();

            if ($playerCargo->isEmpty()) {
                $this->line($this->colorize('  No minerals in cargo', 'dim'));
            } else {
                // Header row
                $headerLine = sprintf('  %4s  %-30s %15s %12s', '', 'Mineral', 'Price', 'Quantity');
                $this->line($this->colorize($headerLine, 'dim'));
                $this->line($this->colorize('  ' . str_repeat('─', 65), 'dim'));

                $displayCargo = $playerCargo->take(9);
                foreach ($displayCargo as $index => $cargo) {
                    $number = $index + 1;
                    $mineral = $cargo->mineral;

                    // Find hub's buy price for this mineral
                    $hubInventory = $hubInventories->where('mineral_id', $mineral->id)->first();
                    $buyPrice = $hubInventory ? $hubInventory->buy_price : $mineral->getMarketValue() * 0.85;

                    // Format with proper column alignment
                    $dataLine = sprintf(
                        '  %4s  %-30s %15s %12s',
                        "[s{$number}]",
                        $mineral->name,
                        number_format($buyPrice, 2),
                        number_format($cargo->quantity, 0)
                    );
                    $this->line($dataLine);
                }
            }

            $this->newLine();
            $this->line($this->colorize(str_repeat('─', $this->termWidth), 'border'));
            $this->line('  ' . $this->colorize('[b1-b9]', 'label') . ' Buy mineral  |  ' . $this->colorize('[s1-s9]', 'label') . ' Sell mineral  |  ' . $this->colorize('[q]', 'label') . ' Exit');
            $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));

            // Get input
            system('stty -icanon -echo');
            $char = fgetc(STDIN);

            if ($char === 'q' || $char === "\033") {
                $running = false;
            } elseif ($char === 'b') {
                // Buy mode - read next char for number
                $num = fgetc(STDIN);
                if (is_numeric($num) && $num >= '1' && $num <= '9') {
                    $selectedIndex = (int)$num - 1;
                    if ($selectedIndex < $hubInventories->count()) {
                        $selectedInventory = $hubInventories->values()->get($selectedIndex);
                        $this->buyMineralFlow($player, $selectedInventory, $tradingHub);
                    }
                }
            } elseif ($char === 's') {
                // Sell mode - read next char for number
                $num = fgetc(STDIN);
                if (is_numeric($num) && $num >= '1' && $num <= '9') {
                    $selectedIndex = (int)$num - 1;
                    if ($selectedIndex < $playerCargo->count()) {
                        $selectedCargo = $playerCargo->values()->get($selectedIndex);
                        $this->sellMineralFlow($player, $selectedCargo, $tradingHub);
                    }
                }
            }
        }
    }

    /**
     * Handle the buy mineral flow
     */
    private function buyMineralFlow(Player $player, TradingHubInventory $inventory, TradingHub $hub): void
    {
        $mineral = $inventory->mineral;
        $ship = $player->activeShip;

        $this->clearScreen();
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->line($this->colorize('  BUY MINERAL - ' . strtoupper($mineral->name), 'header'));
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->newLine();

        $this->line('  Available Stock: ' . $this->colorize(number_format($inventory->quantity) . ' units', 'highlight'));
        $this->line('  Price per Unit: ' . $this->colorize(number_format($inventory->sell_price, 2) . ' credits', 'highlight'));
        $this->line('  Your Credits: ' . $this->colorize(number_format($player->credits, 2), 'dim'));
        $this->line('  Cargo Space: ' . $this->colorize("{$ship->current_cargo}/{$ship->cargo_hold} units", 'dim'));
        $this->newLine();

        $this->line($this->colorize('  How many units to buy? (0 to cancel): ', 'label'));
        system('stty sane');
        $quantity = (int)trim(fgets(STDIN));

        if ($quantity <= 0) {
            return;
        }

        // Validations
        if (!$inventory->hasStock($quantity)) {
            $this->clearScreen();
            $this->error('  Insufficient stock available');
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            system('stty -icanon -echo');
            fgetc(STDIN);
            return;
        }

        $totalCost = $inventory->sell_price * $quantity;
        if ($player->credits < $totalCost) {
            $this->clearScreen();
            $this->error('  Insufficient credits');
            $this->newLine();
            $this->line('  Required: ' . number_format($totalCost, 2) . ' credits');
            $this->line('  You have: ' . number_format($player->credits, 2) . ' credits');
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            system('stty -icanon -echo');
            fgetc(STDIN);
            return;
        }

        $availableSpace = $ship->cargo_hold - $ship->current_cargo;
        if ($availableSpace < $quantity) {
            $this->clearScreen();
            $this->error('  Insufficient cargo space');
            $this->newLine();
            $this->line('  Required: ' . $quantity . ' units');
            $this->line('  Available: ' . $availableSpace . ' units');
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            system('stty -icanon -echo');
            fgetc(STDIN);
            return;
        }

        // Confirmation
        $this->clearScreen();
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->line($this->colorize('  CONFIRM PURCHASE', 'header'));
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->newLine();
        $this->line('  Mineral: ' . $this->colorize($mineral->name, 'trade'));
        $this->line('  Quantity: ' . $this->colorize(number_format($quantity) . ' units', 'highlight'));
        $this->line('  Price per Unit: ' . $this->colorize(number_format($inventory->sell_price, 2) . ' credits', 'dim'));
        $this->line('  Total Cost: ' . $this->colorize(number_format($totalCost, 2) . ' credits', 'trade'));
        $this->newLine();
        $this->line('  Credits After: ' . $this->colorize(number_format($player->credits - $totalCost, 2), 'dim'));
        $this->line('  Cargo After: ' . $this->colorize(($ship->current_cargo + $quantity) . '/' . $ship->cargo_hold, 'dim'));
        $this->newLine();
        $this->line($this->colorize('  Confirm purchase? [y/n]: ', 'label'));

        system('stty -icanon -echo');
        $confirm = fgetc(STDIN);

        if ($confirm !== 'y' && $confirm !== 'Y') {
            return;
        }

        // Execute transaction
        $player->deductCredits($totalCost);
        $inventory->removeStock($quantity);

        // Add to player cargo
        $playerCargo = PlayerCargo::firstOrNew([
            'player_ship_id' => $ship->id,
            'mineral_id' => $mineral->id,
        ]);
        $playerCargo->quantity = ($playerCargo->quantity ?? 0) + $quantity;
        $playerCargo->save();

        // Update ship cargo
        $ship->current_cargo += $quantity;
        $ship->save();

        // Award XP for trading (small amount for purchases)
        $xpEarned = (int)max(5, $quantity / 10); // 1 XP per 10 units, min 5
        $oldLevel = $player->level;
        $player->addExperience($xpEarned);
        $newLevel = $player->level;

        // Success message
        $this->clearScreen();
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->line($this->colorize('  ✓ PURCHASE SUCCESSFUL', 'trade'));
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->newLine();
        $this->line('  Purchased: ' . number_format($quantity) . ' units of ' . $mineral->name);
        $this->line('  Cost: ' . number_format($totalCost, 2) . ' credits');
        $this->line('  XP Earned: ' . $this->colorize('+' . $xpEarned . ' XP', 'highlight'));

        if ($newLevel > $oldLevel) {
            $this->line('  ' . $this->colorize('🎉 LEVEL UP! You are now level ' . $newLevel . '!', 'trade'));
        }

        $this->newLine();
        $this->line($this->colorize('  Press any key to continue...', 'dim'));
        fgetc(STDIN);
    }

    /**
     * Handle the sell mineral flow
     */
    private function sellMineralFlow(Player $player, PlayerCargo $cargo, TradingHub $hub): void
    {
        $mineral = $cargo->mineral;
        $ship = $player->activeShip;

        // Get or create hub inventory for this mineral
        $hubInventory = TradingHubInventory::firstOrNew([
            'trading_hub_id' => $hub->id,
            'mineral_id' => $mineral->id,
        ]);

        // If new inventory, set initial pricing
        if (!$hubInventory->exists) {
            $hubInventory->quantity = 0;
            $hubInventory->demand_level = 50;
            $hubInventory->supply_level = 50;
            $hubInventory->save();
            $hubInventory->updatePricing();
            $hubInventory->refresh();
        }

        $this->clearScreen();
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->line($this->colorize('  SELL MINERAL - ' . strtoupper($mineral->name), 'header'));
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->newLine();

        $this->line('  Your Quantity: ' . $this->colorize(number_format($cargo->quantity) . ' units', 'highlight'));
        $this->line('  Hub Buys At: ' . $this->colorize(number_format($hubInventory->buy_price, 2) . ' credits/unit', 'highlight'));
        $this->newLine();

        $this->line($this->colorize('  How many units to sell? (0 to cancel): ', 'label'));
        system('stty sane');
        $quantity = (int)trim(fgets(STDIN));

        if ($quantity <= 0) {
            return;
        }

        // Validations
        if ($cargo->quantity < $quantity) {
            $this->clearScreen();
            $this->error('  You don\'t have that many units');
            $this->newLine();
            $this->line('  You have: ' . number_format($cargo->quantity) . ' units');
            $this->line('  Trying to sell: ' . number_format($quantity) . ' units');
            $this->newLine();
            $this->line($this->colorize('  Press any key to continue...', 'dim'));
            system('stty -icanon -echo');
            fgetc(STDIN);
            return;
        }

        $totalRevenue = $hubInventory->buy_price * $quantity;

        // Confirmation
        $this->clearScreen();
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->line($this->colorize('  CONFIRM SALE', 'header'));
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->newLine();
        $this->line('  Mineral: ' . $this->colorize($mineral->name, 'trade'));
        $this->line('  Quantity: ' . $this->colorize(number_format($quantity) . ' units', 'highlight'));
        $this->line('  Price per Unit: ' . $this->colorize(number_format($hubInventory->buy_price, 2) . ' credits', 'dim'));
        $this->line('  Total Revenue: ' . $this->colorize(number_format($totalRevenue, 2) . ' credits', 'trade'));
        $this->newLine();
        $this->line('  Credits After: ' . $this->colorize(number_format($player->credits + $totalRevenue, 2), 'dim'));
        $this->line('  Cargo After: ' . $this->colorize(($ship->current_cargo - $quantity) . '/' . $ship->cargo_hold, 'dim'));
        $this->newLine();
        $this->line($this->colorize('  Confirm sale? [y/n]: ', 'label'));

        system('stty -icanon -echo');
        $confirm = fgetc(STDIN);

        if ($confirm !== 'y' && $confirm !== 'Y') {
            return;
        }

        // Execute transaction
        $player->addCredits($totalRevenue);
        $hubInventory->addStock($quantity);

        // Remove from player cargo
        $cargo->quantity -= $quantity;
        if ($cargo->quantity <= 0) {
            $cargo->delete();
        } else {
            $cargo->save();
        }

        // Update ship cargo
        $ship->current_cargo -= $quantity;
        $ship->save();

        // Award XP for trading (based on revenue/profit)
        $xpEarned = (int)max(10, $totalRevenue / 100); // 1 XP per 100 credits revenue, min 10
        $oldLevel = $player->level;
        $player->addExperience($xpEarned);
        $newLevel = $player->level;

        // Success message
        $this->clearScreen();
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->line($this->colorize('  ✓ SALE SUCCESSFUL', 'trade'));
        $this->line($this->colorize(str_repeat('═', $this->termWidth), 'border'));
        $this->newLine();
        $this->line('  Sold: ' . number_format($quantity) . ' units of ' . $mineral->name);
        $this->line('  Revenue: ' . number_format($totalRevenue, 2) . ' credits');
        $this->line('  XP Earned: ' . $this->colorize('+' . $xpEarned . ' XP', 'highlight'));

        if ($newLevel > $oldLevel) {
            $this->line('  ' . $this->colorize('🎉 LEVEL UP! You are now level ' . $newLevel . '!', 'trade'));
        }

        $this->newLine();
        $this->line($this->colorize('  Press any key to continue...', 'dim'));
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

<?php

namespace App\Console\Shops;

use App\DataObjects\PricingContext;
use App\Models\Player;
use App\Models\PlayerCargo;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use App\Services\Trading\HubInventoryMutationService;
use Illuminate\Support\Facades\DB;

class MineralTradingHandler extends BaseShopHandler
{
    /**
     * Display the mineral trading interface
     */
    public function show(Player $player, TradingHub $tradingHub): void
    {
        $this->resetTerminal();
        $running = true;

        while ($running) {
            $player->refresh();
            $player->load('activeShip.cargo.mineral');
            $ship = $player->activeShip;

            app(\App\Services\TradingService::class)->ensureInventoryPopulated($tradingHub);
            $hubInventories = $tradingHub->inventories()->with('mineral')->where('quantity', '>', 0)->get();
            $playerCargo = $ship->cargo;

            $this->clearScreen();
            $this->displayHeader($player, $ship, $tradingHub);
            $this->displayMarketEvents($tradingHub);
            $this->displayBuySection($hubInventories);
            $this->displaySellSection($playerCargo, $hubInventories);
            $this->displayFooter();

            $char = $this->readChar();
            $running = $this->handleInput($char, $player, $hubInventories, $playerCargo, $tradingHub);
        }
    }

    private function displayHeader(Player $player, $ship, TradingHub $tradingHub): void
    {
        $this->renderShopHeader('MINERAL TRADING');
        $this->line('  Trading Hub: '.$this->colorize($tradingHub->name, 'trade'));
        $this->line('  Credits: '.$this->colorize(number_format($player->credits, 2), 'highlight'));

        $cargoUsed = $ship->current_cargo;
        $cargoTotal = $ship->cargo_hold;
        $this->line('  Cargo Space: '.$this->colorize("{$cargoUsed}/{$cargoTotal} units", 'highlight'));
        $this->newLine();
    }

    private function displayMarketEvents(TradingHub $tradingHub): void
    {
        $eventService = app(\App\Services\MarketEventService::class);
        $activeEvents = $eventService->getActiveEventsForHub($tradingHub);

        if ($activeEvents->isEmpty()) {
            return;
        }

        $this->line($this->colorize('  📢 ACTIVE MARKET EVENTS:', 'highlight'));
        foreach ($activeEvents->take(3) as $event) {
            $indicator = $event->event_type->isPriceIncrease() ? '📈' : '📉';
            $color = $event->event_type->isPriceIncrease() ? 'pirate' : 'trade';

            $this->line('     '.$indicator.' '.
                       $this->colorize($event->description, $color).
                       ' '.$this->colorize('['.$event->getTimeRemainingString().']', 'dim'));
        }
        $this->newLine();
    }

    private function displayBuySection($hubInventories): void
    {
        $this->line($this->colorize('  BUY FROM HUB (Hub sells to you):', 'header'));
        $this->newLine();

        if ($hubInventories->isEmpty()) {
            $this->line($this->colorize('  No minerals available for purchase', 'dim'));
            return;
        }

        $this->displayTableHeader('Mineral', 'Price', 'Stock');
        $displayInventories = $hubInventories->take(9);

        foreach ($displayInventories as $index => $inventory) {
            $number = $index + 1;
            $mineral = $inventory->mineral;

            $dataLine = sprintf(
                '  %4s  %-30s %15s %12s',
                "[b{$number}]",
                $mineral->name,
                number_format($inventory->sell_price, 2),
                number_format($inventory->quantity, 0)
            );
            $this->line($dataLine);
        }
        $this->newLine();
    }

    private function displaySellSection($playerCargo, $hubInventories): void
    {
        $this->line($this->colorize('  SELL TO HUB (Hub buys from you):', 'header'));
        $this->newLine();

        if ($playerCargo->isEmpty()) {
            $this->line($this->colorize('  No minerals in cargo', 'dim'));
            return;
        }

        $this->displayTableHeader('Mineral', 'Price', 'Quantity');
        $displayCargo = $playerCargo->take(9);

        foreach ($displayCargo as $index => $cargo) {
            $number = $index + 1;
            $mineral = $cargo->mineral;
            $hubInventory = $hubInventories->where('mineral_id', $mineral->id)->first();
            $buyPrice = $hubInventory ? $hubInventory->buy_price : $mineral->getMarketValue() * 0.85;

            $dataLine = sprintf(
                '  %4s  %-30s %15s %12s',
                "[s{$number}]",
                $mineral->name,
                number_format($buyPrice, 2),
                number_format($cargo->quantity, 0)
            );
            $this->line($dataLine);
        }
        $this->newLine();
    }

    private function displayTableHeader(string $col1, string $col2, string $col3): void
    {
        $headerLine = sprintf('  %4s  %-30s %15s %12s', '', $col1, $col2, $col3);
        $this->line($this->colorize($headerLine, 'dim'));
        $this->line($this->colorize('  '.str_repeat('─', 65), 'dim'));
    }

    private function displayFooter(): void
    {
        $this->renderSeparator();
        $this->line('  '.$this->colorize('[b1-b9]', 'label').' Buy mineral  |  '.$this->colorize('[s1-s9]', 'label').' Sell mineral  |  '.$this->colorize('[q]', 'label').' Exit');
        $this->renderBorder();
    }

    private function handleInput(string $char, Player $player, $hubInventories, $playerCargo, TradingHub $tradingHub): bool
    {
        if ($this->isQuitKey($char)) {
            return false;
        }

        if ($char === 'b') {
            $this->handleBuyInput($player, $hubInventories, $tradingHub);
        } elseif ($char === 's') {
            $this->handleSellInput($player, $playerCargo, $tradingHub);
        }

        return true;
    }

    private function handleBuyInput(Player $player, $hubInventories, TradingHub $tradingHub): void
    {
        $num = fgetc(STDIN);
        if (! is_numeric($num) || $num < '1' || $num > '9') {
            return;
        }

        $selectedIndex = (int) $num - 1;
        if ($selectedIndex < $hubInventories->count()) {
            $selectedInventory = $hubInventories->values()->get($selectedIndex);
            $this->buyMineralFlow($player, $selectedInventory, $tradingHub);
        }
    }

    private function handleSellInput(Player $player, $playerCargo, TradingHub $tradingHub): void
    {
        $num = fgetc(STDIN);
        if (! is_numeric($num) || $num < '1' || $num > '9') {
            return;
        }

        $selectedIndex = (int) $num - 1;
        if ($selectedIndex < $playerCargo->count()) {
            $selectedCargo = $playerCargo->values()->get($selectedIndex);
            $this->sellMineralFlow($player, $selectedCargo, $tradingHub);
        }
    }

    /**
     * Handle the buy mineral flow
     */
    private function buyMineralFlow(Player $player, TradingHubInventory $inventory, TradingHub $hub): void
    {
        $mineral = $inventory->mineral;
        $ship = $player->activeShip;

        $this->displayMineralInfo($mineral, $inventory, $player, $ship);
        $quantity = $this->getQuantityFromUser();

        if ($quantity <= 0) {
            return;
        }

        if (! $this->validateBuyPurchase($player, $inventory, $quantity, $ship)) {
            return;
        }

        $totalCost = $inventory->sell_price * $quantity;
        if (! $this->confirmPurchase($mineral, $inventory, $quantity, $totalCost, $ship, $player)) {
            return;
        }

        $result = $this->executeBuyTransaction($player, $inventory, $ship, $mineral, $quantity, $totalCost);
        $this->displayBuySuccess($mineral, $quantity, $totalCost, $result);
    }

    /**
     * Display mineral info and current state.
     */
    private function displayMineralInfo($mineral, TradingHubInventory $inventory, Player $player, $ship): void
    {
        $this->clearScreen();
        $this->renderShopHeader('BUY MINERAL - '.strtoupper($mineral->name));

        $this->line('  Available Stock: '.$this->colorize(number_format($inventory->quantity).' units', 'highlight'));
        $this->line('  Price per Unit: '.$this->colorize(number_format($inventory->sell_price, 2).' credits', 'highlight'));
        $this->line('  Your Credits: '.$this->colorize(number_format($player->credits, 2), 'dim'));
        $this->line('  Cargo Space: '.$this->colorize("{$ship->current_cargo}/{$ship->cargo_hold} units", 'dim'));
        $this->newLine();
    }

    /**
     * Get quantity input from user.
     */
    private function getQuantityFromUser(): int
    {
        $this->line($this->colorize('  How many units to buy? (0 to cancel): ', 'label'));
        $this->resetTerminal();
        return (int) trim(fgets(STDIN));
    }

    /**
     * Validate purchase prerequisites (stock, credits, cargo).
     */
    private function validateBuyPurchase(Player $player, TradingHubInventory $inventory, int $quantity, $ship): bool
    {
        if (! $inventory->hasStock($quantity)) {
            $this->clearScreen();
            $this->error('  Insufficient stock available');
            $this->newLine();
            $this->waitForAnyKey();
            return false;
        }

        $totalCost = $inventory->sell_price * $quantity;
        if ($player->credits < $totalCost) {
            $this->showInsufficientCredits($totalCost, $player->credits);
            return false;
        }

        $availableSpace = $ship->cargo_hold - $ship->current_cargo;
        if ($availableSpace < $quantity) {
            $this->clearScreen();
            $this->error('  Insufficient cargo space');
            $this->newLine();
            $this->line('  Required: '.$quantity.' units');
            $this->line('  Available: '.$availableSpace.' units');
            $this->newLine();
            $this->waitForAnyKey();
            return false;
        }

        return true;
    }

    /**
     * Display purchase confirmation screen.
     */
    private function confirmPurchase($mineral, TradingHubInventory $inventory, int $quantity, float $totalCost, $ship, Player $player): bool
    {
        $this->clearScreen();
        $this->renderShopHeader('CONFIRM PURCHASE');
        $this->line('  Mineral: '.$this->colorize($mineral->name, 'trade'));
        $this->line('  Quantity: '.$this->colorize(number_format($quantity).' units', 'highlight'));
        $this->line('  Price per Unit: '.$this->colorize(number_format($inventory->sell_price, 2).' credits', 'dim'));
        $this->line('  Total Cost: '.$this->colorize(number_format($totalCost, 2).' credits', 'trade'));
        $this->newLine();
        $this->line('  Credits After: '.$this->colorize(number_format($player->credits - $totalCost, 2), 'dim'));
        $this->line('  Cargo After: '.$this->colorize(($ship->current_cargo + $quantity).'/'.$ship->cargo_hold, 'dim'));
        $this->newLine();
        $this->line($this->colorize('  Confirm purchase? [y/n]: ', 'label'));

        $confirm = $this->readChar();
        return $confirm === 'y' || $confirm === 'Y';
    }

    /**
     * Execute purchase transaction with locking.
     */
    private function executeBuyTransaction(Player $player, TradingHubInventory $inventory, $ship, $mineral, int $quantity, float $totalCost): array
    {
        return DB::transaction(function () use ($player, $inventory, $ship, $mineral, $quantity, $totalCost) {
            // Re-fetch all mutable rows with locks inside transaction (prevent TOCTOU race)
            $player = Player::where('id', $player->id)->lockForUpdate()->firstOrFail();
            $ship = $player->activeShip()->lockForUpdate()->firstOrFail();
            $inventory = TradingHubInventory::where('id', $inventory->id)->lockForUpdate()->firstOrFail();

            // Re-validate all checks with locked rows
            if (! $inventory->hasStock($quantity)) {
                throw new \Exception('Insufficient stock available (race condition)');
            }

            if ($player->credits < $totalCost) {
                throw new \Exception('Insufficient credits (race condition)');
            }

            $availableSpace = $ship->cargo_hold - $ship->current_cargo;
            if ($availableSpace < $quantity) {
                throw new \Exception('Insufficient cargo space (race condition)');
            }

            // Deduct credits from locked player
            $player->deductCredits($totalCost);

            // Apply trade mutation with single save using locked inventory
            $ctx = PricingContext::forHub($inventory->tradingHub);
            $mutationService = app(HubInventoryMutationService::class);
            $mutationService->applyTrade($inventory, $quantity, 'buy', $ctx);

            // Add to player cargo
            $playerCargo = PlayerCargo::firstOrNew([
                'player_ship_id' => $ship->id,
                'mineral_id' => $mineral->id,
            ]);
            $playerCargo->quantity = ($playerCargo->quantity ?? 0) + $quantity;
            $playerCargo->save();

            // Update ship cargo with locked ship object
            $ship->current_cargo += $quantity;
            $ship->save();

            // Award XP for trading
            $xpEarned = (int) max(5, $quantity / 10);
            $oldLevel = $player->level;
            $player->addExperience($xpEarned);
            $newLevel = $player->level;

            return compact('xpEarned', 'oldLevel', 'newLevel');
        });
    }

    /**
     * Display purchase success message.
     */
    private function displayBuySuccess($mineral, int $quantity, float $totalCost, array $result): void
    {
        $this->clearScreen();
        $this->showSuccess('✓ PURCHASE SUCCESSFUL');
        $this->line('  Purchased: '.number_format($quantity).' units of '.$mineral->name);
        $this->line('  Cost: '.number_format($totalCost, 2).' credits');
        $this->line('  XP Earned: '.$this->colorize('+'.$result['xpEarned'].' XP', 'highlight'));

        if ($result['newLevel'] > $result['oldLevel']) {
            $this->line('  '.$this->colorize('🎉 LEVEL UP! You are now level '.$result['newLevel'].'!', 'trade'));
        }

        $this->newLine();
        $this->waitForAnyKey();
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
        if (! $hubInventory->exists) {
            $hubInventory->quantity = 0;
            $hubInventory->demand_level = 50;
            $hubInventory->supply_level = 50;
            $hubInventory->save();
            $hubInventory->updatePricing();
            $hubInventory->refresh();
        }

        $this->clearScreen();
        $this->renderShopHeader('SELL MINERAL - '.strtoupper($mineral->name));

        $this->line('  Your Quantity: '.$this->colorize(number_format($cargo->quantity).' units', 'highlight'));
        $this->line('  Hub Buys At: '.$this->colorize(number_format($hubInventory->buy_price, 2).' credits/unit', 'highlight'));
        $this->newLine();

        $this->line($this->colorize('  How many units to sell? (0 to cancel): ', 'label'));
        $this->resetTerminal();
        $quantity = (int) trim(fgets(STDIN));

        if ($quantity <= 0) {
            return;
        }

        // Validations
        if ($cargo->quantity < $quantity) {
            $this->clearScreen();
            $this->error('  You don\'t have that many units');
            $this->newLine();
            $this->line('  You have: '.number_format($cargo->quantity).' units');
            $this->line('  Trying to sell: '.number_format($quantity).' units');
            $this->newLine();
            $this->waitForAnyKey();

            return;
        }

        $totalRevenue = $hubInventory->buy_price * $quantity;

        // Confirmation
        $this->clearScreen();
        $this->renderShopHeader('CONFIRM SALE');
        $this->line('  Mineral: '.$this->colorize($mineral->name, 'trade'));
        $this->line('  Quantity: '.$this->colorize(number_format($quantity).' units', 'highlight'));
        $this->line('  Price per Unit: '.$this->colorize(number_format($hubInventory->buy_price, 2).' credits', 'dim'));
        $this->line('  Total Revenue: '.$this->colorize(number_format($totalRevenue, 2).' credits', 'trade'));
        $this->newLine();
        $this->line('  Credits After: '.$this->colorize(number_format($player->credits + $totalRevenue, 2), 'dim'));
        $this->line('  Cargo After: '.$this->colorize(($ship->current_cargo - $quantity).'/'.$ship->cargo_hold, 'dim'));
        $this->newLine();
        $this->line($this->colorize('  Confirm sale? [y/n]: ', 'label'));

        $confirm = $this->readChar();

        if ($confirm !== 'y' && $confirm !== 'Y') {
            return;
        }

        // Execute atomically
        $result = DB::transaction(function () use ($player, $hubInventory, $ship, $cargo, $mineral, $quantity, $totalRevenue) {
            // Re-fetch all mutable rows with locks inside transaction (prevent TOCTOU race)
            $player = Player::where('id', $player->id)->lockForUpdate()->firstOrFail();
            $ship = $player->activeShip()->lockForUpdate()->firstOrFail();
            $hubInventory = TradingHubInventory::where('id', $hubInventory->id)->lockForUpdate()->firstOrFail();
            $cargo = PlayerCargo::where('id', $cargo->id)->lockForUpdate()->firstOrFail();

            // Re-validate cargo quantity with locked row
            if ($cargo->quantity < $quantity) {
                throw new \Exception('You don\'t have that many units (race condition)');
            }

            // Add credits to locked player
            $player->addCredits($totalRevenue);

            // Apply trade mutation with single save using locked inventory
            $ctx = PricingContext::forHub($hubInventory->tradingHub);
            $mutationService = app(HubInventoryMutationService::class);
            $mutationService->applyTrade($hubInventory, $quantity, 'sell', $ctx);

            // Remove from locked player cargo
            $cargo->quantity -= $quantity;
            if ($cargo->quantity <= 0) {
                $cargo->delete();
            } else {
                $cargo->save();
            }

            // Update locked ship cargo
            $ship->current_cargo -= $quantity;
            $ship->save();

            // Award XP for trading (based on revenue/profit)
            $xpEarned = (int) max(10, $totalRevenue / 100); // 1 XP per 100 credits revenue, min 10
            $oldLevel = $player->level;
            $player->addExperience($xpEarned);
            $newLevel = $player->level;

            return compact('xpEarned', 'oldLevel', 'newLevel');
        });

        // Success message
        $this->clearScreen();
        $this->showSuccess('✓ SALE SUCCESSFUL');
        $this->line('  Sold: '.number_format($quantity).' units of '.$mineral->name);
        $this->line('  Revenue: '.number_format($totalRevenue, 2).' credits');
        $this->line('  XP Earned: '.$this->colorize('+'.$result['xpEarned'].' XP', 'highlight'));

        if ($result['newLevel'] > $result['oldLevel']) {
            $this->line('  '.$this->colorize('🎉 LEVEL UP! You are now level '.$result['newLevel'].'!', 'trade'));
        }

        $this->newLine();
        $this->waitForAnyKey();
    }
}

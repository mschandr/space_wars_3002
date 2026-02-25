<?php

namespace App\Console\Shops;

use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\TradingHub;
use App\Models\TradingHubShip;
use App\Services\MerchantCommentaryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShipShopHandler extends BaseShopHandler
{
    private MerchantCommentaryService $commentaryService;

    public function __construct()
    {
        $this->commentaryService = app(MerchantCommentaryService::class);
    }

    /**
     * Display the ship shop interface
     */
    public function show(Player $player, TradingHub $tradingHub): void
    {
        $this->resetTerminal();

        $running = true;
        while ($running) {
            // Reload player data
            $player->refresh();
            $player->load('activeShip.ship');
            $currentShip = $player->activeShip;

            // Get available ships at this trading hub
            $availableShips = $tradingHub->ships()
                ->with('ship')
                ->where('quantity', '>', 0)
                ->get();

            $this->clearScreen();

            // Header
            $this->renderShopHeader('SHIPYARD - NEW VESSEL SALES');

            // Location and player info
            $this->line($this->colorize('  Trading Hub: ', 'label').$this->colorize($tradingHub->name, 'trade'));

            if ($currentShip) {
                $this->line($this->colorize('  Current Ship: ', 'label').$currentShip->name.
                           ' ('.$this->colorize($currentShip->ship->name, 'dim').')');
                $this->line($this->colorize('  Trade-In Value: ', 'label').
                           $this->colorize(number_format($this->getTradeInValue($currentShip), 2), 'trade').' credits');
            } else {
                $this->line($this->colorize('  Current Ship: ', 'label').$this->colorize('NONE', 'pirate'));
                $this->line($this->colorize('  ⚠ You must purchase a ship to continue!', 'pirate'));
            }

            $this->line($this->colorize('  Credits Available: ', 'label').
                       $this->colorize(number_format($player->credits, 2), 'trade'));
            $this->newLine();

            if ($availableShips->isEmpty()) {
                $this->line($this->colorize('  This trading hub does not have a shipyard.', 'dim'));
                $this->newLine();
                $this->waitForAnyKey('Press any key to return...');

                return;
            }

            // Display available ships
            $this->line($this->colorize('  AVAILABLE SHIPS:', 'header'));
            $this->newLine();

            foreach ($availableShips as $index => $inventory) {
                $ship = $inventory->ship;
                $number = $index + 1;
                $tradeInValue = $currentShip ? $this->getTradeInValue($currentShip) : 0;
                $netCost = $inventory->current_price - $tradeInValue;
                $canAfford = $player->credits >= $netCost;

                $line = '  '.$this->colorize("[$number]", 'label').' '.
                        $this->colorize($ship->name, 'header').' ';

                // Ship stats in compact format
                $stats = sprintf(
                    'Hull:%d Weapons:%d Speed:%d Cargo:%d',
                    $ship->hull_strength,
                    $ship->weapon_slots * 15, // Approximate weapons value
                    $ship->speed,
                    $ship->cargo_capacity
                );

                $line .= $this->colorize($stats, 'dim');
                $this->line($line);

                // Second line with pricing
                $priceLine = '      '.
                            $this->colorize('Price: ', 'dim').
                            $this->colorize(number_format($inventory->current_price, 2), 'trade').' credits';

                if ($currentShip) {
                    $priceLine .= '  '.
                                $this->colorize('With Trade-In: ', 'dim').
                                $this->colorize(number_format($netCost, 2), $canAfford ? 'trade' : 'pirate').' credits';
                }

                $priceLine .= '  ';

                if (! $canAfford) {
                    $priceLine .= $this->colorize('[INSUFFICIENT FUNDS]', 'pirate');
                }

                $priceLine .= '  '.$this->colorize('Stock: '.$inventory->quantity, 'dim');

                $this->line($priceLine);

                // Special attributes
                if ($ship->attributes['is_carrier'] ?? false) {
                    $this->line('      '.$this->colorize('✈ CARRIER: Can dock '.
                               ($ship->attributes['fighter_capacity'] ?? 0).' fighters', 'highlight'));
                }

                if (! empty($ship->requirements)) {
                    $reqText = $this->formatRequirements($ship->requirements, $player);
                    $this->line('      '.$reqText);
                }

                // Shipyard owner commentary: hand-written overrides dynamic
                $pitch = ! empty($ship->sales_pitches)
                    ? $ship->getSalesPitch($currentShip !== null)
                    : $this->commentaryService->generateShipCommentary(
                        $ship,
                        (float) $inventory->current_price,
                        $player
                    );
                if ($pitch) {
                    $this->line('      '.$this->colorize('"'.$this->wrapText($pitch, $this->termWidth - 8).'"', 'dim'));
                }

                $this->newLine();
            }

            $this->renderSeparator();
            $this->newLine();
            $this->line($this->colorize('  Select ship [1-'.$availableShips->count().'] to purchase, or [q] to exit: ', 'label'));

            $input = trim(fgets(STDIN));

            if (strtolower($input) === 'q') {
                $running = false;

                continue;
            }

            if (is_numeric($input) && $input >= 1 && $input <= $availableShips->count()) {
                $selectedInventory = $availableShips[$input - 1];
                $this->purchaseShip($player, $currentShip, $selectedInventory, $tradingHub);
            }
        }
    }

    /**
     * Calculate trade-in value for current ship
     */
    private function getTradeInValue(PlayerShip $currentShip): float
    {
        // Base price of ship
        $baseValue = $currentShip->ship->base_price;

        // Depreciation based on hull damage
        $hullCondition = $currentShip->hull / $currentShip->max_hull;
        $depreciationFactor = 0.6 + ($hullCondition * 0.2); // 60-80% of base value

        return $baseValue * $depreciationFactor;
    }

    /**
     * Format requirements text
     */
    private function formatRequirements(array $requirements, Player $player): string
    {
        $parts = [];
        foreach ($requirements as $req => $value) {
            $playerValue = match ($req) {
                'level' => $player->level,
                default => 0,
            };

            $color = $playerValue >= $value ? 'trade' : 'pirate';
            $parts[] = $this->colorize(ucfirst($req).': '.$value, $color);
        }

        return $this->colorize('Requires: ', 'dim').implode(', ', $parts);
    }

    /**
     * Purchase a ship
     */
    private function purchaseShip(
        Player $player,
        ?PlayerShip $currentShip,
        TradingHubShip $inventory,
        TradingHub $tradingHub
    ): void {
        $ship = $inventory->ship;
        $tradeInValue = $currentShip ? $this->getTradeInValue($currentShip) : 0;
        $netCost = $inventory->current_price - $tradeInValue;

        // Check requirements
        if (! $ship->meetsRequirements(['level' => $player->level])) {
            $this->newLine();
            $this->line($this->colorize('  ❌ You do not meet the requirements for this ship.', 'pirate'));
            $this->waitForAnyKey();

            return;
        }

        // Check if player can afford
        if ($player->credits < $netCost) {
            $this->newLine();
            $this->line($this->colorize('  ❌ Insufficient funds. You need '.
                       number_format($netCost - $player->credits, 2).' more credits.', 'pirate'));
            $this->waitForAnyKey();

            return;
        }

        // Confirmation
        $this->newLine();
        $this->line($this->colorize('  ╔'.str_repeat('═', $this->termWidth - 4).'╗', 'border'));
        $this->line($this->colorize('  ║ PURCHASE CONFIRMATION', 'header').
                   str_repeat(' ', $this->termWidth - 28).
                   $this->colorize('║', 'border'));
        $this->line($this->colorize('  ╠'.str_repeat('═', $this->termWidth - 4).'╣', 'border'));

        if ($currentShip) {
            $this->line($this->colorize('  ║ ', 'border').'Trading In: '.$currentShip->name.
                       str_repeat(' ', $this->termWidth - 20 - strlen($currentShip->name)).
                       $this->colorize('║', 'border'));
            $this->line($this->colorize('  ║ ', 'border').'Trade-In Value: '.
                       $this->colorize(number_format($tradeInValue, 2), 'trade').' credits'.
                       str_repeat(' ', $this->termWidth - 35 - strlen(number_format($tradeInValue, 2))).
                       $this->colorize('║', 'border'));
            $this->line($this->colorize('  ║ ', 'border').
                       str_repeat(' ', $this->termWidth - 4).
                       $this->colorize('║', 'border'));
        } else {
            $this->line($this->colorize('  ║ ', 'border').'New Purchase (No Trade-In)'.
                       str_repeat(' ', $this->termWidth - 32).
                       $this->colorize('║', 'border'));
            $this->line($this->colorize('  ║ ', 'border').
                       str_repeat(' ', $this->termWidth - 4).
                       $this->colorize('║', 'border'));
        }

        $this->line($this->colorize('  ║ ', 'border').'Purchasing: '.$ship->name.
                   str_repeat(' ', $this->termWidth - 17 - strlen($ship->name)).
                   $this->colorize('║', 'border'));
        $this->line($this->colorize('  ║ ', 'border').'Ship Price: '.
                   $this->colorize(number_format($inventory->current_price, 2), 'trade').' credits'.
                   str_repeat(' ', $this->termWidth - 31 - strlen(number_format($inventory->current_price, 2))).
                   $this->colorize('║', 'border'));
        $this->line($this->colorize('  ║ ', 'border').
                   str_repeat(' ', $this->termWidth - 4).
                   $this->colorize('║', 'border'));
        $this->line($this->colorize('  ║ ', 'border').'NET COST: '.
                   $this->colorize(number_format($netCost, 2), 'highlight').' credits'.
                   str_repeat(' ', $this->termWidth - 29 - strlen(number_format($netCost, 2))).
                   $this->colorize('║', 'border'));
        $this->line($this->colorize('  ╚'.str_repeat('═', $this->termWidth - 4).'╝', 'border'));
        $this->newLine();
        $this->line($this->colorize('  Confirm purchase? [y/n]: ', 'label'));

        $confirm = strtolower(trim(fgets(STDIN)));

        if ($confirm !== 'y') {
            $this->newLine();
            $this->line($this->colorize('  Purchase cancelled.', 'dim'));
            $this->waitForAnyKey();

            return;
        }

        // Process purchase
        DB::transaction(function () use ($player, $currentShip, $ship, $inventory, $netCost) {
            // Delete the ship being traded in (if player has one)
            if ($currentShip) {
                $currentShip->cargo()->delete();
                $currentShip->delete();
            }

            // Deactivate all other ships (for future armada support)
            PlayerShip::where('player_id', $player->id)
                ->update(['is_active' => false]);

            // Create new ship (will be the active ship)
            $newShip = PlayerShip::create([
                'uuid' => Str::uuid(),
                'player_id' => $player->id,
                'ship_id' => $ship->id,
                'current_poi_id' => $player->current_poi_id,
                'name' => $ship->name, // Player can rename later
                'current_fuel' => $ship->attributes['max_fuel'] ?? 100,
                'max_fuel' => $ship->attributes['max_fuel'] ?? 100,
                'fuel_last_updated_at' => now(),
                'hull' => $ship->hull_strength,
                'max_hull' => $ship->hull_strength,
                'weapons' => $ship->attributes['starting_weapons'] ?? 10,
                'cargo_hold' => $ship->cargo_capacity,
                'sensors' => $ship->attributes['starting_sensors'] ?? 1,
                'warp_drive' => $ship->attributes['starting_warp_drive'] ?? 1,
                'current_cargo' => 0,
                'is_active' => true,
                'status' => 'operational',
            ]);

            $player->deductCredits($netCost);

            // Decrease ship inventory
            $inventory->decreaseStock();
        });

        $this->newLine();
        $this->line($this->colorize('  ✅ Ship purchased successfully!', 'trade'));
        $this->line($this->colorize('  Your new '.$ship->name.' is ready for departure.', 'highlight'));
        $this->newLine();
        $this->waitForAnyKey();
    }
}

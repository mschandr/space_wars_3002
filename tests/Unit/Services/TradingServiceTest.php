<?php

namespace Tests\Unit\Services;

use App\Models\Mineral;
use App\Models\Player;
use App\Models\PlayerCargo;
use App\Models\PlayerShip;
use App\Models\Ship;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use App\Services\TradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trading System Tests
 *
 * Critical mechanics:
 * - Buy: Deducts credits, adds to cargo, updates ship cargo, awards XP (1 XP per 10 units, min 5)
 * - Sell: Adds credits, removes from cargo, updates ship cargo, awards XP (1 XP per 100 credits, min 10)
 * - Validates: Stock availability, credit sufficiency, cargo capacity
 *
 * @see /TESTING_ROADMAP.md#4-trading-system-tests
 */
class TradingServiceTest extends TestCase
{
    use RefreshDatabase;

    private TradingService $tradingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tradingService = new TradingService;
    }

    /** @test */
    public function test_buying_minerals_deducts_credits()
    {
        $player = Player::factory()->create(['credits' => 10000.00]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 0,
        ]);

        $mineral = Mineral::factory()->create();
        $tradingHub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'quantity' => 1000,
            'sell_price' => 50.00,
        ]);

        $result = $this->tradingService->buyMineral($player, $playerShip, $inventory, 100);

        $this->assertTrue($result['success']);
        $this->assertEquals(5000.00, $result['total_cost']); // 100 * 50
        $this->assertEquals(5000.00, $player->fresh()->credits); // 10000 - 5000
    }

    /** @test */
    public function test_buying_minerals_adds_to_cargo()
    {
        $player = Player::factory()->create(['credits' => 10000.00]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 0,
        ]);

        $mineral = Mineral::factory()->create();
        $tradingHub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'quantity' => 1000,
            'sell_price' => 10.00,
        ]);

        $result = $this->tradingService->buyMineral($player, $playerShip, $inventory, 50);

        $this->assertTrue($result['success']);

        // Check PlayerCargo was created
        $cargo = PlayerCargo::where('player_ship_id', $playerShip->id)
            ->where('mineral_id', $mineral->id)
            ->first();

        $this->assertNotNull($cargo);
        $this->assertEquals(50, $cargo->quantity);
    }

    /** @test */
    public function test_buying_minerals_updates_ship_cargo()
    {
        $player = Player::factory()->create(['credits' => 10000.00]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 20,
        ]);

        $mineral = Mineral::factory()->create();
        $tradingHub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'quantity' => 1000,
            'sell_price' => 10.00,
        ]);

        $result = $this->tradingService->buyMineral($player, $playerShip, $inventory, 30);

        $this->assertTrue($result['success']);
        $this->assertEquals(50, $playerShip->fresh()->current_cargo); // 20 + 30
    }

    /** @test */
    public function test_cannot_buy_more_than_cargo_capacity()
    {
        $player = Player::factory()->create(['credits' => 100000.00]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 90,
        ]);

        $mineral = Mineral::factory()->create();
        $tradingHub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'quantity' => 1000,
            'sell_price' => 10.00,
        ]);

        // Try to buy 20 units when only 10 space available
        $result = $this->tradingService->buyMineral($player, $playerShip, $inventory, 20);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('cargo', strtolower($result['message']));
    }

    /** @test */
    public function test_cannot_buy_with_insufficient_credits()
    {
        $player = Player::factory()->create(['credits' => 100.00]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 1000,
            'current_cargo' => 0,
        ]);

        $mineral = Mineral::factory()->create();
        $tradingHub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'quantity' => 1000,
            'sell_price' => 50.00,
        ]);

        // Try to buy 100 units at 50 credits each (need 5000, have 100)
        $result = $this->tradingService->buyMineral($player, $playerShip, $inventory, 100);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('credit', strtolower($result['message']));
    }

    /** @test */
    public function test_cannot_buy_more_than_available_stock()
    {
        $player = Player::factory()->create(['credits' => 100000.00]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 1000,
            'current_cargo' => 0,
        ]);

        $mineral = Mineral::factory()->create();
        $tradingHub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'quantity' => 50, // Only 50 in stock
            'sell_price' => 10.00,
        ]);

        // Try to buy 100 when only 50 available
        $result = $this->tradingService->buyMineral($player, $playerShip, $inventory, 100);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('stock', strtolower($result['message']));
    }

    /** @test */
    public function test_buying_awards_xp()
    {
        $player = Player::factory()->create([
            'credits' => 10000.00,
            'experience' => 0,
        ]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 1000,
            'current_cargo' => 0,
        ]);

        $mineral = Mineral::factory()->create();
        $tradingHub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'quantity' => 1000,
            'sell_price' => 10.00,
        ]);

        // Buy 100 units = 1 XP per 10 units = 10 XP
        $result = $this->tradingService->buyMineral($player, $playerShip, $inventory, 100);

        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['xp_earned']);
        $this->assertEquals(10, $player->fresh()->experience);
    }

    /** @test */
    public function test_buying_awards_minimum_5_xp()
    {
        $player = Player::factory()->create([
            'credits' => 10000.00,
            'experience' => 0,
        ]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 1000,
            'current_cargo' => 0,
        ]);

        $mineral = Mineral::factory()->create();
        $tradingHub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'quantity' => 1000,
            'sell_price' => 10.00,
        ]);

        // Buy 10 units = 1 XP, but minimum is 5
        $result = $this->tradingService->buyMineral($player, $playerShip, $inventory, 10);

        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['xp_earned']);
    }

    /** @test */
    public function selling_minerals_adds_credits()
    {
        $player = Player::factory()->create(['credits' => 1000.00]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 50,
        ]);

        $mineral = Mineral::factory()->create();
        $cargo = PlayerCargo::factory()->create([
            'player_ship_id' => $playerShip->id,
            'mineral_id' => $mineral->id,
            'quantity' => 50,
        ]);

        $tradingHub = TradingHub::factory()->create();
        $hubInventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'buy_price' => 30.00,
        ]);

        $result = $this->tradingService->sellMineral($player, $playerShip, $cargo, $hubInventory, 50);

        $this->assertTrue($result['success']);
        $this->assertEquals(1500.00, $result['total_revenue']); // 50 * 30
        $this->assertEquals(2500.00, $player->fresh()->credits); // 1000 + 1500
    }

    /** @test */
    public function selling_removes_from_cargo()
    {
        $player = Player::factory()->create(['credits' => 1000.00]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 50,
        ]);

        $mineral = Mineral::factory()->create();
        $cargo = PlayerCargo::factory()->create([
            'player_ship_id' => $playerShip->id,
            'mineral_id' => $mineral->id,
            'quantity' => 50,
        ]);

        $tradingHub = TradingHub::factory()->create();
        $hubInventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'buy_price' => 30.00,
        ]);

        $result = $this->tradingService->sellMineral($player, $playerShip, $cargo, $hubInventory, 30);

        $this->assertTrue($result['success']);
        $this->assertEquals(20, $cargo->fresh()->quantity); // 50 - 30
    }

    /** @test */
    public function selling_all_cargo_deletes_cargo_record()
    {
        $player = Player::factory()->create(['credits' => 1000.00]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 50,
        ]);

        $mineral = Mineral::factory()->create();
        $cargo = PlayerCargo::factory()->create([
            'player_ship_id' => $playerShip->id,
            'mineral_id' => $mineral->id,
            'quantity' => 50,
        ]);

        $cargoId = $cargo->id;

        $tradingHub = TradingHub::factory()->create();
        $hubInventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'buy_price' => 30.00,
        ]);

        $result = $this->tradingService->sellMineral($player, $playerShip, $cargo, $hubInventory, 50);

        $this->assertTrue($result['success']);
        $this->assertNull(PlayerCargo::find($cargoId)); // Cargo deleted
    }

    /** @test */
    public function selling_updates_ship_cargo()
    {
        $player = Player::factory()->create(['credits' => 1000.00]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 75,
        ]);

        $mineral = Mineral::factory()->create();
        $cargo = PlayerCargo::factory()->create([
            'player_ship_id' => $playerShip->id,
            'mineral_id' => $mineral->id,
            'quantity' => 75,
        ]);

        $tradingHub = TradingHub::factory()->create();
        $hubInventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'buy_price' => 20.00,
        ]);

        $result = $this->tradingService->sellMineral($player, $playerShip, $cargo, $hubInventory, 50);

        $this->assertTrue($result['success']);
        $this->assertEquals(25, $playerShip->fresh()->current_cargo); // 75 - 50
    }

    /** @test */
    public function selling_awards_xp()
    {
        $player = Player::factory()->create([
            'credits' => 1000.00,
            'experience' => 0,
        ]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 100,
        ]);

        $mineral = Mineral::factory()->create();
        $cargo = PlayerCargo::factory()->create([
            'player_ship_id' => $playerShip->id,
            'mineral_id' => $mineral->id,
            'quantity' => 100,
        ]);

        $tradingHub = TradingHub::factory()->create();
        $hubInventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'buy_price' => 50.00,
        ]);

        // Sell 100 units at 50 credits = 5000 revenue = 50 XP (1 XP per 100 credits)
        $result = $this->tradingService->sellMineral($player, $playerShip, $cargo, $hubInventory, 100);

        $this->assertTrue($result['success']);
        $this->assertEquals(50, $result['xp_earned']);
        $this->assertEquals(50, $player->fresh()->experience);
    }

    /** @test */
    public function selling_awards_minimum_10_xp()
    {
        $player = Player::factory()->create([
            'credits' => 1000.00,
            'experience' => 0,
        ]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 10,
        ]);

        $mineral = Mineral::factory()->create();
        $cargo = PlayerCargo::factory()->create([
            'player_ship_id' => $playerShip->id,
            'mineral_id' => $mineral->id,
            'quantity' => 10,
        ]);

        $tradingHub = TradingHub::factory()->create();
        $hubInventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'buy_price' => 5.00,
        ]);

        // Sell 10 units at 5 credits = 50 revenue = 0.5 XP, but minimum is 10
        $result = $this->tradingService->sellMineral($player, $playerShip, $cargo, $hubInventory, 10);

        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['xp_earned']);
    }

    /** @test */
    public function test_cannot_sell_more_than_available()
    {
        $player = Player::factory()->create(['credits' => 1000.00]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 30,
        ]);

        $mineral = Mineral::factory()->create();
        $cargo = PlayerCargo::factory()->create([
            'player_ship_id' => $playerShip->id,
            'mineral_id' => $mineral->id,
            'quantity' => 30,
        ]);

        $tradingHub = TradingHub::factory()->create();
        $hubInventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'buy_price' => 20.00,
        ]);

        // Try to sell 50 when only have 30
        $result = $this->tradingService->sellMineral($player, $playerShip, $cargo, $hubInventory, 50);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('don\'t have', strtolower($result['message']));
    }

    /** @test */
    public function test_buying_reduces_hub_inventory()
    {
        $player = Player::factory()->create(['credits' => 10000.00]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 1000,
            'current_cargo' => 0,
        ]);

        $mineral = Mineral::factory()->create();
        $tradingHub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'quantity' => 1000,
            'sell_price' => 10.00,
        ]);

        $result = $this->tradingService->buyMineral($player, $playerShip, $inventory, 100);

        $this->assertTrue($result['success']);
        $this->assertEquals(900, $inventory->fresh()->quantity); // 1000 - 100
    }

    /** @test */
    public function selling_increases_hub_inventory()
    {
        $player = Player::factory()->create(['credits' => 1000.00]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 50,
        ]);

        $mineral = Mineral::factory()->create();
        $cargo = PlayerCargo::factory()->create([
            'player_ship_id' => $playerShip->id,
            'mineral_id' => $mineral->id,
            'quantity' => 50,
        ]);

        $tradingHub = TradingHub::factory()->create();
        $hubInventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'quantity' => 500,
            'buy_price' => 20.00,
        ]);

        $result = $this->tradingService->sellMineral($player, $playerShip, $cargo, $hubInventory, 50);

        $this->assertTrue($result['success']);
        $this->assertEquals(550, $hubInventory->fresh()->quantity); // 500 + 50
    }

    /** @test */
    public function test_first_buy_is_free()
    {
        $player = Player::factory()->create(['credits' => 500.00, 'settings' => []]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 0,
        ]);

        $mineral = Mineral::factory()->create();
        $tradingHub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'quantity' => 1000,
            'sell_price' => 50.00,
        ]);

        // First buy should be free even though 10 * 50 = 500 would normally cost credits
        $result = $this->tradingService->buyMineral($player, $playerShip, $inventory, 10);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['tutorial']);
        $this->assertEquals(0, $result['total_cost']);
        $this->assertEquals(500.00, $player->fresh()->credits); // Credits unchanged
        $this->assertEquals(10, $playerShip->fresh()->current_cargo); // Cargo updated
        $this->assertTrue($player->fresh()->hasCompletedTutorial('first_mineral_buy'));
    }

    /** @test */
    public function test_second_buy_costs_credits()
    {
        $player = Player::factory()->create([
            'credits' => 10000.00,
            'settings' => ['completed_tutorials' => ['first_mineral_buy']],
        ]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 0,
        ]);

        $mineral = Mineral::factory()->create();
        $tradingHub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'quantity' => 1000,
            'sell_price' => 50.00,
        ]);

        $result = $this->tradingService->buyMineral($player, $playerShip, $inventory, 10);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['tutorial']);
        $this->assertEquals(500.00, $result['total_cost']); // 10 * 50
        $this->assertEquals(9500.00, $player->fresh()->credits); // 10000 - 500
    }

    /** @test */
    public function test_first_sell_earns_nothing()
    {
        $player = Player::factory()->create(['credits' => 1000.00, 'settings' => []]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 50,
        ]);

        $mineral = Mineral::factory()->create();
        $cargo = PlayerCargo::factory()->create([
            'player_ship_id' => $playerShip->id,
            'mineral_id' => $mineral->id,
            'quantity' => 50,
        ]);

        $tradingHub = TradingHub::factory()->create();
        $hubInventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'buy_price' => 30.00,
        ]);

        $result = $this->tradingService->sellMineral($player, $playerShip, $cargo, $hubInventory, 10);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['tutorial']);
        $this->assertEquals(0, $result['total_revenue']);
        $this->assertEquals(1000.00, $player->fresh()->credits); // Credits unchanged
        $this->assertEquals(40, $cargo->fresh()->quantity); // Cargo removed
        $this->assertTrue($player->fresh()->hasCompletedTutorial('first_mineral_sell'));
    }

    /** @test */
    public function test_second_sell_earns_credits()
    {
        $player = Player::factory()->create([
            'credits' => 1000.00,
            'settings' => ['completed_tutorials' => ['first_mineral_sell']],
        ]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 50,
        ]);

        $mineral = Mineral::factory()->create();
        $cargo = PlayerCargo::factory()->create([
            'player_ship_id' => $playerShip->id,
            'mineral_id' => $mineral->id,
            'quantity' => 50,
        ]);

        $tradingHub = TradingHub::factory()->create();
        $hubInventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'buy_price' => 30.00,
        ]);

        $result = $this->tradingService->sellMineral($player, $playerShip, $cargo, $hubInventory, 10);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['tutorial']);
        $this->assertEquals(300.00, $result['total_revenue']); // 10 * 30
        $this->assertEquals(1300.00, $player->fresh()->credits); // 1000 + 300
    }

    /** @test */
    public function get_max_affordable_quantity_calculates_correctly()
    {
        $player = Player::factory()->create(['credits' => 1000.00]);

        $mineral = Mineral::factory()->create();
        $tradingHub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'quantity' => 100,
            'sell_price' => 25.00,
        ]);

        // Can afford 1000 / 25 = 40 units, but only 100 in stock
        $maxAffordable = $this->tradingService->getMaxAffordableQuantity($player, $inventory);

        $this->assertEquals(40, $maxAffordable);
    }

    /** @test */
    public function get_max_affordable_limited_by_stock()
    {
        $player = Player::factory()->create(['credits' => 10000.00]);

        $mineral = Mineral::factory()->create();
        $tradingHub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $tradingHub->id,
            'mineral_id' => $mineral->id,
            'quantity' => 30, // Only 30 in stock
            'sell_price' => 10.00,
        ]);

        // Can afford 1000 units, but only 30 in stock
        $maxAffordable = $this->tradingService->getMaxAffordableQuantity($player, $inventory);

        $this->assertEquals(30, $maxAffordable);
    }
}

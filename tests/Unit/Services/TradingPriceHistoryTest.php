<?php

namespace Tests\Unit\Services;

use App\Enums\Trading\MineralRarity;
use App\Models\Galaxy;
use App\Models\Mineral;
use App\Models\Player;
use App\Models\PlayerCargo;
use App\Models\PlayerPriceSighting;
use App\Models\PlayerShip;
use App\Models\PlayerTradeTransaction;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use App\Models\User;
use App\Services\TradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TradingPriceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private TradingService $tradingService;

    private Player $player;

    private TradingHub $tradingHub;

    private Mineral $mineral;

    private PlayerShip $ship;

    private TradingHubInventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tradingService = new TradingService;

        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create();

        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 100,
            'y' => 100,
            'is_inhabited' => true,
        ]);

        $this->tradingHub = TradingHub::create([
            'uuid' => Str::uuid(),
            'poi_id' => $poi->id,
            'name' => 'Test Hub',
            'type' => 'standard',
            'gate_count' => 3,
            'tax_rate' => 5.0,
            'is_active' => true,
        ]);

        $this->player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
            'credits' => 50000,
        ]);

        $shipBlueprint = Ship::factory()->create();
        $this->ship = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $shipBlueprint->id,
            'cargo_hold' => 500,
            'current_cargo' => 0,
            'is_active' => true,
            'status' => 'operational',
        ]);

        $this->mineral = Mineral::create([
            'uuid' => Str::uuid(),
            'name' => 'Iron Ore',
            'symbol' => 'Fe',
            'description' => 'Common mineral',
            'base_value' => 100.00,
            'rarity' => MineralRarity::COMMON,
            'attributes' => [],
        ]);

        $this->inventory = TradingHubInventory::create([
            'trading_hub_id' => $this->tradingHub->id,
            'mineral_id' => $this->mineral->id,
            'quantity' => 1000,
            'current_price' => 100.00,
            'buy_price' => 80.00,
            'sell_price' => 120.00,
        ]);
    }

    public function test_record_price_sightings_creates_records(): void
    {
        $inventory = TradingHubInventory::where('trading_hub_id', $this->tradingHub->id)
            ->get();

        $result = $this->tradingService->recordPriceSightings(
            $this->player,
            $this->tradingHub,
            $inventory
        );

        $this->assertTrue($result);
        $this->assertDatabaseHas('player_price_sightings', [
            'player_id' => $this->player->id,
            'trading_hub_id' => $this->tradingHub->id,
            'mineral_id' => $this->mineral->id,
            'buy_price' => 80.00,
            'sell_price' => 120.00,
            'quantity' => 1000,
        ]);
    }

    public function test_sightings_throttled_within_five_minutes(): void
    {
        $inventory = TradingHubInventory::where('trading_hub_id', $this->tradingHub->id)
            ->get();

        // First call should succeed
        $result1 = $this->tradingService->recordPriceSightings(
            $this->player,
            $this->tradingHub,
            $inventory
        );
        $this->assertTrue($result1);

        // Second call within 5 minutes should be throttled
        $result2 = $this->tradingService->recordPriceSightings(
            $this->player,
            $this->tradingHub,
            $inventory
        );
        $this->assertFalse($result2);

        // Only one set of sightings should exist
        $count = PlayerPriceSighting::where('player_id', $this->player->id)
            ->where('trading_hub_id', $this->tradingHub->id)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_sightings_allowed_after_five_minutes(): void
    {
        $inventory = TradingHubInventory::where('trading_hub_id', $this->tradingHub->id)
            ->get();

        // Record first sighting
        $this->tradingService->recordPriceSightings(
            $this->player,
            $this->tradingHub,
            $inventory
        );

        // Backdate the sighting to 6 minutes ago
        PlayerPriceSighting::where('player_id', $this->player->id)
            ->update(['recorded_at' => now()->subMinutes(6)]);

        // Second call should now succeed
        $result = $this->tradingService->recordPriceSightings(
            $this->player,
            $this->tradingHub,
            $inventory
        );
        $this->assertTrue($result);

        $count = PlayerPriceSighting::where('player_id', $this->player->id)
            ->where('trading_hub_id', $this->tradingHub->id)
            ->count();
        $this->assertEquals(2, $count);
    }

    public function test_buy_mineral_creates_transaction_log(): void
    {
        $result = $this->tradingService->buyMineral(
            $this->player,
            $this->ship,
            $this->inventory,
            10
        );

        $this->assertTrue($result['success']);

        $transaction = PlayerTradeTransaction::where('player_id', $this->player->id)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('buy', $transaction->transaction_type);
        $this->assertEquals(10, $transaction->quantity);
        $this->assertEquals(120.00, $transaction->unit_price);
        $this->assertEquals(1200.00, $transaction->total_amount);
        $this->assertNotNull($transaction->uuid);
    }

    public function test_sell_mineral_creates_transaction_log(): void
    {
        // Give the player some cargo to sell
        $cargo = PlayerCargo::create([
            'player_ship_id' => $this->ship->id,
            'mineral_id' => $this->mineral->id,
            'quantity' => 20,
        ]);
        $this->ship->update(['current_cargo' => 20]);

        $result = $this->tradingService->sellMineral(
            $this->player,
            $this->ship,
            $cargo,
            $this->inventory,
            10
        );

        $this->assertTrue($result['success']);

        $transaction = PlayerTradeTransaction::where('player_id', $this->player->id)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('sell', $transaction->transaction_type);
        $this->assertEquals(10, $transaction->quantity);
        $this->assertEquals(80.00, $transaction->unit_price);
        $this->assertEquals(800.00, $transaction->total_amount);
    }

    public function test_credits_after_captured_correctly_on_buy(): void
    {
        $initialCredits = $this->player->credits;

        $this->tradingService->buyMineral(
            $this->player,
            $this->ship,
            $this->inventory,
            10
        );

        $transaction = PlayerTradeTransaction::where('player_id', $this->player->id)->first();
        $expectedCreditsAfter = $initialCredits - (120.00 * 10);
        $this->assertEquals($expectedCreditsAfter, (float) $transaction->credits_after);
    }

    public function test_credits_after_captured_correctly_on_sell(): void
    {
        $initialCredits = $this->player->credits;

        $cargo = PlayerCargo::create([
            'player_ship_id' => $this->ship->id,
            'mineral_id' => $this->mineral->id,
            'quantity' => 20,
        ]);
        $this->ship->update(['current_cargo' => 20]);

        $this->tradingService->sellMineral(
            $this->player,
            $this->ship,
            $cargo,
            $this->inventory,
            10
        );

        $transaction = PlayerTradeTransaction::where('player_id', $this->player->id)->first();
        $expectedCreditsAfter = $initialCredits + (80.00 * 10);
        $this->assertEquals($expectedCreditsAfter, (float) $transaction->credits_after);
    }

    public function test_failed_buy_does_not_create_transaction(): void
    {
        $this->player->update(['credits' => 10]);

        $result = $this->tradingService->buyMineral(
            $this->player,
            $this->ship,
            $this->inventory,
            10
        );

        $this->assertFalse($result['success']);
        $this->assertEquals(0, PlayerTradeTransaction::where('player_id', $this->player->id)->count());
    }

    public function test_failed_sell_does_not_create_transaction(): void
    {
        $cargo = PlayerCargo::create([
            'player_ship_id' => $this->ship->id,
            'mineral_id' => $this->mineral->id,
            'quantity' => 5,
        ]);
        $this->ship->update(['current_cargo' => 5]);

        $result = $this->tradingService->sellMineral(
            $this->player,
            $this->ship,
            $cargo,
            $this->inventory,
            10 // More than available
        );

        $this->assertFalse($result['success']);
        $this->assertEquals(0, PlayerTradeTransaction::where('player_id', $this->player->id)->count());
    }
}

<?php

namespace Tests\Feature\Api;

use App\Enums\Trading\MineralRarity;
use App\Models\Galaxy;
use App\Models\Mineral;
use App\Models\Player;
use App\Models\PlayerCargo;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    private Galaxy $galaxy;

    private PointOfInterest $hubLocation;

    private TradingHub $tradingHub;

    private Mineral $mineral;

    private PlayerShip $ship;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user and get token
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        // Create galaxy
        $this->galaxy = Galaxy::factory()->create();

        // Create hub location
        $this->hubLocation = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'name' => 'Trade Station Alpha',
            'x' => 100,
            'y' => 100,
            'is_inhabited' => true,
        ]);

        // Create trading hub
        $this->tradingHub = TradingHub::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'poi_id' => $this->hubLocation->id,
            'name' => 'Alpha Trading Hub',
            'type' => 'standard',
            'gate_count' => 3,
            'tax_rate' => 5.0,
            'services' => ['trading', 'repair'],
            'has_salvage_yard' => false,
            'has_plans' => false,
            'is_active' => true,
        ]);

        // Create player at hub location
        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->hubLocation->id,
            'credits' => 10000,
            'experience' => 0,
            'level' => 1,
        ]);

        // Create ship
        $shipBlueprint = Ship::factory()->create([
            'class' => 'scout',
            'name' => 'Scout',
        ]);

        $this->ship = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $shipBlueprint->id,
            'name' => 'Test Ship',
            'current_fuel' => 100,
            'max_fuel' => 100,
            'hull' => 100,
            'max_hull' => 100,
            'weapons' => 10,
            'cargo_hold' => 500,
            'current_cargo' => 0,
            'sensors' => 2,
            'warp_drive' => 1,
            'is_active' => true,
            'status' => 'operational',
        ]);

        // Create mineral
        $this->mineral = Mineral::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'name' => 'Iron Ore',
            'symbol' => 'Fe',
            'description' => 'Common mineral',
            'base_value' => 100.00,
            'rarity' => MineralRarity::COMMON,
            'attributes' => [],
        ]);

        // Create hub inventory
        TradingHubInventory::create([
            'trading_hub_id' => $this->tradingHub->id,
            'mineral_id' => $this->mineral->id,
            'quantity' => 1000,
            'current_price' => 100.00,
            'buy_price' => 80.00,
            'sell_price' => 120.00,
        ]);
    }

    public function test_user_can_list_nearby_trading_hubs(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/trading-hubs?player_uuid={$this->player->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'hubs' => [
                        '*' => ['id', 'uuid', 'name', 'type', 'tier'],
                    ],
                    'search_radius',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_list_hubs_respects_sensor_range(): void
    {
        // Create a hub far away (beyond sensor range)
        $farLocation = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'x' => 1000,
            'y' => 1000,
        ]);

        TradingHub::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'poi_id' => $farLocation->id,
            'name' => 'Distant Hub',
            'type' => 'standard',
            'gate_count' => 2,
            'tax_rate' => 5.0,
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/trading-hubs?player_uuid={$this->player->uuid}");

        $response->assertStatus(200);

        // Should only see the nearby hub, not the far one
        $hubs = $response->json('data.hubs');
        $this->assertCount(1, $hubs);
    }

    public function test_user_can_get_hub_details(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/trading-hubs/{$this->hubLocation->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id', 'uuid', 'name', 'type', 'tier', 'gate_count',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Alpha Trading Hub',
                ],
            ]);
    }

    public function test_user_can_get_hub_inventory(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/trading-hubs/{$this->hubLocation->uuid}/inventory");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'hub',
                    'inventory' => [
                        '*' => ['mineral', 'quantity', 'buy_price', 'sell_price'],
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_user_can_list_all_minerals(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/minerals');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'uuid', 'name', 'symbol', 'base_value', 'rarity'],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_user_can_buy_minerals(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/trading-hubs/{$this->hubLocation->uuid}/buy", [
                'player_uuid' => $this->player->uuid,
                'mineral_id' => $this->mineral->id,
                'quantity' => 10,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'transaction_type',
                    'mineral',
                    'quantity',
                    'price_per_unit',
                    'total_cost',
                    'credits_remaining',
                    'cargo_remaining',
                    'xp_earned',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'transaction_type' => 'buy',
                    'quantity' => 10,
                ],
            ]);

        // Verify cargo was updated
        $cargo = PlayerCargo::where('player_ship_id', $this->ship->id)
            ->where('mineral_id', $this->mineral->id)
            ->first();

        $this->assertNotNull($cargo);
        $this->assertEquals(10, $cargo->quantity);

        // Verify credits were deducted
        $this->player->refresh();
        $this->assertEquals(8800, $this->player->credits); // 10000 - (120 * 10)
    }

    public function test_buy_fails_with_insufficient_credits(): void
    {
        $this->player->update(['credits' => 100]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/trading-hubs/{$this->hubLocation->uuid}/buy", [
                'player_uuid' => $this->player->uuid,
                'mineral_id' => $this->mineral->id,
                'quantity' => 10,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_buy_fails_with_insufficient_cargo_space(): void
    {
        $this->ship->update(['current_cargo' => 495]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/trading-hubs/{$this->hubLocation->uuid}/buy", [
                'player_uuid' => $this->player->uuid,
                'mineral_id' => $this->mineral->id,
                'quantity' => 10,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_can_sell_minerals(): void
    {
        // First buy some minerals
        PlayerCargo::create([
            'player_ship_id' => $this->ship->id,
            'mineral_id' => $this->mineral->id,
            'quantity' => 20,
        ]);
        $this->ship->update(['current_cargo' => 20]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/trading-hubs/{$this->hubLocation->uuid}/sell", [
                'player_uuid' => $this->player->uuid,
                'mineral_id' => $this->mineral->id,
                'quantity' => 10,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'transaction_type',
                    'mineral',
                    'quantity',
                    'price_per_unit',
                    'total_revenue',
                    'credits_remaining',
                    'cargo_remaining',
                    'xp_earned',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'transaction_type' => 'sell',
                    'quantity' => 10,
                ],
            ]);

        // Verify cargo was updated
        $cargo = PlayerCargo::where('player_ship_id', $this->ship->id)
            ->where('mineral_id', $this->mineral->id)
            ->first();

        $this->assertEquals(10, $cargo->quantity);

        // Verify credits were added
        $this->player->refresh();
        $this->assertEquals(10800, $this->player->credits); // 10000 + (80 * 10)
    }

    public function test_sell_fails_without_cargo(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/trading-hubs/{$this->hubLocation->uuid}/sell", [
                'player_uuid' => $this->player->uuid,
                'mineral_id' => $this->mineral->id,
                'quantity' => 10,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_can_get_cargo_manifest(): void
    {
        // Add some cargo
        PlayerCargo::create([
            'player_ship_id' => $this->ship->id,
            'mineral_id' => $this->mineral->id,
            'quantity' => 50,
        ]);
        $this->ship->update(['current_cargo' => 50]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$this->player->uuid}/cargo");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'ship_uuid',
                    'ship_name',
                    'current_cargo',
                    'cargo_capacity',
                    'available_space',
                    'cargo' => [
                        '*' => ['mineral', 'quantity'],
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'current_cargo' => 50,
                    'cargo_capacity' => 500,
                ],
            ]);
    }

    public function test_user_can_calculate_affordability(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/trading/affordability?player_uuid={$this->player->uuid}&hub_uuid={$this->hubLocation->uuid}&mineral_id={$this->mineral->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'max_affordable',
                    'max_by_cargo_space',
                    'max_purchasable',
                    'price_per_unit',
                    'total_cost',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Should be able to afford 83 units (10000 / 120 = 83.33)
        $this->assertEquals(83, $response->json('data.max_affordable'));
    }

    public function test_trading_validates_input(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/trading-hubs/{$this->hubLocation->uuid}/buy", [
                'player_uuid' => $this->player->uuid,
                // Missing mineral_id and quantity
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_trading_requires_authentication(): void
    {
        $endpoints = [
            ['GET', "/api/trading-hubs?player_uuid={$this->player->uuid}"],
            ['GET', "/api/trading-hubs/{$this->hubLocation->uuid}"],
            ['GET', "/api/trading-hubs/{$this->hubLocation->uuid}/inventory"],
            ['GET', '/api/minerals'],
            ['POST', "/api/trading-hubs/{$this->hubLocation->uuid}/buy"],
            ['POST', "/api/trading-hubs/{$this->hubLocation->uuid}/sell"],
            ['GET', "/api/players/{$this->player->uuid}/cargo"],
            ['GET', "/api/trading/affordability?player_uuid={$this->player->uuid}&hub_uuid={$this->hubLocation->uuid}&mineral_id=1"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $response->assertStatus(401);
        }
    }

    public function test_user_cannot_access_other_users_trading(): void
    {
        $otherUser = User::factory()->create();
        $otherPlayer = Player::factory()->create([
            'user_id' => $otherUser->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->hubLocation->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/trading-hubs/{$this->hubLocation->uuid}/buy", [
                'player_uuid' => $otherPlayer->uuid,
                'mineral_id' => $this->mineral->id,
                'quantity' => 10,
            ]);

        $response->assertStatus(404);
    }
}

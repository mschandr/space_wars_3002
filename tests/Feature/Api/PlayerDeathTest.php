<?php

namespace Tests\Feature\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\ShipShop;
use App\Models\TradingHub;
use App\Models\User;
use App\Services\PlayerDeathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerDeathTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Player $player;
    private Galaxy $galaxy;
    private PlayerShip $playerShip;
    private PlayerDeathService $deathService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->galaxy = Galaxy::factory()->create();

        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
        ]);

        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $poi->id,
            'credits' => 10000,
            'experience' => 500,
        ]);

        $ship = Ship::factory()->create(['base_price' => 15000]);
        $this->playerShip = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $ship->id,
            'is_active' => true,
            'name' => 'Test Ship Alpha',
        ]);

        $this->deathService = app(PlayerDeathService::class);
    }

    public function test_it_processes_player_death_successfully()
    {
        $result = $this->deathService->processPlayerDeath($this->player, $this->playerShip);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('losses', $result);
        $this->assertArrayHasKey('respawn_location', $result);
        $this->assertArrayHasKey('credits_retained', $result);
        $this->assertArrayHasKey('xp_retained', $result);
    }

    public function test_it_destroys_ship_on_death()
    {
        $shipId = $this->playerShip->id;

        $this->deathService->processPlayerDeath($this->player, $this->playerShip);

        $this->assertDatabaseMissing('player_ships', ['id' => $shipId]);
    }

    public function test_it_removes_all_upgrade_plans_on_death()
    {
        // Add some plans to the player
        $plan1 = \App\Models\Plan::factory()->create();
        $plan2 = \App\Models\Plan::factory()->create();

        $this->player->plans()->attach($plan1->id, ['acquired_at' => now()]);
        $this->player->plans()->attach($plan2->id, ['acquired_at' => now()]);

        $this->assertEquals(2, $this->player->plans()->count());

        $this->deathService->processPlayerDeath($this->player, $this->playerShip);

        $this->player->refresh();
        $this->assertEquals(0, $this->player->plans()->count());
    }

    public function test_it_retains_credits_on_death()
    {
        $originalCredits = $this->player->credits;

        $result = $this->deathService->processPlayerDeath($this->player, $this->playerShip);

        $this->player->refresh();
        $this->assertEquals($originalCredits, $this->player->credits);
        $this->assertEquals($originalCredits, $result['credits_retained']);
    }

    public function test_it_retains_experience_on_death()
    {
        $originalXp = $this->player->experience;

        $result = $this->deathService->processPlayerDeath($this->player, $this->playerShip);

        $this->player->refresh();
        $this->assertEquals($originalXp, $this->player->experience);
        $this->assertEquals($originalXp, $result['xp_retained']);
    }

    public function test_it_grants_minimum_credits_if_player_is_broke()
    {
        $this->player->update(['credits' => 100]); // Player has only 100 credits

        $result = $this->deathService->processPlayerDeath($this->player, $this->playerShip);

        $this->player->refresh();
        $this->assertEquals(5000, $this->player->credits); // Minimum credits granted
        $this->assertEquals(4900, $result['credits_granted']); // 5000 - 100 = 4900 granted
    }

    public function test_it_does_not_grant_credits_if_player_has_enough()
    {
        $this->player->update(['credits' => 10000]);

        $result = $this->deathService->processPlayerDeath($this->player, $this->playerShip);

        $this->player->refresh();
        $this->assertEquals(10000, $this->player->credits);
        $this->assertEquals(0, $result['credits_granted']);
    }

    public function test_it_respawns_at_trading_hub_with_ships()
    {
        // Create trading hub with ships available
        $hubPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_hidden' => false,
        ]);

        $tradingHub = TradingHub::factory()->create([
            'poi_id' => $hubPoi->id,
            'is_active' => true,
        ]);

        // Add ship to trading hub
        $ship = Ship::factory()->create();
        \DB::table('trading_hub_ships')->insert([
            'trading_hub_id' => $tradingHub->id,
            'ship_id' => $ship->id,
            'galaxy_id' => $this->galaxy->id,
            'quantity' => 5,
            'current_price' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->deathService->processPlayerDeath($this->player, $this->playerShip);

        $this->player->refresh();
        $this->assertNotNull($result['respawn_location']);
        $this->assertEquals($hubPoi->id, $this->player->current_poi_id);
    }

    public function test_it_respawns_at_last_trading_hub_if_it_has_ships()
    {
        // Create last visited hub with ships
        $lastHubPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_hidden' => false,
        ]);

        $tradingHub = TradingHub::factory()->create([
            'poi_id' => $lastHubPoi->id,
            'is_active' => true,
        ]);

        // Add ship to trading hub
        $ship = Ship::factory()->create();
        \DB::table('trading_hub_ships')->insert([
            'trading_hub_id' => $tradingHub->id,
            'ship_id' => $ship->id,
            'galaxy_id' => $this->galaxy->id,
            'quantity' => 3,
            'current_price' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Set as last visited hub
        $this->player->update(['last_trading_hub_poi_id' => $lastHubPoi->id]);

        $result = $this->deathService->processPlayerDeath($this->player, $this->playerShip);

        $this->player->refresh();
        $this->assertEquals($lastHubPoi->id, $this->player->current_poi_id);
        $this->assertEquals($lastHubPoi->id, $result['respawn_location']->id);
    }

    public function test_it_prioritizes_hub_with_ships_over_regular_trading_hub()
    {
        // Create regular trading hub (no ships)
        $regularHub = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_hidden' => false,
        ]);

        TradingHub::factory()->create([
            'poi_id' => $regularHub->id,
            'is_active' => true,
        ]);

        // Create trading hub WITH ships
        $hubWithShips = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_hidden' => false,
        ]);

        $tradingHubWithShips = TradingHub::factory()->create([
            'poi_id' => $hubWithShips->id,
            'is_active' => true,
        ]);

        // Add ship to trading hub
        $ship = Ship::factory()->create();
        \DB::table('trading_hub_ships')->insert([
            'trading_hub_id' => $tradingHubWithShips->id,
            'ship_id' => $ship->id,
            'galaxy_id' => $this->galaxy->id,
            'quantity' => 2,
            'current_price' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->deathService->processPlayerDeath($this->player, $this->playerShip);

        $this->player->refresh();
        // Should respawn at hub with ships, not regular hub
        $this->assertEquals($hubWithShips->id, $this->player->current_poi_id);
    }

    public function test_it_records_losses_correctly()
    {
        // Add cargo to ship
        $mineral = \App\Models\Mineral::factory()->create(['base_value' => 100]);
        \App\Models\PlayerCargo::create([
            'player_ship_id' => $this->playerShip->id,
            'mineral_id' => $mineral->id,
            'quantity' => 50,
        ]);

        // Add plans
        $plan = \App\Models\Plan::factory()->create();
        $this->player->plans()->attach($plan->id, ['acquired_at' => now()]);

        $result = $this->deathService->processPlayerDeath($this->player, $this->playerShip);

        $losses = $result['losses'];

        $this->assertArrayHasKey('ship_name', $losses);
        $this->assertArrayHasKey('ship_class', $losses);
        $this->assertArrayHasKey('cargo_items_lost', $losses);
        $this->assertArrayHasKey('estimated_cargo_value', $losses);
        $this->assertArrayHasKey('upgrade_plans_lost', $losses);
        $this->assertArrayHasKey('ship_value', $losses);

        $this->assertEquals('Test Ship Alpha', $losses['ship_name']);
        $this->assertEquals(1, $losses['cargo_items_lost']);
        $this->assertEquals(1, $losses['upgrade_plans_lost']);
    }

    public function test_it_generates_death_message()
    {
        $result = $this->deathService->processPlayerDeath($this->player, $this->playerShip);
        $message = $this->deathService->generateDeathMessage($result);

        $this->assertStringContainsString('SHIP DESTROYED', $message);
        $this->assertStringContainsString('Test Ship Alpha', $message);
        $this->assertStringContainsString('LOSSES:', $message);
        $this->assertStringContainsString('RETAINED:', $message);
        $this->assertStringContainsString('escape pod', $message);
    }

    public function test_death_message_includes_credits_granted_when_applicable()
    {
        $this->player->update(['credits' => 100]);

        $result = $this->deathService->processPlayerDeath($this->player, $this->playerShip);
        $message = $this->deathService->generateDeathMessage($result);

        $this->assertStringContainsString('EMERGENCY FUNDS', $message);
        $this->assertStringContainsString('4,900', $message); // 5000 - 100
    }

    public function test_death_message_indicates_ship_availability()
    {
        // Create hub with ships
        $hubPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
        ]);

        $tradingHub = TradingHub::factory()->create([
            'poi_id' => $hubPoi->id,
            'is_active' => true,
        ]);

        // Add ship to trading hub
        $ship = Ship::factory()->create();
        \DB::table('trading_hub_ships')->insert([
            'trading_hub_id' => $tradingHub->id,
            'ship_id' => $ship->id,
            'galaxy_id' => $this->galaxy->id,
            'quantity' => 1,
            'current_price' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->deathService->processPlayerDeath($this->player, $this->playerShip);
        $message = $this->deathService->generateDeathMessage($result);

        $this->assertStringContainsString('ships available', $message);
        $this->assertStringContainsString('purchase a new ship', $message);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\StellarCartographer;
use App\Models\TradingHub;
use App\Models\TradingHubShip;
use App\Models\User;
use App\Models\WarpGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    /**
     * Test user can list their players
     */
    public function test_user_can_list_their_players(): void
    {
        // Create 3 different galaxies to avoid unique constraint
        $galaxies = Galaxy::factory()->count(3)->create();

        foreach ($galaxies as $galaxy) {
            Player::factory()->create([
                'user_id' => $this->user->id,
                'galaxy_id' => $galaxy->id,
            ]);
        }

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/players');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['uuid', 'call_sign', 'credits', 'level'],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /**
     * Test user can create a new player
     */
    public function test_user_can_create_player(): void
    {
        $galaxy = Galaxy::factory()->create();

        // Create multiple inhabited star systems to ensure the random query finds one
        PointOfInterest::factory()->count(5)->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/players', [
                'galaxy_id' => $galaxy->id,
                'call_sign' => 'Captain Nova',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['uuid', 'call_sign', 'credits', 'level'],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'call_sign' => 'Captain Nova',
                    'credits' => 10000,
                    'level' => 1,
                ],
            ]);

        $this->assertDatabaseHas('players', [
            'user_id' => $this->user->id,
            'call_sign' => 'Captain Nova',
            'galaxy_id' => $galaxy->id,
        ]);

        // Verify no ship was auto-assigned
        $player = Player::where('call_sign', 'Captain Nova')->first();
        $this->assertNull($player->activeShip);
        $this->assertEquals(0, $player->ships()->count());
    }

    /**
     * Test cannot create player with duplicate call sign in same galaxy
     */
    public function test_cannot_create_player_with_duplicate_call_sign(): void
    {
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
        ]);

        Player::factory()->create([
            'galaxy_id' => $galaxy->id,
            'call_sign' => 'Existing Player',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/players', [
                'galaxy_id' => $galaxy->id,
                'call_sign' => 'Existing Player',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'DUPLICATE_CALL_SIGN',
                ],
            ]);
    }

    /**
     * Test user can get player details by UUID
     */
    public function test_user_can_get_player_details(): void
    {
        $galaxy = Galaxy::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$player->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['uuid', 'call_sign', 'credits', 'level'],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'uuid' => $player->uuid,
                    'call_sign' => $player->call_sign,
                ],
            ]);
    }

    /**
     * Test user cannot access another user's player
     */
    public function test_user_cannot_access_other_users_player(): void
    {
        $otherUser = User::factory()->create();
        $galaxy = Galaxy::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $otherUser->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$player->uuid}");

        $response->assertStatus(404);
    }

    /**
     * Test user can update player call sign
     */
    public function test_user_can_update_player_call_sign(): void
    {
        $galaxy = Galaxy::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $galaxy->id,
            'call_sign' => 'Old Name',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/players/{$player->uuid}", [
                'call_sign' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'call_sign' => 'New Name',
                ],
            ]);

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'call_sign' => 'New Name',
        ]);
    }

    /**
     * Test user can delete their player
     */
    public function test_user_can_delete_player(): void
    {
        $galaxy = Galaxy::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/players/{$player->uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('players', [
            'id' => $player->id,
        ]);
    }

    /**
     * Test user can get player status
     */
    public function test_user_can_get_player_status(): void
    {
        $galaxy = Galaxy::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$player->uuid}/status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'player' => ['uuid', 'call_sign', 'level', 'credits'],
                    'location',  // Can be null if player has no location
                ],
            ]);
    }

    /**
     * Test user can get player stats
     */
    public function test_user_can_get_player_stats(): void
    {
        $galaxy = Galaxy::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$player->uuid}/stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'player_info',
                    'economy',
                    'exploration',
                    'mirror_universe',
                ],
            ]);
    }

    /**
     * Test new player has no active ship
     */
    public function test_new_player_has_no_active_ship(): void
    {
        $galaxy = Galaxy::factory()->create();

        PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/players', [
                'galaxy_id' => $galaxy->id,
                'call_sign' => 'Shipless Wonder',
            ]);

        $response->assertStatus(201);

        $playerUuid = $response->json('data.uuid');

        // Verify status endpoint returns null ship
        $statusResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$playerUuid}/status");

        $statusResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'ship' => null,
                ],
            ]);
    }

    /**
     * Test new player prefers spawning at a super hub with all services
     */
    public function test_new_player_spawns_at_super_hub(): void
    {
        $galaxy = Galaxy::factory()->create();

        // Create an inhabited star WITHOUT full services (just a trading hub)
        $plainStar = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
        ]);

        TradingHub::factory()->create([
            'poi_id' => $plainStar->id,
            'is_active' => true,
            'has_salvage_yard' => false,
        ]);

        // Create a super hub: inhabited star with all services
        $superHubStar = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
        ]);

        $tradingHub = TradingHub::factory()->create([
            'poi_id' => $superHubStar->id,
            'is_active' => true,
            'has_salvage_yard' => true,
            'services' => ['shipyard', 'salvage', 'upgrades', 'plans', 'cartography'],
        ]);

        $ship = Ship::factory()->create();
        TradingHubShip::create([
            'trading_hub_id' => $tradingHub->id,
            'ship_id' => $ship->id,
            'galaxy_id' => $galaxy->id,
            'quantity' => 3,
            'current_price' => 5000,
            'demand_level' => 50,
            'supply_level' => 50,
        ]);

        StellarCartographer::create([
            'poi_id' => $superHubStar->id,
            'name' => 'Super Hub Charts',
            'is_active' => true,
            'chart_base_price' => 1000,
            'markup_multiplier' => 1.50,
        ]);

        // Create a second POI for the warp gate destination
        $destStar = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
        ]);

        WarpGate::factory()->visible()->create([
            'galaxy_id' => $galaxy->id,
            'source_poi_id' => $superHubStar->id,
            'destination_poi_id' => $destStar->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/players', [
                'galaxy_id' => $galaxy->id,
                'call_sign' => 'Super Hub Seeker',
            ]);

        $response->assertStatus(201);

        $player = Player::where('call_sign', 'Super Hub Seeker')->first();
        $this->assertEquals($superHubStar->id, $player->current_poi_id);

        // Verify the spawn system has a rich planetary system
        $planetCount = PointOfInterest::where('parent_poi_id', $superHubStar->id)
            ->whereIn('type', [
                PointOfInterestType::TERRESTRIAL,
                PointOfInterestType::SUPER_EARTH,
                PointOfInterestType::OCEAN,
                PointOfInterestType::LAVA,
                PointOfInterestType::GAS_GIANT,
                PointOfInterestType::ICE_GIANT,
                PointOfInterestType::HOT_JUPITER,
                PointOfInterestType::CHTHONIC,
                PointOfInterestType::DWARF_PLANET,
                PointOfInterestType::PLANET,
            ])
            ->count();
        $this->assertEquals(12, $planetCount, 'Spawn system should have exactly 12 planets');
    }

    /**
     * Test spawn upgrades best candidate to super hub when none exist naturally
     */
    public function test_spawn_upgrades_best_candidate_to_super_hub(): void
    {
        $galaxy = Galaxy::factory()->create();

        // Create an inhabited star with a trading hub but missing some services
        $candidateStar = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
        ]);

        TradingHub::factory()->create([
            'poi_id' => $candidateStar->id,
            'is_active' => true,
            'has_salvage_yard' => false,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/players', [
                'galaxy_id' => $galaxy->id,
                'call_sign' => 'Upgrade Test',
            ]);

        $response->assertStatus(201);

        $player = Player::where('call_sign', 'Upgrade Test')->first();
        $spawnPoi = PointOfInterest::find($player->current_poi_id);

        // Verify the spawn location was upgraded to have salvage yard
        $this->assertTrue($spawnPoi->tradingHub->fresh()->has_salvage_yard);
        // Verify a stellar cartographer was created
        $this->assertNotNull($spawnPoi->stellarCartographer);

        // Verify the spawn system has a rich planetary system
        $planetCount = PointOfInterest::where('parent_poi_id', $spawnPoi->id)
            ->whereIn('type', [
                PointOfInterestType::TERRESTRIAL,
                PointOfInterestType::SUPER_EARTH,
                PointOfInterestType::OCEAN,
                PointOfInterestType::LAVA,
                PointOfInterestType::GAS_GIANT,
                PointOfInterestType::ICE_GIANT,
                PointOfInterestType::HOT_JUPITER,
                PointOfInterestType::CHTHONIC,
                PointOfInterestType::DWARF_PLANET,
                PointOfInterestType::PLANET,
            ])
            ->count();
        $this->assertEquals(12, $planetCount, 'Spawn system should have exactly 12 planets');
    }

    /**
     * Test spawn system has a gas giant with at least 4 moons
     */
    public function test_spawn_system_has_gas_giant_with_moons(): void
    {
        $galaxy = Galaxy::factory()->create();

        $star = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
        ]);

        TradingHub::factory()->create([
            'poi_id' => $star->id,
            'is_active' => true,
            'has_salvage_yard' => false,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/players', [
                'galaxy_id' => $galaxy->id,
                'call_sign' => 'Moon Watcher',
            ]);

        $response->assertStatus(201);

        $player = Player::where('call_sign', 'Moon Watcher')->first();
        $spawnPoi = PointOfInterest::find($player->current_poi_id);

        // Verify at least one gas giant exists
        $gasGiant = PointOfInterest::where('parent_poi_id', $spawnPoi->id)
            ->where('type', PointOfInterestType::GAS_GIANT)
            ->first();
        $this->assertNotNull($gasGiant, 'Spawn system should have at least one gas giant');

        // Verify the gas giant has at least 4 moons
        $moonCount = PointOfInterest::where('parent_poi_id', $gasGiant->id)
            ->where('type', PointOfInterestType::MOON)
            ->count();
        $this->assertGreaterThanOrEqual(4, $moonCount, 'Gas giant should have at least 4 moons');
    }

    /**
     * Test spawn system has an asteroid belt
     */
    public function test_spawn_system_has_asteroid_belt(): void
    {
        $galaxy = Galaxy::factory()->create();

        $star = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
        ]);

        TradingHub::factory()->create([
            'poi_id' => $star->id,
            'is_active' => true,
            'has_salvage_yard' => false,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/players', [
                'galaxy_id' => $galaxy->id,
                'call_sign' => 'Belt Hunter',
            ]);

        $response->assertStatus(201);

        $player = Player::where('call_sign', 'Belt Hunter')->first();
        $spawnPoi = PointOfInterest::find($player->current_poi_id);

        // Verify asteroid belt exists
        $beltExists = PointOfInterest::where('parent_poi_id', $spawnPoi->id)
            ->where('type', PointOfInterestType::ASTEROID_BELT)
            ->exists();
        $this->assertTrue($beltExists, 'Spawn system should have an asteroid belt');
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\User;
use App\Models\WarpGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    private Galaxy $galaxy;

    private PointOfInterest $currentLocation;

    private PointOfInterest $destination;

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

        // Create current location
        $this->currentLocation = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'name' => 'Current System',
            'x' => 100,
            'y' => 100,
            'is_inhabited' => true,
        ]);

        // Create destination (close enough for direct jump with warp 1 = 5 unit max)
        $this->destination = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'name' => 'Destination System',
            'x' => 103,
            'y' => 103,
            'is_inhabited' => true,
        ]);

        // Create player
        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->currentLocation->id,
            'credits' => 10000,
            'experience' => 0,
            'level' => 1,
        ]);

        // Create ship blueprint
        $shipBlueprint = Ship::factory()->create([
            'class' => 'scout',
            'name' => 'Scout',
        ]);

        // Create player ship
        $this->ship = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $shipBlueprint->id,
            'name' => 'Test Ship',
            'current_fuel' => 100,
            'max_fuel' => 100,
            'hull' => 100,
            'max_hull' => 100,
            'weapons' => 10,
            'cargo_hold' => 100,
            'sensors' => 1,
            'warp_drive' => 1,
            'is_active' => true,
            'status' => 'operational',
        ]);
    }

    public function test_user_can_list_warp_gates_at_location(): void
    {
        // Create warp gates from current location
        $gate1 = WarpGate::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $this->currentLocation->id,
            'destination_poi_id' => $this->destination->id,
            'fuel_cost' => 25,
            'distance' => 141.42,
            'is_hidden' => false,
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/warp-gates/{$this->currentLocation->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'location',
                    'gate_count',
                    'gates' => [
                        '*' => ['uuid', 'destination', 'fuel_cost', 'distance'],
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'gate_count' => 1,
                ],
            ]);
    }

    public function test_list_warp_gates_returns_404_for_invalid_location(): void
    {
        $invalidUuid = \Illuminate\Support\Str::uuid();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/warp-gates/{$invalidUuid}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_can_travel_via_warp_gate(): void
    {
        // Ensure ship has fuel
        $this->ship->current_fuel = 100;
        $this->ship->save();

        // Create warp gate (distance 50 = ~50 fuel cost)
        $gate = WarpGate::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $this->currentLocation->id,
            'destination_poi_id' => $this->destination->id,
            'fuel_cost' => 50,
            'distance' => 50,
            'is_hidden' => false,
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/players/{$this->player->uuid}/travel/warp-gate", [
                'gate_uuid' => $gate->uuid,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'fuel_consumed',
                    'xp_earned',
                    'new_location',
                    'level_up',
                    'new_level',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Verify player moved
        $this->player->refresh();
        $this->assertEquals($this->destination->id, $this->player->current_poi_id);
    }

    public function test_warp_gate_travel_fails_with_insufficient_fuel(): void
    {
        // Set ship fuel to low
        $this->ship->update(['current_fuel' => 5]);

        // Create warp gate with high fuel cost
        $gate = WarpGate::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $this->currentLocation->id,
            'destination_poi_id' => $this->destination->id,
            'fuel_cost' => 50,
            'distance' => 141.42,
            'is_hidden' => false,
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/players/{$this->player->uuid}/travel/warp-gate", [
                'gate_uuid' => $gate->uuid,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_can_jump_to_coordinates(): void
    {
        // Ensure ship has fuel
        $this->ship->current_fuel = 100;
        $this->ship->save();

        // Jump a short distance (3 units diagonally = ~4.24 units, within 5 unit max)
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/players/{$this->player->uuid}/travel/coordinate", [
                'target_x' => 103,
                'target_y' => 103,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'fuel_consumed',
                    'xp_earned',
                    'new_location',
                    'level_up',
                    'new_level',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_coordinate_jump_fails_without_active_ship(): void
    {
        // Deactivate ship
        $this->ship->update(['is_active' => false]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/players/{$this->player->uuid}/travel/coordinate", [
                'target_x' => 150,
                'target_y' => 150,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_coordinate_jump_validates_input(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/players/{$this->player->uuid}/travel/coordinate", [
                'target_x' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'errors' => ['target_x', 'target_y'],
                ],
            ]);
    }

    public function test_user_can_direct_jump_to_trading_hub(): void
    {
        // Ensure ship has fuel
        $this->ship->current_fuel = 100;
        $this->ship->save();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/players/{$this->player->uuid}/travel/direct-jump", [
                'target_poi_uuid' => $this->destination->uuid,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'fuel_consumed',
                    'xp_earned',
                    'new_location',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_direct_jump_fails_for_invalid_poi(): void
    {
        $invalidUuid = \Illuminate\Support\Str::uuid();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/players/{$this->player->uuid}/travel/direct-jump", [
                'target_poi_uuid' => $invalidUuid,
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_can_preview_xp_for_distance(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/travel/xp-preview?distance=100');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'distance',
                    'xp_earned',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'distance' => 100,
                ],
            ]);

        // XP should be calculated as distance * 5 (minimum 10)
        $this->assertEquals(500, $response->json('data.xp_earned'));
    }

    public function test_xp_preview_validates_distance(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/travel/xp-preview?distance=-10');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'errors' => ['distance'],
                ],
            ]);
    }

    public function test_user_can_calculate_fuel_cost(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/travel/fuel-cost?ship_uuid={$this->ship->uuid}&poi_uuid={$this->destination->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'from' => ['uuid', 'name', 'x', 'y'],
                    'to' => ['uuid', 'name', 'x', 'y'],
                    'distance',
                    'ship' => ['current_fuel', 'max_fuel', 'warp_drive'],
                    'warp_gate',
                    'direct_jump' => ['distance', 'fuel_cost', 'can_afford', 'in_range', 'max_range'],
                    'cheapest_option',
                    'cheapest_fuel_cost',
                    'can_reach',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'from' => ['uuid' => $this->currentLocation->uuid],
                    'to' => ['uuid' => $this->destination->uuid],
                    'ship' => ['warp_drive' => 1],
                ],
            ]);
    }

    public function test_fuel_cost_calculation_fails_with_invalid_ship(): void
    {
        $invalidUuid = \Illuminate\Support\Str::uuid();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/travel/fuel-cost?ship_uuid={$invalidUuid}&poi_uuid={$this->destination->uuid}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_fuel_cost_validates_input(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/travel/fuel-cost?ship_uuid={$this->ship->uuid}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_travel_endpoints_require_authentication(): void
    {
        $endpoints = [
            ['GET', "/api/warp-gates/{$this->currentLocation->uuid}"],
            ['POST', "/api/players/{$this->player->uuid}/travel/warp-gate"],
            ['POST', "/api/players/{$this->player->uuid}/travel/coordinate"],
            ['POST', "/api/players/{$this->player->uuid}/travel/direct-jump"],
            ['GET', '/api/travel/xp-preview?distance=100'],
            ['GET', "/api/travel/fuel-cost?ship_uuid={$this->ship->uuid}&poi_uuid={$this->destination->uuid}"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $response->assertStatus(401);
        }
    }

    public function test_user_cannot_access_other_users_players_for_travel(): void
    {
        $otherUser = User::factory()->create();
        $otherPlayer = Player::factory()->create([
            'user_id' => $otherUser->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->currentLocation->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/players/{$otherPlayer->uuid}/travel/coordinate", [
                'target_x' => 150,
                'target_y' => 150,
            ]);

        $response->assertStatus(404);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpgradeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    private Galaxy $galaxy;

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

        // Create location
        $location = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'x' => 100,
            'y' => 100,
        ]);

        // Create player
        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $location->id,
            'credits' => 50000,
            'experience' => 0,
            'level' => 1,
        ]);

        // Create ship
        $shipBlueprint = Ship::factory()->create([
            'class' => 'scout',
            'name' => 'Scout',
            'hull_strength' => 100,
            'cargo_capacity' => 100,
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
            'cargo_hold' => 100,
            'sensors' => 1,
            'warp_drive' => 1,
            'is_active' => true,
            'status' => 'operational',
        ]);
    }

    public function test_user_can_list_upgrade_options(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/ships/{$this->ship->uuid}/upgrade-options");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'ship_uuid',
                    'ship_name',
                    'player_credits',
                    'components' => [
                        'max_fuel' => [
                            'current_value',
                            'current_level',
                            'max_level',
                            'can_upgrade',
                            'upgrade_cost',
                        ],
                        'max_hull',
                        'weapons',
                        'cargo_hold',
                        'sensors',
                        'warp_drive',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_user_can_get_component_upgrade_details(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/ships/{$this->ship->uuid}/upgrade/weapons");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'component',
                    'current_value',
                    'current_level',
                    'max_level',
                    'can_upgrade',
                    'upgrade_cost',
                    'next_value',
                    'increment',
                    'player_credits',
                    'can_afford',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'component' => 'weapons',
                    'current_value' => 10,
                ],
            ]);
    }

    public function test_component_details_fails_for_invalid_component(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/ships/{$this->ship->uuid}/upgrade/invalid_component");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_can_execute_upgrade(): void
    {
        $oldWeapons = $this->ship->weapons;

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/ships/{$this->ship->uuid}/upgrade/weapons");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'component',
                    'old_value',
                    'new_value',
                    'new_level',
                    'cost',
                    'credits_remaining',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'component' => 'weapons',
                    'old_value' => $oldWeapons,
                    'new_value' => $oldWeapons + 5,
                ],
            ]);

        // Verify upgrade was applied
        $this->ship->refresh();
        $this->assertEquals($oldWeapons + 5, $this->ship->weapons);

        // Verify credits were deducted
        $this->player->refresh();
        $this->assertLessThan(50000, $this->player->credits);
    }

    public function test_upgrade_fails_with_insufficient_credits(): void
    {
        $this->player->update(['credits' => 10]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/ships/{$this->ship->uuid}/upgrade/weapons");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_upgrade_fails_for_invalid_component(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/ships/{$this->ship->uuid}/upgrade/invalid_component");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_can_upgrade_multiple_components(): void
    {
        // Upgrade weapons
        $response1 = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/ships/{$this->ship->uuid}/upgrade/weapons");
        $response1->assertStatus(200);

        // Upgrade sensors
        $response2 = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/ships/{$this->ship->uuid}/upgrade/sensors");
        $response2->assertStatus(200);

        // Verify both upgrades
        $this->ship->refresh();
        $this->assertEquals(15, $this->ship->weapons); // 10 + 5
        $this->assertEquals(2, $this->ship->sensors); // 1 + 1
    }

    public function test_upgrading_max_hull_also_increases_current_hull(): void
    {
        $oldMaxHull = $this->ship->max_hull;
        $oldHull = $this->ship->hull;

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/ships/{$this->ship->uuid}/upgrade/max_hull");

        $response->assertStatus(200);

        $this->ship->refresh();
        $this->assertEquals($oldMaxHull + 10, $this->ship->max_hull);
        $this->assertEquals($oldHull + 10, $this->ship->hull);
    }

    public function test_user_can_get_owned_plans(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$this->player->uuid}/plans");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'player_uuid',
                    'plans_by_component',
                    'total_plans',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_user_can_get_upgrade_cost_formulas(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/upgrade-costs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'formula',
                    'base_costs' => [
                        'max_fuel',
                        'max_hull',
                        'weapons',
                        'cargo_hold',
                        'sensors',
                        'warp_drive',
                    ],
                    'increments',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_user_can_get_upgrade_limits(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/upgrade-limits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'base_max_levels' => [
                        'max_fuel',
                        'max_hull',
                        'weapons',
                        'cargo_hold',
                        'sensors',
                        'warp_drive',
                    ],
                    'note',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_upgrade_endpoints_require_authentication(): void
    {
        $endpoints = [
            ['GET', "/api/ships/{$this->ship->uuid}/upgrade-options"],
            ['GET', "/api/ships/{$this->ship->uuid}/upgrade/weapons"],
            ['POST', "/api/ships/{$this->ship->uuid}/upgrade/weapons"],
            ['GET', "/api/players/{$this->player->uuid}/plans"],
            ['GET', '/api/upgrade-costs'],
            ['GET', '/api/upgrade-limits'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $response->assertStatus(401);
        }
    }

    public function test_user_cannot_access_other_users_ship_upgrades(): void
    {
        $otherUser = User::factory()->create();
        $otherPlayer = Player::factory()->create([
            'user_id' => $otherUser->id,
            'galaxy_id' => $this->galaxy->id,
        ]);

        $otherShip = PlayerShip::factory()->create([
            'player_id' => $otherPlayer->id,
            'ship_id' => $this->ship->ship_id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/ships/{$otherShip->uuid}/upgrade/weapons");

        $response->assertStatus(404);
    }

    public function test_upgrade_cost_increases_with_level(): void
    {
        // Get initial upgrade cost
        $response1 = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/ships/{$this->ship->uuid}/upgrade/weapons");
        $firstCost = $response1->json('data.upgrade_cost');

        // Perform upgrade
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/ships/{$this->ship->uuid}/upgrade/weapons");

        // Get second upgrade cost
        $response2 = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/ships/{$this->ship->uuid}/upgrade/weapons");
        $secondCost = $response2->json('data.upgrade_cost');

        // Second upgrade should cost more
        $this->assertGreaterThan($firstCost, $secondCost);
    }
}

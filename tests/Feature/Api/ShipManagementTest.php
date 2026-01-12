<?php

namespace Tests\Feature\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    private Player $player;

    private PlayerShip $ship;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with player and ship
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $galaxy = Galaxy::factory()->create();
        $location = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
        ]);

        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $location->id,
        ]);

        $shipBlueprint = Ship::factory()->create([
            'class' => 'scout',
        ]);

        $this->ship = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $shipBlueprint->id,
            'is_active' => true,
            'current_fuel' => 50,
            'max_fuel' => 100,
            'hull' => 80,
            'max_hull' => 100,
            'weapons' => 10,
            'cargo_hold' => 100,
            'sensors' => 2,
            'warp_drive' => 2,
            'status' => 'operational',
        ]);
    }

    /**
     * Test user can get their active ship
     */
    public function test_user_can_get_active_ship(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$this->player->uuid}/ship");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'uuid', 'name', 'current_fuel', 'max_fuel', 'hull'],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'uuid' => $this->ship->uuid,
                ],
            ]);
    }

    /**
     * Test user can get ship status
     */
    public function test_user_can_get_ship_status(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/ships/{$this->ship->uuid}/status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'uuid',
                    'name',
                    'ship_class',
                    'status',
                    'hull' => ['current', 'max', 'percentage', 'is_damaged'],
                    'fuel' => ['current', 'max', 'percentage', 'time_to_full'],
                    'cargo' => ['current', 'capacity', 'available_space'],
                    'components' => ['weapons', 'sensors', 'warp_drive'],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'uuid' => $this->ship->uuid,
                    'hull' => [
                        'current' => 80,
                        'max' => 100,
                    ],
                ],
            ]);
    }

    /**
     * Test user can get fuel status
     */
    public function test_user_can_get_fuel_status(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/ships/{$this->ship->uuid}/fuel");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_fuel',
                    'max_fuel',
                    'fuel_percentage',
                    'regen_rate_per_hour',
                    'seconds_to_full',
                    'last_updated',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'max_fuel' => 100,
                ],
            ]);
    }

    /**
     * Test user can regenerate fuel
     */
    public function test_user_can_regenerate_fuel(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/ships/{$this->ship->uuid}/regenerate-fuel");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'fuel_before',
                    'fuel_after',
                    'fuel_regenerated',
                    'max_fuel',
                    'is_full',
                    'time_to_full',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * Test user can get ship upgrades info
     */
    public function test_user_can_get_ship_upgrades(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/ships/{$this->ship->uuid}/upgrades");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'ship_uuid',
                    'ship_name',
                    'upgrades' => [
                        'max_fuel',
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

        // Verify each component has required fields
        $upgrades = $response->json('data.upgrades');
        foreach ($upgrades as $component => $info) {
            $this->assertArrayHasKey('current_value', $info);
            $this->assertArrayHasKey('max_level', $info);
            $this->assertArrayHasKey('bonus_from_plans', $info);
            $this->assertArrayHasKey('can_upgrade', $info);
        }
    }

    /**
     * Test user can get damage assessment
     */
    public function test_user_can_get_damage_assessment(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/ships/{$this->ship->uuid}/damage");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'ship_uuid',
                    'hull' => ['current', 'max', 'damage', 'percentage'],
                    'status',
                    'assessment',
                    'needs_repair',
                    'repair_cost_estimate',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'hull' => [
                        'current' => 80,
                        'max' => 100,
                        'damage' => 20,
                    ],
                    'needs_repair' => true,
                ],
            ]);
    }

    /**
     * Test user can rename their ship
     */
    public function test_user_can_rename_ship(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/ships/{$this->ship->uuid}/name", [
                'name' => 'USS Enterprise',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'uuid', 'name'],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'USS Enterprise',
                ],
            ]);

        $this->assertDatabaseHas('player_ships', [
            'id' => $this->ship->id,
            'name' => 'USS Enterprise',
        ]);
    }

    /**
     * Test rename fails with invalid data
     */
    public function test_rename_fails_with_invalid_data(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/ships/{$this->ship->uuid}/name", [
                'name' => '', // Empty name
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    /**
     * Test user cannot access another user's ship
     */
    public function test_user_cannot_access_other_users_ship(): void
    {
        $otherUser = User::factory()->create();
        $otherGalaxy = Galaxy::factory()->create();
        $otherPlayer = Player::factory()->create([
            'user_id' => $otherUser->id,
            'galaxy_id' => $otherGalaxy->id,
        ]);
        $otherShip = PlayerShip::factory()->create([
            'player_id' => $otherPlayer->id,
            'ship_id' => $this->ship->ship_id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/ships/{$otherShip->uuid}/status");

        $response->assertStatus(404);
    }
}

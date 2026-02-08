<?php

namespace Tests\Feature\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\TradingHub;
use App\Models\User;
use App\Models\WarpGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    private Player $player;

    private PlayerShip $ship;

    private PointOfInterest $currentLocation;

    private Galaxy $galaxy;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with player and ship
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $this->galaxy = Galaxy::factory()->create();
        $this->currentLocation = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
            'x' => 500,
            'y' => 500,
        ]);

        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->currentLocation->id,
        ]);

        $shipBlueprint = Ship::factory()->create();

        $this->ship = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $shipBlueprint->id,
            'is_active' => true,
            'sensors' => 3, // 300 unit range
        ]);
    }

    /**
     * Test user can get current location details
     */
    public function test_user_can_get_current_location(): void
    {
        // Create some warp gates
        $destination = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
        ]);

        WarpGate::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $this->currentLocation->id,
            'destination_poi_id' => $destination->id,
            'fuel_cost' => 10,
            'distance' => 100,
            'is_hidden' => false,
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$this->player->uuid}/location");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'location' => ['uuid', 'name', 'type', 'x', 'y', 'is_inhabited'],
                    'galaxy' => ['uuid', 'name'],
                    'warp_gates_available',
                    'trading_hub',
                    'is_inhabited',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'location' => [
                        'uuid' => $this->currentLocation->uuid,
                    ],
                    'warp_gates_available' => 1,
                    'is_inhabited' => true,
                ],
            ]);
    }

    /**
     * Test location shows trading hub if present
     */
    public function test_location_shows_trading_hub_if_present(): void
    {
        // Create trading hub at current location
        $tradingHub = TradingHub::factory()->create([
            'poi_id' => $this->currentLocation->id,
            'name' => 'Central Trading Hub',
            'type' => 'standard',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$this->player->uuid}/location");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'trading_hub' => [
                        'uuid' => $tradingHub->uuid,
                        'name' => 'Central Trading Hub',
                        'type' => 'standard',
                    ],
                ],
            ]);
    }

    /**
     * Test user can get nearby systems within sensor range
     */
    public function test_user_can_get_nearby_systems(): void
    {
        // Create systems within sensor range (300 units with sensor level 3)
        $nearbySystem1 = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'x' => 600, // 100 units away
            'y' => 500,
            'name' => 'Nearby System 1',
        ]);

        $nearbySystem2 = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'x' => 500,
            'y' => 700, // 200 units away
            'name' => 'Nearby System 2',
        ]);

        // Create system outside sensor range
        PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'x' => 1000, // 500 units away - outside range
            'y' => 1000,
            'name' => 'Far System',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$this->player->uuid}/nearby-systems");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_location',
                    'sensor_range',
                    'sensor_level',
                    'systems_detected',
                    'nearby_systems',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'sensor_range' => 300,
                    'sensor_level' => 3,
                    'systems_detected' => 2,
                ],
            ]);

        // Verify the nearby systems are in the response
        $systems = $response->json('data.nearby_systems');
        $this->assertCount(2, $systems);
    }

    /**
     * Test nearby systems respects sensor range
     */
    public function test_nearby_systems_respects_sensor_range(): void
    {
        // Update ship to have sensor level 1 (100 unit range)
        $this->ship->update(['sensors' => 1]);

        // Create system just outside range
        PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'x' => 610, // 110 units away - outside 100 unit range
            'y' => 500,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$this->player->uuid}/nearby-systems");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'sensor_range' => 100,
                    'sensor_level' => 1,
                    'systems_detected' => 0,
                ],
            ]);
    }

    /**
     * Test user can perform local scan
     */
    public function test_user_can_scan_local_area(): void
    {
        // Create various POI types within sensor range
        PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'x' => 550,
            'y' => 500,
        ]);

        PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::PLANET,
            'x' => 500,
            'y' => 550,
        ]);

        PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::ASTEROID_BELT,
            'x' => 550,
            'y' => 550,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$this->player->uuid}/scan-local");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_location',
                    'sensor_range',
                    'sensor_level',
                    'total_pois_detected',
                    'pois_by_type',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'sensor_range' => 300,
                    'sensor_level' => 3,
                ],
            ]);

        // Verify POIs are grouped by type
        $poisByType = $response->json('data.pois_by_type');
        $this->assertIsArray($poisByType);
        $this->assertGreaterThan(0, count($poisByType));
    }

    /**
     * Test navigation fails without active ship
     */
    public function test_navigation_fails_without_active_ship(): void
    {
        // Deactivate the ship
        $this->ship->update(['is_active' => false]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$this->player->uuid}/nearby-systems");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_SHIP',
                ],
            ]);
    }

    /**
     * Test user cannot access another user's player navigation
     */
    public function test_user_cannot_access_other_users_navigation(): void
    {
        $otherUser = User::factory()->create();
        $otherGalaxy = Galaxy::factory()->create();
        $otherLocation = PointOfInterest::factory()->create([
            'galaxy_id' => $otherGalaxy->id,
            'type' => PointOfInterestType::STAR,
        ]);
        $otherPlayer = Player::factory()->create([
            'user_id' => $otherUser->id,
            'galaxy_id' => $otherGalaxy->id,
            'current_poi_id' => $otherLocation->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$otherPlayer->uuid}/location");

        $response->assertStatus(404);
    }

    /**
     * Test user can get local bodies (planets, moons, etc.) at current location
     */
    public function test_user_can_get_local_bodies(): void
    {
        // Create planetary bodies orbiting the current star
        $planet1 = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::TERRESTRIAL,
            'parent_poi_id' => $this->currentLocation->id,
            'name' => 'Planet Alpha',
            'orbital_index' => 1,
            'is_inhabited' => true,
        ]);

        $planet2 = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::GAS_GIANT,
            'parent_poi_id' => $this->currentLocation->id,
            'name' => 'Planet Beta',
            'orbital_index' => 3,
            'is_inhabited' => false,
        ]);

        // Create a moon orbiting the gas giant
        PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::MOON,
            'parent_poi_id' => $planet2->id,
            'name' => 'Moon Beta-1',
            'orbital_index' => 1,
        ]);

        // Create an asteroid belt
        PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::ASTEROID_BELT,
            'parent_poi_id' => $this->currentLocation->id,
            'name' => 'Inner Asteroid Belt',
            'orbital_index' => 2,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$this->player->uuid}/local-bodies");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'system' => ['uuid', 'name', 'type', 'coordinates', 'is_inhabited'],
                    'sector',
                    'bodies' => [
                        'planets',
                        'moons',
                        'asteroid_belts',
                        'stations',
                        'other',
                    ],
                    'summary' => [
                        'total_bodies',
                        'planets',
                        'moons',
                        'asteroid_belts',
                        'stations',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'system' => [
                        'uuid' => $this->currentLocation->uuid,
                    ],
                    'summary' => [
                        'total_bodies' => 3, // 2 planets + 1 asteroid belt (moon is child of planet)
                        'planets' => 2,
                        'moons' => 0, // Direct children only, moon is child of planet
                        'asteroid_belts' => 1,
                    ],
                ],
            ]);

        // Verify planet data structure
        $planets = $response->json('data.bodies.planets');
        $this->assertCount(2, $planets);
        $this->assertEquals('Planet Alpha', $planets[0]['name']); // Ordered by orbital_distance
        $this->assertEquals('Planet Beta', $planets[1]['name']);
        $this->assertTrue($planets[0]['is_inhabited']);
        $this->assertFalse($planets[1]['is_inhabited']);

        // Verify gas giant has moons listed
        $this->assertArrayHasKey('moons', $planets[1]);
        $this->assertCount(1, $planets[1]['moons']);
        $this->assertEquals('Moon Beta-1', $planets[1]['moons'][0]['name']);
    }

    /**
     * Test local bodies returns empty when at uninhabited star with no planets
     */
    public function test_local_bodies_returns_empty_for_star_without_planets(): void
    {
        // Current location already has no children by default
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$this->player->uuid}/local-bodies");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_bodies' => 0,
                        'planets' => 0,
                        'moons' => 0,
                        'asteroid_belts' => 0,
                        'stations' => 0,
                    ],
                ],
            ]);
    }
}

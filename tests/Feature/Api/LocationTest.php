<?php

namespace Tests\Feature\Api;

use App\Enums\Galaxy\GalaxyStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\Sector;
use App\Models\TradingHub;
use App\Models\User;
use App\Models\WarpGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Location API Tests
 *
 * Tests for POST /api/location/current
 */
class LocationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Galaxy $galaxy;

    private Player $player;

    private PointOfInterest $poi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->galaxy = Galaxy::factory()->create([
            'status' => GalaxyStatus::ACTIVE,
            'owner_user_id' => $this->user->id,
        ]);

        $this->poi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'name' => 'Alpha Centauri',
            'x' => 100,
            'y' => 100,
            'is_inhabited' => true,
            'type' => PointOfInterestType::STAR,
        ]);

        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->poi->id,
        ]);
    }

    public function test_location_requires_authentication(): void
    {
        $response = $this->postJson('/api/location/current');
        $response->assertStatus(401);
    }

    public function test_location_returns_empty_space_for_unknown_coordinates(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/location/current?x=999&y=999');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.location', 'empty_space')
            ->assertJsonPath('data.message', 'User is in empty space');
    }

    public function test_location_returns_system_info_by_coordinates(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/location/current?x=100&y=100');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.location', 'star_system')
            ->assertJsonPath('data.system_name', 'Alpha Centauri')
            ->assertJsonPath('data.coordinates.x', 100)
            ->assertJsonPath('data.coordinates.y', 100);
    }

    public function test_location_returns_system_info_by_uuid(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/location/current/{$this->poi->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.location', 'star_system')
            ->assertJsonPath('data.system_name', 'Alpha Centauri');
    }

    public function test_location_returns_404_for_unknown_uuid(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/location/current/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    public function test_location_requires_coordinates_or_uuid(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/location/current');

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'MISSING_PARAMETERS');
    }

    public function test_location_shows_unknown_for_unscanned_system(): void
    {
        // Create uninhabited, unscanned system
        $unknownPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'name' => 'Unknown System',
            'x' => 500,
            'y' => 500,
            'is_inhabited' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/location/current/{$unknownPoi->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.knowledge_level', 'unknown')
            ->assertJsonPath('data.inhabited', 'unknown')
            ->assertJsonPath('data.planets', 'unknown');
    }

    public function test_location_shows_details_for_inhabited_system(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/location/current/{$this->poi->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.location', 'star_system')
            ->assertJsonPath('data.inhabited.is_inhabited', true)
            ->assertJsonStructure([
                'data' => [
                    'location',
                    'system_name',
                    'system_uuid',
                    'coordinates',
                    'inhabited',
                    'has',
                ],
            ]);
    }

    public function test_location_shows_gates_for_inhabited_system(): void
    {
        // Create destination POI
        $destination = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'name' => 'Beta Centauri',
            'is_inhabited' => true,
        ]);

        // Create warp gate
        WarpGate::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $this->poi->id,
            'destination_poi_id' => $destination->id,
            'is_hidden' => false,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/location/current/{$this->poi->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.has.gate_count', 1);
    }

    public function test_location_shows_services_for_trading_hub(): void
    {
        // Create trading hub at POI
        TradingHub::factory()->create([
            'poi_id' => $this->poi->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/location/current/{$this->poi->uuid}");

        $response->assertStatus(200);
        $services = $response->json('data.has.services');
        $this->assertContains('trading_hub', $services);
    }

    public function test_location_shows_current_location_flag(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/location/current/{$this->poi->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_current_location', true);

        // Create different POI
        $otherPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'is_inhabited' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/location/current/{$otherPoi->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_current_location', false);
    }

    public function test_location_includes_sector_info(): void
    {
        // Create a sector that contains the POI
        $sector = Sector::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'name' => 'Alpha Quadrant',
            'grid_x' => 1,
            'grid_y' => 1,
            'x_min' => 0,
            'x_max' => 200,
            'y_min' => 0,
            'y_max' => 200,
        ]);

        // Update POI to be in the sector
        $this->poi->sector_id = $sector->id;
        $this->poi->save();

        $response = $this->actingAs($this->user)
            ->postJson("/api/location/current/{$this->poi->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'sector' => [
                        'uuid',
                        'name',
                        'grid',
                        'bounds',
                        'danger_level',
                        'display_name',
                    ],
                ],
            ])
            ->assertJsonPath('data.sector.name', 'Alpha Quadrant')
            ->assertJsonPath('data.sector.grid.x', 1)
            ->assertJsonPath('data.sector.grid.y', 1);
    }

    public function test_location_finds_sector_by_coordinates(): void
    {
        // Create a sector that covers coordinates
        Sector::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'name' => 'Beta Quadrant',
            'grid_x' => 5,
            'grid_y' => 5,
            'x_min' => 400,
            'x_max' => 600,
            'y_min' => 400,
            'y_max' => 600,
        ]);

        // Query empty space within the sector bounds
        $response = $this->actingAs($this->user)
            ->postJson('/api/location/current?x=500&y=500');

        $response->assertStatus(200)
            ->assertJsonPath('data.location', 'empty_space')
            ->assertJsonPath('data.sector.name', 'Beta Quadrant')
            ->assertJsonPath('data.sector.grid.x', 5);
    }
}

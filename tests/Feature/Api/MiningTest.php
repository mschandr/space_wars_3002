<?php

namespace Tests\Feature\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Colony;
use App\Models\ColonyBuilding;
use App\Models\Galaxy;
use App\Models\Mineral;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiningTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    private PointOfInterest $asteroid;

    private Mineral $mineral;

    private PlayerShip $playerShip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $galaxy = Galaxy::factory()->create();

        $this->asteroid = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::ASTEROID,
            'planet_class' => 'rocky',
            'mineral_deposits' => [
                'Iron' => [
                    'size' => 5000,
                    'richness' => 'abundant',
                ],
            ],
        ]);

        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $this->asteroid->id,
            'credits' => 50000,
        ]);

        $this->mineral = Mineral::factory()->create([
            'name' => 'Iron',
            'rarity' => 'common',
            'attributes' => [
                'found_in' => ['asteroid', 'rocky'],
            ],
        ]);

        $ship = Ship::factory()->create();
        $this->playerShip = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $ship->id,
            'is_active' => true,
            'sensors' => 5,
            'cargo_hold' => 100,
            'current_cargo' => 0,
        ]);
    }

    public function test_it_gets_mining_opportunities()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/poi/{$this->asteroid->uuid}/mining-opportunities");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'has_deposits',
                    'minerals',
                    'poi_type',
                ],
            ])
            ->assertJson([
                'data' => [
                    'has_deposits' => true,
                ],
            ]);

        $this->assertNotEmpty($response->json('data.minerals'));
    }

    public function test_it_returns_no_deposits_for_empty_poi()
    {
        $emptyPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->player->galaxy_id,
            'type' => PointOfInterestType::STAR,
            'mineral_deposits' => null,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/poi/{$emptyPoi->uuid}/mining-opportunities");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'has_deposits' => false,
                    'minerals' => [],
                ],
            ]);
    }

    public function test_it_extracts_resources_manually()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/ships/{$this->playerShip->uuid}/mining/extract", [
                'poi_uuid' => $this->asteroid->uuid,
                'mineral_id' => $this->mineral->id,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'mineral_name',
                    'amount_extracted',
                    'efficiency_percent',
                    'sensor_level',
                    'cargo_used',
                    'cargo_remaining',
                ],
            ]);

        $this->assertGreaterThan(0, $response->json('data.amount_extracted'));
    }

    public function test_it_fails_extraction_when_not_at_location()
    {
        $otherPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->player->galaxy_id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/ships/{$this->playerShip->uuid}/mining/extract", [
                'poi_uuid' => $otherPoi->uuid,
                'mineral_id' => $this->mineral->id,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Your ship must be at the target location',
                ],
            ]);
    }

    public function test_it_fails_extraction_with_full_cargo()
    {
        $this->playerShip->update([
            'cargo_hold' => 10,
            'current_cargo' => 10,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/ships/{$this->playerShip->uuid}/mining/extract", [
                'poi_uuid' => $this->asteroid->uuid,
                'mineral_id' => $this->mineral->id,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'No cargo space available',
                ],
            ]);
    }

    public function test_it_starts_automated_mining()
    {
        $planet = PointOfInterest::factory()->create([
            'galaxy_id' => $this->player->galaxy_id,
            'type' => PointOfInterestType::PLANET,
        ]);

        $colony = Colony::factory()->create([
            'player_id' => $this->player->id,
            'poi_id' => $planet->id,
        ]);

        // Create orbital mining facility
        ColonyBuilding::factory()->create([
            'colony_id' => $colony->id,
            'building_type' => 'orbital_mining',
            'status' => 'operational',
            'effects' => ['mineral_production' => 100],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/colonies/{$colony->uuid}/mining/start", [
                'poi_uuid' => $this->asteroid->uuid,
                'mineral_id' => $this->mineral->id,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'mineral_name',
                    'production_per_cycle',
                    'sensor_efficiency',
                ],
            ]);
    }

    public function test_it_fails_automated_mining_without_facility()
    {
        $planet = PointOfInterest::factory()->create([
            'galaxy_id' => $this->player->galaxy_id,
            'type' => PointOfInterestType::PLANET,
        ]);

        $colony = Colony::factory()->create([
            'player_id' => $this->player->id,
            'poi_id' => $planet->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/colonies/{$colony->uuid}/mining/start", [
                'poi_uuid' => $this->asteroid->uuid,
                'mineral_id' => $this->mineral->id,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Colony does not have an operational orbital mining facility',
                ],
            ]);
    }

    public function test_it_requires_authentication()
    {
        $response = $this->getJson("/api/poi/{$this->asteroid->uuid}/mining-opportunities");

        $response->assertUnauthorized();
    }

    public function test_it_authorizes_ship_owner()
    {
        $otherUser = User::factory()->create();
        $otherPlayer = Player::factory()->create([
            'user_id' => $otherUser->id,
            'galaxy_id' => $this->player->galaxy_id,
        ]);

        $response = $this->actingAs($otherUser, 'sanctum')
            ->postJson("/api/ships/{$this->playerShip->uuid}/mining/extract", [
                'poi_uuid' => $this->asteroid->uuid,
                'mineral_id' => $this->mineral->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_it_validates_required_fields()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/ships/{$this->playerShip->uuid}/mining/extract", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['poi_uuid', 'mineral_id']);
    }
}

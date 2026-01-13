<?php

namespace Tests\Feature\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Colony;
use App\Models\ColonyBuilding;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColonyBuildingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Player $player;
    private Colony $colony;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $galaxy = Galaxy::factory()->create();
        $planet = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::PLANET,
        ]);

        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $galaxy->id,
            'credits' => 100000,
        ]);

        $this->colony = Colony::factory()->create([
            'player_id' => $this->player->id,
            'poi_id' => $planet->id,
            'development_level' => 2,
            'mineral_storage' => 10000,
        ]);
    }

    public function test_it_lists_colony_buildings()
    {
        ColonyBuilding::factory()->count(3)->create([
            'colony_id' => $this->colony->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/colonies/{$this->colony->uuid}/buildings");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'buildings',
                    'total_count',
                    'max_buildings',
                    'can_build_more',
                ],
            ]);

        $this->assertEquals(3, $response->json('data.total_count'));
    }

    public function test_it_constructs_new_building()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/colonies/{$this->colony->uuid}/buildings", [
                'building_type' => 'hydroponics',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'building' => [
                        'uuid',
                        'building_type',
                        'level',
                    ],
                    'cost_paid',
                ],
            ]);

        $this->assertDatabaseHas('colony_buildings', [
            'colony_id' => $this->colony->id,
            'building_type' => 'hydroponics',
        ]);
    }

    public function test_it_fails_to_construct_when_at_building_limit()
    {
        // Fill up to max buildings (development_level * 2 = 4)
        ColonyBuilding::factory()->count(4)->create([
            'colony_id' => $this->colony->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/colonies/{$this->colony->uuid}/buildings", [
                'building_type' => 'hydroponics',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Colony cannot build more buildings at current development level',
                ],
            ]);
    }

    public function test_it_fails_to_construct_with_insufficient_credits()
    {
        $this->player->update(['credits' => 100]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/colonies/{$this->colony->uuid}/buildings", [
                'building_type' => 'shipyard', // Expensive building
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Insufficient credits',
                ],
            ]);
    }

    public function test_it_upgrades_building()
    {
        $building = ColonyBuilding::factory()->create([
            'colony_id' => $this->colony->id,
            'level' => 1,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/colonies/{$this->colony->uuid}/buildings/{$building->uuid}", [
                'action' => 'upgrade',
            ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'new_level' => 2,
                ],
            ]);

        $building->refresh();
        $this->assertEquals(2, $building->level);
    }

    public function test_it_fails_to_upgrade_max_level_building()
    {
        $building = ColonyBuilding::factory()->create([
            'colony_id' => $this->colony->id,
            'level' => 10,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/colonies/{$this->colony->uuid}/buildings/{$building->uuid}", [
                'action' => 'upgrade',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Building is already at maximum level',
                ],
            ]);
    }

    public function test_it_repairs_damaged_building()
    {
        $building = ColonyBuilding::factory()->create([
            'colony_id' => $this->colony->id,
            'status' => 'damaged',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/colonies/{$this->colony->uuid}/buildings/{$building->uuid}", [
                'action' => 'repair',
            ]);

        $response->assertOk();

        $building->refresh();
        $this->assertEquals('operational', $building->status);
    }

    public function test_it_demolishes_building()
    {
        $building = ColonyBuilding::factory()->create([
            'colony_id' => $this->colony->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/colonies/{$this->colony->uuid}/buildings/{$building->uuid}");

        $response->assertOk();

        $this->assertDatabaseMissing('colony_buildings', [
            'id' => $building->id,
        ]);
    }

    public function test_it_validates_building_type()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/colonies/{$this->colony->uuid}/buildings", [
                'building_type' => 'invalid_type',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['building_type']);
    }

    public function test_it_requires_authentication()
    {
        $response = $this->getJson("/api/colonies/{$this->colony->uuid}/buildings");

        $response->assertUnauthorized();
    }

    public function test_it_authorizes_colony_owner()
    {
        $galaxy = $this->colony->player->galaxy;
        $otherUser = User::factory()->create();
        $otherPlayer = Player::factory()->create([
            'user_id' => $otherUser->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $response = $this->actingAs($otherUser, 'sanctum')
            ->postJson("/api/colonies/{$this->colony->uuid}/buildings", [
                'building_type' => 'hydroponics',
            ]);

        $response->assertStatus(403);
    }
}

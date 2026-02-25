<?php

namespace Tests\Feature\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Colony;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColonyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    private Galaxy $galaxy;

    private PointOfInterest $planet;

    private PlayerShip $playerShip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->galaxy = Galaxy::factory()->create();
        $this->planet = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::PLANET,
            'planet_class' => 'terrestrial',
            'habitability_score' => 0.8,
        ]);

        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->planet->id,
            'credits' => 50000,
        ]);

        $ship = Ship::factory()->create();
        $this->playerShip = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $ship->id,
            'is_active' => true,
        ]);
    }

    public function test_it_lists_player_colonies()
    {
        Colony::factory()->count(3)->create(['player_id' => $this->player->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/players/{$this->player->uuid}/colonies");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'colonies',
                    'total_count',
                ],
            ]);

        $this->assertEquals(3, $response->json('data.total_count'));
    }

    public function test_it_establishes_new_colony()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/colonies", [
                'poi_uuid' => $this->planet->uuid,
                'name' => 'New Hope Colony',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'colony' => [
                        'uuid',
                        'name',
                        'population',
                        'development_level',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('colonies', [
            'player_id' => $this->player->id,
            'poi_id' => $this->planet->id,
            'name' => 'New Hope Colony',
        ]);
    }

    public function test_it_fails_to_establish_colony_on_non_planet()
    {
        $star = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/colonies", [
                'poi_uuid' => $star->uuid,
                'name' => 'Star Colony',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Only planets and moons can be colonized',
                ],
            ]);
    }

    public function test_it_fails_to_establish_colony_when_already_colonized()
    {
        $otherUser = User::factory()->create();
        $otherPlayer = Player::factory()->create([
            'user_id' => $otherUser->id,
            'galaxy_id' => $this->galaxy->id,
        ]);

        Colony::factory()->create([
            'player_id' => $otherPlayer->id,
            'poi_id' => $this->planet->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/colonies", [
                'poi_uuid' => $this->planet->uuid,
                'name' => 'Second Colony',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'This location already has a colony',
                ],
            ]);
    }

    public function test_it_gets_colony_details()
    {
        $colony = Colony::factory()->create([
            'player_id' => $this->player->id,
            'poi_id' => $this->planet->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/colonies/{$colony->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'colony' => [
                        'uuid',
                        'name',
                        'population',
                        'development_level',
                        'production',
                        'location',
                    ],
                ],
            ]);
    }

    public function test_it_updates_colony()
    {
        $colony = Colony::factory()->create([
            'player_id' => $this->player->id,
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/colonies/{$colony->uuid}", [
                'name' => 'New Name',
            ]);

        $response->assertOk();

        $colony->refresh();
        $this->assertEquals('New Name', $colony->name);
    }

    public function test_it_abandons_colony()
    {
        $colony = Colony::factory()->create([
            'player_id' => $this->player->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/colonies/{$colony->uuid}");

        $response->assertOk();

        $this->assertDatabaseMissing('colonies', [
            'id' => $colony->id,
        ]);
    }

    public function test_it_gets_production_summary()
    {
        $colony = Colony::factory()->create([
            'player_id' => $this->player->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/colonies/{$colony->uuid}/production");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'food_production',
                    'food_storage',
                    'mineral_production',
                    'mineral_storage',
                    'quantium_storage',
                    'credits_per_cycle',
                    'population',
                ],
            ]);
    }

    public function test_it_upgrades_development_level()
    {
        $colony = Colony::factory()->create([
            'player_id' => $this->player->id,
            'development_level' => 1,
            'mineral_storage' => 2000,
        ]);

        $this->player->update(['credits' => 20000]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/colonies/{$colony->uuid}/upgrade");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'new_development_level' => 2,
                ],
            ]);

        $colony->refresh();
        $this->assertEquals(2, $colony->development_level);
    }

    public function test_it_fails_upgrade_with_insufficient_credits()
    {
        $colony = Colony::factory()->create([
            'player_id' => $this->player->id,
            'development_level' => 1,
        ]);

        $this->player->update(['credits' => 100]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/colonies/{$colony->uuid}/upgrade");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Insufficient credits',
                ],
            ]);
    }

    public function test_it_gets_ship_production_queue()
    {
        $colony = Colony::factory()->create([
            'player_id' => $this->player->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/colonies/{$colony->uuid}/ship-production");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'has_shipyard',
                    'queue',
                ],
            ]);
    }

    public function test_it_requires_authentication()
    {
        $colony = Colony::factory()->create([
            'player_id' => $this->player->id,
            'poi_id' => $this->planet->id,
        ]);

        $response = $this->getJson("/api/colonies/{$colony->uuid}");

        $response->assertUnauthorized();
    }

    public function test_it_authorizes_colony_owner()
    {
        $otherUser = User::factory()->create();
        $otherPlayer = Player::factory()->create([
            'user_id' => $otherUser->id,
            'galaxy_id' => $this->galaxy->id,
        ]);

        $colony = Colony::factory()->create([
            'player_id' => $this->player->id,
        ]);

        $response = $this->actingAs($otherUser, 'sanctum')
            ->putJson("/api/colonies/{$colony->uuid}", [
                'name' => 'Hacked Name',
            ]);

        $response->assertStatus(403);
    }
}

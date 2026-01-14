<?php

namespace Tests\Feature\Api;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\Sector;
use App\Models\User;
use App\Models\WarpGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalaxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_list_all_galaxies()
    {
        Galaxy::factory()->count(3)->create();

        $response = $this->getJson('/api/galaxies');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'uuid',
                    'name',
                    'dimensions' => ['width', 'height'],
                    'configuration',
                    'statistics',
                ],
            ],
        ]);
    }

    public function test_it_can_get_galaxy_details()
    {
        $galaxy = Galaxy::factory()->create();

        $response = $this->getJson("/api/galaxies/{$galaxy->uuid}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.uuid', $galaxy->uuid);
        $response->assertJsonPath('data.name', $galaxy->name);
    }

    public function test_it_can_get_galaxy_statistics()
    {
        $galaxy = Galaxy::factory()->create();

        // Create some data - each player needs a unique user
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create();
            Player::factory()->create([
                'user_id' => $user->id,
                'galaxy_id' => $galaxy->id,
                'status' => 'active',
            ]);
        }

        PointOfInterest::factory()->count(10)->create([
            'galaxy_id' => $galaxy->id,
        ]);

        $response = $this->getJson("/api/galaxies/{$galaxy->uuid}/statistics");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'galaxy' => ['uuid', 'name', 'dimensions'],
                'players' => ['total', 'active', 'destroyed'],
                'economy' => ['total_credits_in_circulation', 'average_player_credits', 'trading_hubs'],
                'colonies' => ['total', 'total_population', 'average_development'],
                'combat' => ['total_pvp_challenges', 'completed_battles'],
                'infrastructure' => ['warp_gates', 'sectors', 'pirate_fleets'],
            ],
        ]);
    }

    public function test_it_can_get_galaxy_map()
    {
        $galaxy = Galaxy::factory()->create();

        // Create POIs
        PointOfInterest::factory()->count(5)->create([
            'galaxy_id' => $galaxy->id,
            'is_inhabited' => true,
        ]);

        $response = $this->getJson("/api/galaxies/{$galaxy->uuid}/map");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'galaxy' => ['uuid', 'name', 'width', 'height'],
                'systems',
                'warp_gates',
                'sectors',
                'player_location',
            ],
        ]);
    }

    public function test_it_can_get_galaxy_map_with_authenticated_player()
    {
        $galaxy = Galaxy::factory()->create();
        $user = User::factory()->create();

        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'is_inhabited' => true,
        ]);

        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/galaxies/{$galaxy->uuid}/map");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.player_location.x', $poi->x);
        $response->assertJsonPath('data.player_location.y', $poi->y);
    }

    public function test_it_can_get_sector_information()
    {
        $galaxy = Galaxy::factory()->create();

        $sector = Sector::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x_min' => 0,
            'x_max' => 100,
            'y_min' => 0,
            'y_max' => 100,
        ]);

        PointOfInterest::factory()->count(3)->create([
            'galaxy_id' => $galaxy->id,
            'x' => 50,
            'y' => 50,
        ]);

        $response = $this->getJson("/api/sectors/{$sector->uuid}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'uuid',
                'name',
                'galaxy' => ['uuid', 'name'],
                'bounds' => ['x_min', 'x_max', 'y_min', 'y_max'],
                'danger_level',
                'statistics' => [
                    'total_systems',
                    'inhabited_systems',
                    'active_players',
                    'pirate_fleets',
                ],
                'systems',
            ],
        ]);
    }

    public function test_it_returns_404_for_nonexistent_galaxy()
    {
        $response = $this->getJson('/api/galaxies/nonexistent-uuid');

        $response->assertStatus(404);
    }

    public function test_it_returns_404_for_nonexistent_sector()
    {
        $response = $this->getJson('/api/sectors/nonexistent-uuid');

        $response->assertStatus(404);
    }
}

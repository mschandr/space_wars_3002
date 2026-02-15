<?php

namespace Tests\Feature\Api;

use App\Models\Colony;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VictoryTest extends TestCase
{
    use RefreshDatabase;

    private Galaxy $galaxy;

    private User $user;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->galaxy = Galaxy::factory()->create();
        $this->user = User::factory()->create();
        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'credits' => 250000000, // 25% toward merchant victory
        ]);
    }

    public function test_it_can_get_victory_conditions()
    {
        $response = $this->getJson("/api/galaxies/{$this->galaxy->uuid}/victory-conditions");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'galaxy' => ['uuid', 'name'],
                'victory_conditions' => [
                    'merchant_empire' => [
                        'name',
                        'description',
                        'requirement',
                        'formatted_requirement',
                    ],
                    'colonization' => [
                        'name',
                        'description',
                        'requirement',
                        'formatted_requirement',
                    ],
                    'conquest' => [
                        'name',
                        'description',
                        'requirement',
                        'formatted_requirement',
                    ],
                    'pirate_king' => [
                        'name',
                        'description',
                        'requirement',
                        'formatted_requirement',
                    ],
                ],
            ],
        ]);
    }

    public function test_it_can_get_player_victory_progress()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/victory-progress");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'player' => ['uuid', 'call_sign'],
                'galaxy' => ['uuid', 'name'],
                'victory_paths' => [
                    'merchant_empire' => [
                        'progress_percent',
                        'achieved',
                        'current',
                        'required',
                        'remaining',
                    ],
                    'colonization' => [
                        'progress_percent',
                        'achieved',
                        'current_population',
                        'galaxy_population',
                        'population_share_percent',
                        'required_share_percent',
                    ],
                    'conquest' => [
                        'progress_percent',
                        'achieved',
                        'current_systems',
                        'total_systems',
                        'systems_share_percent',
                        'required_share_percent',
                    ],
                    'pirate_king',
                ],
                'closest_to_victory' => [
                    'path',
                    'name',
                    'progress_percent',
                ],
            ],
        ]);
    }

    public function test_it_calculates_merchant_victory_progress_correctly()
    {
        // Player has 250M credits, needs 1B for victory (25%)
        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/victory-progress");

        $response->assertStatus(200);
        $response->assertJsonPath('data.victory_paths.merchant_empire.progress_percent', 25);
        $response->assertJsonPath('data.victory_paths.merchant_empire.achieved', false);
        $response->assertJsonPath('data.victory_paths.merchant_empire.current', 250000000);
    }

    public function test_it_calculates_colonization_victory_progress_correctly()
    {
        $poi1 = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $poi2 = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);

        // Create colony with 10k population
        Colony::factory()->create([
            'player_id' => $this->player->id,
            'poi_id' => $poi1->id,
            'population' => 10000,
        ]);

        // Create another player's colony with 40k population (total 50k)
        $otherUser = User::factory()->create();
        $otherPlayer = Player::factory()->create([
            'user_id' => $otherUser->id,
            'galaxy_id' => $this->galaxy->id,
        ]);

        Colony::factory()->create([
            'player_id' => $otherPlayer->id,
            'poi_id' => $poi2->id,
            'population' => 40000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/victory-progress");

        $response->assertStatus(200);
        $response->assertJsonPath('data.victory_paths.colonization.current_population', 10000);
        $response->assertJsonPath('data.victory_paths.colonization.galaxy_population', 50000);
        // 10k / 50k = 20% share, needs 50% for victory = 40% progress
        $response->assertJsonPath('data.victory_paths.colonization.population_share_percent', 20);
    }

    public function test_it_calculates_conquest_victory_progress_correctly()
    {
        // Create 10 inhabited systems
        $pois = PointOfInterest::factory()->count(10)->create([
            'galaxy_id' => $this->galaxy->id,
            'is_inhabited' => true,
        ]);

        // Player controls 6 of them (60%)
        foreach ($pois->take(6) as $poi) {
            Colony::factory()->create([
                'player_id' => $this->player->id,
                'poi_id' => $poi->id,
            ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/victory-progress");

        $response->assertStatus(200);
        $response->assertJsonPath('data.victory_paths.conquest.current_systems', 6);
        $response->assertJsonPath('data.victory_paths.conquest.total_systems', 10);
        $response->assertJsonPath('data.victory_paths.conquest.systems_share_percent', 60);
        // 60% share, needs 60% for victory = 100% progress (achieved!)
        $response->assertJsonPath('data.victory_paths.conquest.progress_percent', 100);
        $response->assertJsonPath('data.victory_paths.conquest.achieved', true);
    }

    public function test_it_can_get_victory_leaders()
    {
        // Create additional players
        $user2 = User::factory()->create();
        $player2 = Player::factory()->create([
            'user_id' => $user2->id,
            'galaxy_id' => $this->galaxy->id,
            'credits' => 500000000, // More credits than player1
        ]);

        $response = $this->getJson("/api/galaxies/{$this->galaxy->uuid}/victory-leaders");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'galaxy' => ['uuid', 'name'],
                'victory_leaders' => [
                    'merchant_empire' => [
                        '*' => [
                            'uuid',
                            'call_sign',
                            'user_name',
                            'credits',
                            'progress_percent',
                        ],
                    ],
                    'colonization',
                    'conquest',
                ],
            ],
        ]);

        // Player2 should be first in merchant leaders
        $response->assertJsonPath('data.victory_leaders.merchant_empire.0.uuid', (string) $player2->uuid);
    }

    public function test_it_shows_top_5_leaders_per_category()
    {
        // Create 10 players with varying credits
        for ($i = 0; $i < 10; $i++) {
            $user = User::factory()->create();
            Player::factory()->create([
                'user_id' => $user->id,
                'galaxy_id' => $this->galaxy->id,
                'credits' => ($i + 1) * 10000000,
            ]);
        }

        $response = $this->getJson("/api/galaxies/{$this->galaxy->uuid}/victory-leaders");

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data.victory_leaders.merchant_empire');
    }

    public function test_it_identifies_closest_to_victory()
    {
        // Give player high progress in merchant path
        $this->player->update(['credits' => 900000000]); // 90% progress

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/victory-progress");

        $response->assertStatus(200);
        $response->assertJsonPath('data.closest_to_victory.path', 'merchant');
        $response->assertJsonPath('data.closest_to_victory.name', 'Merchant Empire');
    }

    public function test_it_returns_404_for_nonexistent_galaxy()
    {
        $response = $this->getJson('/api/galaxies/nonexistent-uuid/victory-conditions');

        $response->assertStatus(404);
    }

    public function test_it_returns_404_for_nonexistent_player()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/players/nonexistent-uuid/victory-progress');

        $response->assertStatus(404);
    }
}

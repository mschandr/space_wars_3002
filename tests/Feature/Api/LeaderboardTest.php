<?php

namespace Tests\Feature\Api;

use App\Models\Colony;
use App\Models\CombatSession;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private Galaxy $galaxy;
    private User $user1, $user2, $user3;
    private Player $player1, $player2, $player3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->galaxy = Galaxy::factory()->create();

        // Create users and players
        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
        $this->user3 = User::factory()->create();

        $this->player1 = Player::factory()->create([
            'user_id' => $this->user1->id,
            'galaxy_id' => $this->galaxy->id,
            'level' => 10,
            'experience' => 10000,
            'credits' => 500000,
        ]);

        $this->player2 = Player::factory()->create([
            'user_id' => $this->user2->id,
            'galaxy_id' => $this->galaxy->id,
            'level' => 8,
            'experience' => 6400,
            'credits' => 300000,
        ]);

        $this->player3 = Player::factory()->create([
            'user_id' => $this->user3->id,
            'galaxy_id' => $this->galaxy->id,
            'level' => 5,
            'experience' => 2500,
            'credits' => 100000,
        ]);
    }

    public function test_it_can_get_overall_leaderboard()
    {
        $response = $this->getJson("/api/galaxies/{$this->galaxy->uuid}/leaderboards/overall");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.leaderboard_type', 'overall');
        $response->assertJsonPath('data.total_players', 3);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'galaxy' => ['uuid', 'name'],
                'leaderboard_type',
                'total_players',
                'leaders' => [
                    '*' => [
                        'rank',
                        'player' => ['uuid', 'call_sign', 'user_name'],
                        'stats' => ['level', 'experience', 'credits'],
                        'ship',
                    ],
                ],
            ],
        ]);

        // Verify ordering (player1 should be rank 1)
        $response->assertJsonPath('data.leaders.0.rank', 1);
        $response->assertJsonPath('data.leaders.0.player.uuid', (string) $this->player1->uuid);
    }

    public function test_it_can_get_combat_leaderboard()
    {
        $response = $this->getJson("/api/galaxies/{$this->galaxy->uuid}/leaderboards/combat");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.leaderboard_type', 'combat');
        $response->assertJsonStructure([
            'success',
            'data' => [
                'galaxy',
                'leaderboard_type',
                'leaders' => [
                    '*' => [
                        'rank',
                        'player',
                        'combat_stats' => [
                            'pvp_wins',
                            'pvp_losses',
                            'pirate_kills',
                            'total_kills',
                            'kd_ratio',
                        ],
                        'combat_score',
                    ],
                ],
            ],
        ]);
    }

    public function test_it_can_get_economic_leaderboard()
    {
        $response = $this->getJson("/api/galaxies/{$this->galaxy->uuid}/leaderboards/economic");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.leaderboard_type', 'economic');
        $response->assertJsonStructure([
            'success',
            'data' => [
                'galaxy',
                'leaderboard_type',
                'leaders' => [
                    '*' => [
                        'rank',
                        'player',
                        'economic_stats' => [
                            'net_worth',
                            'liquid_credits',
                            'ship_value',
                            'cargo_value',
                            'colony_count',
                            'colony_value',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_it_can_get_colonial_leaderboard()
    {
        // Create colonies for testing - each colony needs a unique POI
        $poi1 = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $poi2 = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);

        Colony::factory()->create([
            'player_id' => $this->player1->id,
            'poi_id' => $poi1->id,
            'population' => 10000,
            'development_level' => 5,
        ]);

        Colony::factory()->create([
            'player_id' => $this->player1->id,
            'poi_id' => $poi2->id,
            'population' => 10000,
            'development_level' => 5,
        ]);

        $response = $this->getJson("/api/galaxies/{$this->galaxy->uuid}/leaderboards/colonial");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.leaderboard_type', 'colonial');
        $response->assertJsonStructure([
            'success',
            'data' => [
                'galaxy',
                'leaderboard_type',
                'leaders' => [
                    '*' => [
                        'rank',
                        'player',
                        'colonial_stats' => [
                            'colony_count',
                            'total_population',
                            'avg_development',
                            'population_share',
                        ],
                        'colonial_score',
                    ],
                ],
            ],
        ]);
    }

    public function test_it_can_limit_leaderboard_results()
    {
        // Create more players
        for ($i = 0; $i < 10; $i++) {
            $user = User::factory()->create();
            Player::factory()->create([
                'user_id' => $user->id,
                'galaxy_id' => $this->galaxy->id,
            ]);
        }

        $response = $this->getJson("/api/galaxies/{$this->galaxy->uuid}/leaderboards/overall?limit=5");

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data.leaders');
    }

    public function test_it_can_get_player_ranking()
    {
        $response = $this->actingAs($this->user1)
            ->getJson("/api/players/{$this->player1->uuid}/ranking");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'player' => ['uuid', 'call_sign'],
                'galaxy' => ['uuid', 'name'],
                'rankings' => ['overall', 'economic', 'colonial'],
                'total_players',
            ],
        ]);
    }

    public function test_it_can_get_detailed_player_statistics()
    {
        // Create ship for player
        $shipBlueprint = Ship::factory()->create();
        PlayerShip::factory()->create([
            'player_id' => $this->player1->id,
            'ship_id' => $shipBlueprint->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user1)
            ->getJson("/api/players/{$this->player1->uuid}/statistics");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'player' => ['uuid', 'call_sign', 'level', 'experience'],
                'galaxy' => ['uuid', 'name'],
                'statistics' => [
                    'combat' => [
                        'total_battles',
                        'victories',
                        'defeats',
                        'total_damage_dealt',
                        'total_damage_taken',
                    ],
                    'economic' => [
                        'current_credits',
                        'cargo_value',
                        'total_colonies',
                        'total_ships',
                    ],
                    'exploration' => [
                        'systems_visited',
                        'current_location',
                    ],
                ],
            ],
        ]);
    }

    public function test_it_returns_404_for_nonexistent_galaxy_in_leaderboard()
    {
        $response = $this->getJson('/api/galaxies/nonexistent-uuid/leaderboards/overall');

        $response->assertStatus(404);
    }

    public function test_it_returns_404_for_nonexistent_player_in_ranking()
    {
        $response = $this->actingAs($this->user1)
            ->getJson('/api/players/nonexistent-uuid/ranking');

        $response->assertStatus(404);
    }

    public function test_leaderboard_respects_player_status()
    {
        // Create destroyed player
        $user = User::factory()->create();
        Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $this->galaxy->id,
            'status' => 'destroyed',
            'level' => 20, // Higher than everyone else
        ]);

        $response = $this->getJson("/api/galaxies/{$this->galaxy->uuid}/leaderboards/overall");

        $response->assertStatus(200);
        // Should only show 3 active players, not the destroyed one
        $response->assertJsonPath('data.total_players', 3);
    }
}

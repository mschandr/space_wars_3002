<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Galaxy Creation API Tests
 *
 * Note: Uses DatabaseTransactions instead of RefreshDatabase to avoid
 * deleting existing database data. Each test runs in a transaction that
 * is rolled back after the test completes.
 */
class GalaxyCreationTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_create_multiplayer_galaxy(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'width' => 200,
                'height' => 200,
                'stars' => 100,
                'game_mode' => 'multiplayer',
                'skip_mirror' => true,
                'skip_pirates' => true,
                'skip_precursors' => true,
            ]);

        if ($response->status() !== 201) {
            dump($response->json());
        }

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'success',
                    'galaxy' => [
                        'id',
                        'uuid',
                        'name',
                        'width',
                        'height',
                        'game_mode',
                        'status',
                    ],
                    'statistics',
                    'steps',
                    'execution_time_seconds',
                ],
            ])
            ->assertJsonPath('data.galaxy.game_mode', 'multiplayer')
            ->assertJsonPath('data.galaxy.width', 200)
            ->assertJsonPath('data.galaxy.height', 200);
    }

    public function test_can_create_single_player_galaxy_with_npcs(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'width' => 150,
                'height' => 150,
                'stars' => 50,
                'game_mode' => 'single_player',
                'npc_count' => 3,
                'npc_difficulty' => 'easy',
                'skip_mirror' => true,
                'skip_pirates' => true,
                'skip_precursors' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.galaxy.game_mode', 'single_player')
            ->assertJsonCount(3, 'data.npcs');
    }

    public function test_validation_fails_with_invalid_game_mode(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'width' => 200,
                'height' => 200,
                'stars' => 100,
                'game_mode' => 'invalid_mode',
            ]);

        $response->assertStatus(422);
    }

    public function test_validation_fails_with_width_too_small(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'width' => 50,
                'height' => 200,
                'stars' => 100,
                'game_mode' => 'multiplayer',
            ]);

        $response->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->postJson('/api/galaxies/create', [
            'width' => 200,
            'height' => 200,
            'stars' => 100,
            'game_mode' => 'multiplayer',
        ]);

        $response->assertStatus(401);
    }

    public function test_can_get_npc_archetypes(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/npcs/archetypes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'archetypes',
                    'difficulties',
                ],
            ]);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Galaxy Creation API Tests
 *
 * Tests the optimized galaxy creation endpoint: POST /api/galaxies/create
 */
class GalaxyCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_create_small_galaxy(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'size_tier' => 'small',
                'game_mode' => 'multiplayer',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        if ($response->status() !== 201) {
            dump($response->json());
        }

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'galaxy' => [
                        'id',
                        'uuid',
                        'name',
                        'status',
                    ],
                    'statistics' => [
                        'total_pois',
                        'total_stars',
                        'core_stars',
                        'outer_stars',
                        'inhabited_systems',
                        'warp_gates',
                        'sectors',
                    ],
                    // Note: 'metrics' is only included for admin users (user_id=1, email=mark.dhas@gmail.com)
                    'config' => [
                        'tier',
                        'game_mode',
                        'dimensions',
                        'star_counts',
                    ],
                ],
            ])
            ->assertJsonPath('data.config.tier', 'small')
            ->assertJsonPath('data.config.game_mode', 'multiplayer')
            ->assertJsonPath('data.config.dimensions.width', 500)
            ->assertJsonPath('data.config.dimensions.height', 500);

        // Non-admin users should NOT receive metrics
        $this->assertArrayNotHasKey('metrics', $response->json('data'));
    }

    public function test_can_create_medium_galaxy(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'size_tier' => 'medium',
                'game_mode' => 'multiplayer',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.config.tier', 'medium')
            ->assertJsonPath('data.config.game_mode', 'multiplayer')
            ->assertJsonPath('data.config.dimensions.width', 1500)
            ->assertJsonPath('data.config.dimensions.height', 1500);
    }

    public function test_validation_requires_size_tier(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'game_mode' => 'multiplayer',
            ]);

        $response->assertStatus(422);
    }

    public function test_validation_requires_game_mode(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'size_tier' => 'small',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        // game_mode is required
        $response->assertStatus(422);
    }

    public function test_can_create_single_player_galaxy(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'size_tier' => 'small',
                'game_mode' => 'single_player',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.config.game_mode', 'single_player');
    }

    public function test_validation_rejects_invalid_size_tier(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'size_tier' => 'extra_large',
                'game_mode' => 'multiplayer',
            ]);

        $response->assertStatus(422);
    }

    public function test_validation_rejects_invalid_game_mode(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'size_tier' => 'small',
                'game_mode' => 'invalid_mode',
            ]);

        $response->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->postJson('/api/galaxies/create', [
            'size_tier' => 'small',
            'game_mode' => 'multiplayer',
        ]);

        $response->assertStatus(401);
    }

    public function test_can_get_size_tiers(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/galaxies/size-tiers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tiers' => [
                        '*' => [
                            'value',
                            'label',
                            'outer_bounds',
                            'core_bounds',
                            'core_stars',
                            'outer_stars',
                            'total_stars',
                        ],
                    ],
                ],
            ]);

        // Only public tiers (small, medium, large) are returned
        $tiers = $response->json('data.tiers');
        $this->assertCount(3, $tiers);
    }

    public function test_can_use_secret_massive_tier(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'size_tier' => 'massive',
                'game_mode' => 'multiplayer',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.config.tier', 'massive')
            ->assertJsonPath('data.config.dimensions.width', 5000)
            ->assertJsonPath('data.config.dimensions.height', 5000);
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

    public function test_galaxy_has_sectors(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'size_tier' => 'small',
                'game_mode' => 'multiplayer',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        $response->assertStatus(201);
        $stats = $response->json('data.statistics');

        // Small galaxy should have 5x5 = 25 sectors
        $this->assertEquals(25, $stats['sectors']);
    }

    public function test_galaxy_has_inhabited_core_systems(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'size_tier' => 'small',
                'game_mode' => 'multiplayer',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        $response->assertStatus(201);
        $stats = $response->json('data.statistics');

        // Most core systems should be inhabited (~81%)
        $this->assertGreaterThan(0, $stats['inhabited_systems']);
        // Inhabited should be a significant fraction of core stars but not all
        $this->assertGreaterThan($stats['core_stars'] * 0.5, $stats['inhabited_systems']);
    }

    public function test_galaxy_has_warp_gates(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'size_tier' => 'small',
                'game_mode' => 'multiplayer',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        $response->assertStatus(201);
        $stats = $response->json('data.statistics');

        // Should have warp gates
        $this->assertGreaterThan(0, $stats['warp_gates']);
        $this->assertGreaterThan(0, $stats['active_gates']);
    }

    public function test_galaxy_has_trading_hubs(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'size_tier' => 'small',
                'game_mode' => 'multiplayer',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        $response->assertStatus(201);
        $stats = $response->json('data.statistics');

        // Should have trading hubs in core systems
        $this->assertGreaterThan(0, $stats['trading_hubs']);
    }

    public function test_can_get_creation_status(): void
    {
        // First create a galaxy
        $createResponse = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'size_tier' => 'small',
                'game_mode' => 'multiplayer',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        $createResponse->assertStatus(201);
        $galaxyUuid = $createResponse->json('data.galaxy.uuid');

        // Get creation status
        $statusResponse = $this->actingAs($this->user)
            ->getJson("/api/galaxies/{$galaxyUuid}/creation-status");

        $statusResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'galaxy_id',
                    'galaxy_uuid',
                    'galaxy_name',
                    'status',
                    'size_tier',
                    'current_progress',
                    'is_complete',
                ],
            ])
            ->assertJsonPath('data.is_complete', true);
    }

    public function test_generation_metrics_are_returned_for_admin(): void
    {
        // Admin is defined as user_id=1 AND email=mark.dhas@gmail.com
        // $this->user was created with id=1 in setUp(), so just update the email
        $this->user->email = 'mark.dhas@gmail.com';
        $this->user->save();

        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'size_tier' => 'small',
                'game_mode' => 'multiplayer',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        $response->assertStatus(201);
        $metrics = $response->json('data.metrics');

        // Admin should receive metrics
        $this->assertNotNull($metrics, 'Admin users should receive metrics');

        // Should have total timing
        $this->assertArrayHasKey('total_elapsed_ms', $metrics);
        $this->assertArrayHasKey('total_elapsed_seconds', $metrics);
        $this->assertGreaterThan(0, $metrics['total_elapsed_ms']);

        // Should have per-generator metrics
        $generators = $metrics['generators'];
        $this->assertArrayHasKey('star_field', $generators);
        $this->assertArrayHasKey('planetary_systems', $generators);
        $this->assertArrayHasKey('sector_grid', $generators);
        $this->assertArrayHasKey('warp_gate_network', $generators);
    }

    public function test_generation_metrics_are_hidden_for_non_admin(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'size_tier' => 'small',
                'game_mode' => 'multiplayer',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        $response->assertStatus(201);
        $metrics = $response->json('data.metrics');

        // Non-admin users should NOT receive metrics
        $this->assertNull($metrics, 'Non-admin users should not receive metrics');
    }

    public function test_npc_config_is_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create', [
                'size_tier' => 'small',
                'game_mode' => 'multiplayer',
                'npc_count' => 10,
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'NPC_CONFIG_DISABLED');
    }
}

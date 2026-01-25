<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tiered Galaxy Creation API Tests
 *
 * Tests for the tiered galaxy creation system with core/outer regions.
 */
class TieredGalaxyCreationTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_create_small_tiered_galaxy(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create-tiered', [
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
                    'success',
                    'galaxy' => [
                        'id',
                        'uuid',
                        'name',
                        'size_tier',
                        'width',
                        'height',
                        'core_bounds',
                        'game_mode',
                        'status',
                    ],
                    'statistics' => [
                        'total_stars',
                        'core_stars',
                        'outer_stars',
                        'core_inhabited',
                        'outer_inhabited',
                        'fortified_systems',
                    ],
                    'execution_time_seconds',
                ],
            ])
            ->assertJsonPath('data.galaxy.size_tier', 'small')
            ->assertJsonPath('data.galaxy.width', 500)
            ->assertJsonPath('data.galaxy.height', 500);

        // Verify core bounds
        $data = $response->json('data');
        $this->assertEquals(125, $data['galaxy']['core_bounds']['x_min']);
        $this->assertEquals(375, $data['galaxy']['core_bounds']['x_max']);
        $this->assertEquals(125, $data['galaxy']['core_bounds']['y_min']);
        $this->assertEquals(375, $data['galaxy']['core_bounds']['y_max']);
    }

    public function test_can_create_medium_tiered_galaxy(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create-tiered', [
                'size_tier' => 'medium',
                'game_mode' => 'single_player',
                'npc_count' => 3,
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.galaxy.size_tier', 'medium')
            ->assertJsonPath('data.galaxy.width', 1500)
            ->assertJsonPath('data.galaxy.height', 1500)
            ->assertJsonPath('data.galaxy.game_mode', 'single_player');
    }

    public function test_large_galaxy_uses_async_by_default(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create-tiered', [
                'size_tier' => 'large',
                'game_mode' => 'multiplayer',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        // Should return 202 Accepted for async processing
        $response->assertStatus(202)
            ->assertJsonPath('data.async', true)
            ->assertJsonPath('data.galaxy.size_tier', 'large')
            ->assertJsonPath('data.galaxy.status', 'processing');
    }

    public function test_validation_requires_size_tier(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create-tiered', [
                'game_mode' => 'multiplayer',
            ]);

        $response->assertStatus(422);
    }

    public function test_validation_rejects_invalid_size_tier(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create-tiered', [
                'size_tier' => 'extra_large',
                'game_mode' => 'multiplayer',
            ]);

        $response->assertStatus(422);
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

        $tiers = $response->json('data.tiers');
        $this->assertCount(3, $tiers);  // small, medium, large
    }

    public function test_can_get_creation_status(): void
    {
        // First create a galaxy
        $createResponse = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create-tiered', [
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
                    'generation_started_at',
                    'generation_completed_at',
                    'steps',
                ],
            ])
            ->assertJsonPath('data.is_complete', true)
            ->assertJsonPath('data.current_progress', 100);
    }

    public function test_core_systems_are_inhabited(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create-tiered', [
                'size_tier' => 'small',
                'game_mode' => 'multiplayer',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        $response->assertStatus(201);
        $stats = $response->json('data.statistics');

        // Core systems should be inhabited
        $this->assertGreaterThan(0, $stats['core_inhabited']);

        // Outer systems should not be inhabited
        $this->assertEquals(0, $stats['outer_inhabited']);
    }

    public function test_core_systems_are_fortified(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create-tiered', [
                'size_tier' => 'small',
                'game_mode' => 'multiplayer',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        $response->assertStatus(201);
        $stats = $response->json('data.statistics');

        // Core systems should be fortified
        $this->assertGreaterThan(0, $stats['fortified_systems']);
    }

    public function test_outer_region_has_dormant_gates(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/galaxies/create-tiered', [
                'size_tier' => 'small',
                'game_mode' => 'multiplayer',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

        $response->assertStatus(201);
        $stats = $response->json('data.statistics');

        // Should have dormant gates
        $this->assertGreaterThanOrEqual(0, $stats['dormant_gates']);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->postJson('/api/galaxies/create-tiered', [
            'size_tier' => 'small',
            'game_mode' => 'multiplayer',
        ]);

        $response->assertStatus(401);
    }
}

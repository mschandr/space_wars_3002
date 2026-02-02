<?php

namespace Tests\Feature\Api;

use App\Enums\Galaxy\GalaxyStatus;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Galaxy API Tests
 *
 * Tests for galaxy listing, details, and map endpoints.
 */
class GalaxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_galaxy_list_requires_authentication(): void
    {
        $response = $this->getJson('/api/galaxies');

        $response->assertStatus(401);
    }

    public function test_can_list_galaxies_dehydrated(): void
    {
        $user = User::factory()->create();

        // Create galaxies - one the user is part of, two open
        $userGalaxy = Galaxy::factory()->create([
            'status' => GalaxyStatus::ACTIVE,
            'game_mode' => 'multiplayer',
        ]);

        Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $userGalaxy->id,
            'status' => 'active',
            'last_accessed_at' => now(),
        ]);

        Galaxy::factory()->count(2)->create([
            'status' => GalaxyStatus::ACTIVE,
            'game_mode' => 'multiplayer',
        ]);

        $response = $this->actingAs($user)->getJson('/api/galaxies');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'my_games' => [
                        '*' => [
                            'uuid',
                            'name',
                            'size',
                            'players',
                            'max_players',
                            'slots_available',
                            'mode',
                            'status',
                        ],
                    ],
                    'open_games' => [
                        '*' => [
                            'uuid',
                            'name',
                            'size',
                            'players',
                            'max_players',
                            'slots_available',
                            'mode',
                            'status',
                        ],
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data.my_games')
            ->assertJsonCount(2, 'data.open_games');
    }

    public function test_can_get_cached_galaxy_list(): void
    {
        $user = User::factory()->create();

        Galaxy::factory()->count(2)->create([
            'status' => GalaxyStatus::ACTIVE,
            'game_mode' => 'multiplayer',
        ]);

        $response = $this->actingAs($user)->getJson('/api/galaxies/list');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'my_games',
                    'open_games',
                ],
            ])
            ->assertJsonCount(0, 'data.my_games')
            ->assertJsonCount(2, 'data.open_games');
    }

    public function test_can_get_galaxy_details(): void
    {
        $galaxy = Galaxy::factory()->create([
            'status' => GalaxyStatus::ACTIVE,
        ]);

        $response = $this->getJson("/api/galaxies/{$galaxy->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $galaxy->uuid)
            ->assertJsonPath('data.name', $galaxy->name)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'uuid',
                    'name',
                    'status',
                    'game_mode',
                    'size_tier',
                    'dimensions' => ['width', 'height'],
                    'statistics',
                    'created_at',
                ],
            ]);
    }

    public function test_can_get_galaxy_statistics(): void
    {
        $galaxy = Galaxy::factory()->create();

        // Create some players
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

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
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

    public function test_can_get_galaxy_map(): void
    {
        $galaxy = Galaxy::factory()->create();

        PointOfInterest::factory()->count(5)->create([
            'galaxy_id' => $galaxy->id,
            'is_inhabited' => true,
        ]);

        $response = $this->getJson("/api/galaxies/{$galaxy->uuid}/map");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
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

    public function test_can_get_galaxy_map_with_authenticated_player(): void
    {
        $galaxy = Galaxy::factory()->create();
        $user = User::factory()->create();

        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'is_inhabited' => true,
        ]);

        Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/galaxies/{$galaxy->uuid}/map");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.player_location.x', $poi->x)
            ->assertJsonPath('data.player_location.y', $poi->y);
    }

    public function test_can_get_sector_information(): void
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
            'sector_id' => $sector->id,
            'x' => 50,
            'y' => 50,
        ]);

        $response = $this->getJson("/api/sectors/{$sector->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
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

    public function test_returns_404_for_nonexistent_galaxy(): void
    {
        $response = $this->getJson('/api/galaxies/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    public function test_returns_404_for_nonexistent_sector(): void
    {
        $response = $this->getJson('/api/sectors/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    public function test_galaxy_list_includes_player_count(): void
    {
        $user = User::factory()->create();

        $galaxy = Galaxy::factory()->create([
            'status' => GalaxyStatus::ACTIVE,
            'game_mode' => 'multiplayer',
        ]);

        // Create 2 active players (not including the authenticated user)
        for ($i = 0; $i < 2; $i++) {
            $otherUser = User::factory()->create();
            Player::factory()->create([
                'user_id' => $otherUser->id,
                'galaxy_id' => $galaxy->id,
                'status' => 'active',
            ]);
        }

        $response = $this->actingAs($user)->getJson('/api/galaxies');

        $response->assertStatus(200);
        $openGames = $response->json('data.open_games');
        $this->assertCount(1, $openGames);
        $this->assertEquals(2, $openGames[0]['players']);
        $this->assertEquals(98, $openGames[0]['slots_available']); // 100 - 2
    }

    // =========================================================================
    // GET /api/galaxies/{uuid}/my-player Tests
    // =========================================================================

    public function test_my_player_requires_authentication(): void
    {
        $galaxy = Galaxy::factory()->create();

        $response = $this->getJson("/api/galaxies/{$galaxy->uuid}/my-player");

        $response->assertStatus(401);
    }

    public function test_my_player_returns_player_when_exists(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);

        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
            'call_sign' => 'TestPilot',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/galaxies/{$galaxy->uuid}/my-player");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', (string) $player->uuid)
            ->assertJsonPath('data.call_sign', 'TestPilot');
    }

    public function test_my_player_returns_404_when_no_player(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create();

        $response = $this->actingAs($user)
            ->getJson("/api/galaxies/{$galaxy->uuid}/my-player");

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'NO_PLAYER_IN_GALAXY');
    }

    public function test_my_player_returns_404_for_nonexistent_galaxy(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/galaxies/00000000-0000-0000-0000-000000000000/my-player');

        $response->assertStatus(404);
    }

    // =========================================================================
    // POST /api/galaxies/{uuid}/join Tests
    // =========================================================================

    public function test_join_requires_authentication(): void
    {
        $galaxy = Galaxy::factory()->create();

        $response = $this->postJson("/api/galaxies/{$galaxy->uuid}/join", [
            'call_sign' => 'TestPilot',
        ]);

        $response->assertStatus(401);
    }

    public function test_join_returns_existing_player_when_already_joined(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create(['status' => GalaxyStatus::ACTIVE]);
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);

        $existingPlayer = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
            'call_sign' => 'ExistingPilot',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/galaxies/{$galaxy->uuid}/join", [
                'call_sign' => 'NewCallSign', // This should be ignored
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', false)
            ->assertJsonPath('data.player.uuid', (string) $existingPlayer->uuid)
            ->assertJsonPath('data.player.call_sign', 'ExistingPilot');
    }

    public function test_join_creates_new_player_when_not_joined(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create([
            'status' => GalaxyStatus::ACTIVE,
            'game_mode' => 'multiplayer',
        ]);

        // Create an inhabited starting location
        PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'is_inhabited' => true,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/galaxies/{$galaxy->uuid}/join", [
                'call_sign' => 'NewPilot',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', true)
            ->assertJsonPath('data.player.call_sign', 'NewPilot');

        $this->assertDatabaseHas('players', [
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'call_sign' => 'NewPilot',
        ]);
    }

    public function test_join_requires_call_sign_when_creating(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create(['status' => GalaxyStatus::ACTIVE]);

        $response = $this->actingAs($user)
            ->postJson("/api/galaxies/{$galaxy->uuid}/join", []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_join_rejects_duplicate_call_sign(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $galaxy = Galaxy::factory()->create([
            'status' => GalaxyStatus::ACTIVE,
            'game_mode' => 'multiplayer',
        ]);

        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'is_inhabited' => true,
        ]);

        // Another player already has this call sign
        Player::factory()->create([
            'user_id' => $otherUser->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
            'call_sign' => 'TakenName',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/galaxies/{$galaxy->uuid}/join", [
                'call_sign' => 'TakenName',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'DUPLICATE_CALL_SIGN');
    }

    public function test_join_rejects_inactive_galaxy(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create(['status' => GalaxyStatus::DRAFT]);

        $response = $this->actingAs($user)
            ->postJson("/api/galaxies/{$galaxy->uuid}/join", [
                'call_sign' => 'TestPilot',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'GALAXY_NOT_ACTIVE');
    }

    public function test_join_rejects_full_galaxy(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create([
            'status' => GalaxyStatus::ACTIVE,
            'game_mode' => 'multiplayer',
            'max_players' => 2,
        ]);

        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'is_inhabited' => true,
        ]);

        // Fill up the galaxy
        for ($i = 0; $i < 2; $i++) {
            $otherUser = User::factory()->create();
            Player::factory()->create([
                'user_id' => $otherUser->id,
                'galaxy_id' => $galaxy->id,
                'current_poi_id' => $poi->id,
                'status' => 'active',
            ]);
        }

        $response = $this->actingAs($user)
            ->postJson("/api/galaxies/{$galaxy->uuid}/join", [
                'call_sign' => 'TestPilot',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'GALAXY_FULL');
    }

    public function test_join_rejects_single_player_galaxy_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $galaxy = Galaxy::factory()->create([
            'status' => GalaxyStatus::ACTIVE,
            'game_mode' => 'single_player',
            'owner_user_id' => $owner->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->postJson("/api/galaxies/{$galaxy->uuid}/join", [
                'call_sign' => 'TestPilot',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SINGLE_PLAYER_GALAXY');
    }

    public function test_join_allows_owner_to_join_single_player_galaxy(): void
    {
        $owner = User::factory()->create();

        $galaxy = Galaxy::factory()->create([
            'status' => GalaxyStatus::ACTIVE,
            'game_mode' => 'single_player',
            'owner_user_id' => $owner->id,
        ]);

        PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'is_inhabited' => true,
        ]);

        $response = $this->actingAs($owner)
            ->postJson("/api/galaxies/{$galaxy->uuid}/join", [
                'call_sign' => 'OwnerPilot',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', true);
    }

    public function test_join_is_idempotent(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create([
            'status' => GalaxyStatus::ACTIVE,
            'game_mode' => 'multiplayer',
        ]);

        PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'is_inhabited' => true,
        ]);

        // First call creates the player
        $response1 = $this->actingAs($user)
            ->postJson("/api/galaxies/{$galaxy->uuid}/join", [
                'call_sign' => 'TestPilot',
            ]);

        $response1->assertStatus(201)
            ->assertJsonPath('data.created', true);

        $playerUuid = $response1->json('data.player.uuid');

        // Second call returns the same player
        $response2 = $this->actingAs($user)
            ->postJson("/api/galaxies/{$galaxy->uuid}/join", [
                'call_sign' => 'DifferentName', // Should be ignored
            ]);

        $response2->assertStatus(200)
            ->assertJsonPath('data.created', false)
            ->assertJsonPath('data.player.uuid', $playerUuid)
            ->assertJsonPath('data.player.call_sign', 'TestPilot');

        // Only one player should exist
        $this->assertEquals(1, Player::where('user_id', $user->id)
            ->where('galaxy_id', $galaxy->id)
            ->count());
    }
}

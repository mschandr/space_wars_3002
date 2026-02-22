<?php

namespace Tests\Feature\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    /**
     * Test user can list their players
     */
    public function test_user_can_list_their_players(): void
    {
        // Create 3 different galaxies to avoid unique constraint
        $galaxies = Galaxy::factory()->count(3)->create();

        foreach ($galaxies as $galaxy) {
            Player::factory()->create([
                'user_id' => $this->user->id,
                'galaxy_id' => $galaxy->id,
            ]);
        }

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/players');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['uuid', 'call_sign', 'credits', 'level'],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /**
     * Test user can create a new player
     */
    public function test_user_can_create_player(): void
    {
        $galaxy = Galaxy::factory()->create();

        // Create multiple inhabited star systems to ensure the random query finds one
        PointOfInterest::factory()->count(5)->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
        ]);

        // Create a scout ship blueprint
        Ship::factory()->create([
            'class' => 'scout',
            'name' => 'Scout',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/players', [
                'galaxy_id' => $galaxy->id,
                'call_sign' => 'Captain Nova',
            ]);

        if ($response->status() !== 201) {
            // Debug: check what POIs exist
            dump(PointOfInterest::where('galaxy_id', $galaxy->id)->get()->toArray());
            dump($response->json());
        }

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['uuid', 'call_sign', 'credits', 'level'],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'call_sign' => 'Captain Nova',
                    'level' => 1,
                ],
            ]);

        $this->assertDatabaseHas('players', [
            'user_id' => $this->user->id,
            'call_sign' => 'Captain Nova',
            'galaxy_id' => $galaxy->id,
        ]);
    }

    /**
     * Test cannot create player with duplicate call sign in same galaxy
     */
    public function test_cannot_create_player_with_duplicate_call_sign(): void
    {
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
        ]);

        Player::factory()->create([
            'galaxy_id' => $galaxy->id,
            'call_sign' => 'Existing Player',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/players', [
                'galaxy_id' => $galaxy->id,
                'call_sign' => 'Existing Player',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'DUPLICATE_CALL_SIGN',
                ],
            ]);
    }

    /**
     * Test user can get player details by UUID
     */
    public function test_user_can_get_player_details(): void
    {
        $galaxy = Galaxy::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$player->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['uuid', 'call_sign', 'credits', 'level'],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'uuid' => $player->uuid,
                    'call_sign' => $player->call_sign,
                ],
            ]);
    }

    /**
     * Test user cannot access another user's player
     */
    public function test_user_cannot_access_other_users_player(): void
    {
        $otherUser = User::factory()->create();
        $galaxy = Galaxy::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $otherUser->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$player->uuid}");

        $response->assertStatus(404);
    }

    /**
     * Test user can update player call sign
     */
    public function test_user_can_update_player_call_sign(): void
    {
        $galaxy = Galaxy::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $galaxy->id,
            'call_sign' => 'Old Name',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/players/{$player->uuid}", [
                'call_sign' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'call_sign' => 'New Name',
                ],
            ]);

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'call_sign' => 'New Name',
        ]);
    }

    /**
     * Test user can delete their player
     */
    public function test_user_can_delete_player(): void
    {
        $galaxy = Galaxy::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/players/{$player->uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('players', [
            'id' => $player->id,
        ]);
    }

    /**
     * Test user can get player status
     */
    public function test_user_can_get_player_status(): void
    {
        $galaxy = Galaxy::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$player->uuid}/status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'player' => ['uuid', 'call_sign', 'level', 'credits'],
                    'location',  // Can be null if player has no location
                ],
            ]);
    }

    /**
     * Test user can get player stats
     */
    public function test_user_can_get_player_stats(): void
    {
        $galaxy = Galaxy::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/players/{$player->uuid}/stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'player_info',
                    'economy',
                    'exploration',
                    'mirror_universe',
                ],
            ]);
    }
}

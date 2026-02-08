<?php

namespace Tests\Feature\Api;

use App\Enums\Galaxy\GalaxyStatus;
use App\Models\Galaxy;
use App\Models\PilotLaneKnowledge;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\User;
use App\Models\WarpGate;
use App\Services\LaneKnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Backend Improvements API Tests
 *
 * Tests for the 7 backend improvements:
 * 1. Admin-only generation metrics (tested in GalaxyCreationTest)
 * 2. NPC config rejection (tested in GalaxyCreationTest)
 * 3. Pilot lane knowledge (fog of war)
 * 4. LaneKnowledgeService
 * 5. Map summaries endpoint
 * 6. Player/Galaxy settings PATCH endpoints
 * 7. Spawn discovery
 */
class BackendImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Galaxy $galaxy;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->galaxy = Galaxy::factory()->create([
            'status' => GalaxyStatus::ACTIVE,
            'owner_user_id' => $this->user->id,
        ]);
    }

    // ========================================
    // Task 3: Lane Knowledge (Fog of War)
    // ========================================

    public function test_lane_knowledge_is_created_on_discovery(): void
    {
        $poi1 = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $poi2 = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);

        $gate = WarpGate::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $poi1->id,
            'destination_poi_id' => $poi2->id,
        ]);

        $player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $poi1->id,
        ]);

        $service = app(LaneKnowledgeService::class);
        $knowledge = $service->discoverLane($player, $gate, 'travel');

        $this->assertInstanceOf(PilotLaneKnowledge::class, $knowledge);
        $this->assertEquals($player->id, $knowledge->player_id);
        $this->assertEquals($gate->id, $knowledge->warp_gate_id);
        $this->assertEquals('travel', $knowledge->discovery_method);
    }

    public function test_lane_knowledge_is_not_duplicated(): void
    {
        $poi1 = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $poi2 = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);

        $gate = WarpGate::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $poi1->id,
            'destination_poi_id' => $poi2->id,
        ]);

        $player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $poi1->id,
        ]);

        $service = app(LaneKnowledgeService::class);

        // First discovery
        $knowledge1 = $service->discoverLane($player, $gate, 'travel');

        // Second discovery should return existing
        $knowledge2 = $service->discoverLane($player, $gate, 'scan');

        $this->assertEquals($knowledge1->id, $knowledge2->id);
        $this->assertEquals(1, PilotLaneKnowledge::where('player_id', $player->id)->count());
    }

    public function test_bidirectional_lane_discovery(): void
    {
        // Create POIs with specific coordinates to ensure canonical uniqueness
        $poi1 = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'x' => 100,
            'y' => 100,
        ]);
        $poi2 = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'x' => 200,
            'y' => 200,
        ]);

        // Create forward gate (poi1 -> poi2)
        $forwardGate = WarpGate::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $poi1->id,
            'destination_poi_id' => $poi2->id,
            'source_x' => 100,
            'source_y' => 100,
            'dest_x' => 200,
            'dest_y' => 200,
        ]);

        // Create reverse gate (poi2 -> poi1) with different canonical coords
        $reverseGate = WarpGate::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $poi2->id,
            'destination_poi_id' => $poi1->id,
            // Note: canonical ordering means lower x comes first
            // So we need to use different coordinates to avoid collision
            'source_x' => 100,
            'source_y' => 101, // Slightly different to avoid collision
            'dest_x' => 200,
            'dest_y' => 201,
        ]);

        $player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $poi1->id,
        ]);

        $service = app(LaneKnowledgeService::class);
        $discovered = $service->discoverLaneBidirectional($player, $forwardGate, 'travel');

        $this->assertCount(2, $discovered);
        $this->assertTrue($service->knowsLane($player, $forwardGate));
        $this->assertTrue($service->knowsLane($player, $reverseGate));
    }

    // ========================================
    // Task 5: Map Summaries Endpoint
    // ========================================

    public function test_map_summaries_requires_authentication(): void
    {
        $response = $this->getJson("/api/galaxies/{$this->galaxy->uuid}/map-summaries");
        $response->assertStatus(401);
    }

    public function test_map_summaries_requires_player_in_galaxy(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/galaxies/{$this->galaxy->uuid}/map-summaries");

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NO_PLAYER_IN_GALAXY');
    }

    public function test_map_summaries_returns_known_systems(): void
    {
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'is_inhabited' => true,
        ]);

        $player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $poi->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/galaxies/{$this->galaxy->uuid}/map-summaries");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1);
    }

    // ========================================
    // Task 6: Settings PATCH Endpoints
    // ========================================

    public function test_player_settings_requires_authentication(): void
    {
        $player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
        ]);

        $response = $this->patchJson("/api/players/{$player->uuid}/settings", [
            'call_sign' => 'NewCallSign',
        ]);

        $response->assertStatus(401);
    }

    public function test_player_settings_requires_ownership(): void
    {
        $otherUser = User::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $otherUser->id,
            'galaxy_id' => $this->galaxy->id,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/players/{$player->uuid}/settings", [
                'call_sign' => 'NewCallSign',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_can_update_player_call_sign(): void
    {
        $player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'call_sign' => 'OldCallSign',
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/players/{$player->uuid}/settings", [
                'call_sign' => 'NewCallSign',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals('NewCallSign', $player->fresh()->call_sign);
    }

    public function test_player_call_sign_must_be_unique_in_galaxy(): void
    {
        $player1 = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'call_sign' => 'ExistingCallSign',
        ]);

        $otherUser = User::factory()->create();
        $player2 = Player::factory()->create([
            'user_id' => $otherUser->id,
            'galaxy_id' => $this->galaxy->id,
            'call_sign' => 'OtherCallSign',
        ]);

        $response = $this->actingAs($otherUser)
            ->patchJson("/api/players/{$player2->uuid}/settings", [
                'call_sign' => 'ExistingCallSign',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'DUPLICATE_CALL_SIGN');
    }

    public function test_galaxy_settings_requires_ownership(): void
    {
        $otherUser = User::factory()->create();
        $otherGalaxy = Galaxy::factory()->create([
            'status' => GalaxyStatus::ACTIVE,
            'owner_user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/galaxies/{$otherGalaxy->uuid}/settings", [
                'name' => 'NewGalaxyName',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_can_update_galaxy_settings(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson("/api/galaxies/{$this->galaxy->uuid}/settings", [
                'name' => 'UpdatedGalaxyName',
                'description' => 'New description',
                'is_public' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->galaxy->refresh();
        $this->assertEquals('UpdatedGalaxyName', $this->galaxy->name);
        $this->assertEquals('New description', $this->galaxy->description);
        $this->assertEquals(true, (bool) $this->galaxy->is_public);
    }
}

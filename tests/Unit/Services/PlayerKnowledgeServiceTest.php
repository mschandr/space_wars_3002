<?php

namespace Tests\Unit\Services;

use App\Enums\Exploration\KnowledgeLevel;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PlayerSystemKnowledge;
use App\Models\PointOfInterest;
use App\Models\Sector;
use App\Models\Ship;
use App\Models\WarpGate;
use App\Services\PlayerKnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlayerKnowledgeServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlayerKnowledgeService $service;

    private Galaxy $galaxy;

    private Sector $sector;

    private Player $player;

    private PointOfInterest $starA;

    private PointOfInterest $starB;

    private PointOfInterest $starC;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PlayerKnowledgeService::class);

        // Create base galaxy & sector
        $this->galaxy = Galaxy::factory()->create(['width' => 1000, 'height' => 1000]);
        $this->sector = Sector::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'x_min' => 0,
            'x_max' => 100,
            'y_min' => 0,
            'y_max' => 100,
        ]);

        // Create star systems
        $this->starA = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'sector_id' => $this->sector->id,
            'x' => 10,
            'y' => 10,
            'name' => 'Star A',
            'is_inhabited' => true,
        ]);

        $this->starB = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'sector_id' => $this->sector->id,
            'x' => 13,
            'y' => 14,
            'name' => 'Star B',
            'is_inhabited' => true,
        ]);

        $this->starC = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'sector_id' => $this->sector->id,
            'x' => 20,
            'y' => 20,
            'name' => 'Star C',
            'is_inhabited' => false,
        ]);

        // Create player at Star A
        $this->player = Player::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->starA->id,
        ]);

        // Create active ship
        PlayerShip::factory()->active()->create([
            'player_id' => $this->player->id,
            'ship_id' => Ship::factory()->create()->id,
            'sensors' => 3,
        ]);
    }

    // -------------------------------------------------------
    // grantKnowledge tests
    // -------------------------------------------------------

    public function test_grant_knowledge_creates_new_record(): void
    {
        $result = $this->service->grantKnowledge(
            $this->player,
            $this->starB,
            KnowledgeLevel::DETECTED,
            'sensor',
        );

        $this->assertNotNull($result);
        $this->assertEquals($this->player->id, $result->player_id);
        $this->assertEquals($this->starB->id, $result->poi_id);
        $this->assertEquals(KnowledgeLevel::DETECTED->value, $result->knowledge_level);
        $this->assertEquals('sensor', $result->source);
    }

    public function test_grant_knowledge_upgrades_existing_record(): void
    {
        // First grant DETECTED
        $this->service->grantKnowledge(
            $this->player,
            $this->starB,
            KnowledgeLevel::DETECTED,
            'sensor',
        );

        // Then upgrade to BASIC
        $result = $this->service->grantKnowledge(
            $this->player,
            $this->starB,
            KnowledgeLevel::BASIC,
            'chart',
        );

        $this->assertEquals(KnowledgeLevel::BASIC->value, $result->knowledge_level);
        $this->assertEquals('chart', $result->source);

        // Should only have one record
        $count = PlayerSystemKnowledge::where('player_id', $this->player->id)
            ->where('poi_id', $this->starB->id)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_grant_knowledge_never_downgrades(): void
    {
        // First grant VISITED
        $this->service->grantKnowledge(
            $this->player,
            $this->starB,
            KnowledgeLevel::VISITED,
            'visit',
        );

        // Try to "downgrade" to DETECTED
        $result = $this->service->grantKnowledge(
            $this->player,
            $this->starB,
            KnowledgeLevel::DETECTED,
            'sensor',
        );

        // Should still be VISITED
        $this->assertEquals(KnowledgeLevel::VISITED->value, $result->knowledge_level);
        $this->assertEquals('visit', $result->source);
    }

    // -------------------------------------------------------
    // grantBulkKnowledge tests
    // -------------------------------------------------------

    public function test_bulk_grant_creates_multiple_records(): void
    {
        $entries = [
            ['poi_id' => $this->starA->id, 'level' => KnowledgeLevel::DETECTED, 'source' => 'warp_lane'],
            ['poi_id' => $this->starB->id, 'level' => KnowledgeLevel::BASIC, 'source' => 'chart'],
            ['poi_id' => $this->starC->id, 'level' => KnowledgeLevel::DETECTED, 'source' => 'sensor'],
        ];

        $count = $this->service->grantBulkKnowledge($this->player, $entries);

        $this->assertGreaterThan(0, $count);

        $totalKnowledge = PlayerSystemKnowledge::where('player_id', $this->player->id)->count();
        $this->assertEquals(3, $totalKnowledge);
    }

    // -------------------------------------------------------
    // markVisited tests
    // -------------------------------------------------------

    public function test_mark_visited_sets_visited_level(): void
    {
        $this->service->markVisited($this->player, $this->starA);

        $knowledge = PlayerSystemKnowledge::where('player_id', $this->player->id)
            ->where('poi_id', $this->starA->id)
            ->first();

        $this->assertNotNull($knowledge);
        $this->assertEquals(KnowledgeLevel::VISITED->value, $knowledge->knowledge_level);
        $this->assertEquals('visit', $knowledge->source);
    }

    public function test_mark_visited_discovers_warp_lane_endpoints(): void
    {
        // Create warp gate from A to B
        WarpGate::create([
            'uuid' => Str::uuid(),
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $this->starA->id,
            'destination_poi_id' => $this->starB->id,
            'source_x' => $this->starA->x,
            'source_y' => $this->starA->y,
            'dest_x' => $this->starB->x,
            'dest_y' => $this->starB->y,
            'is_hidden' => false,
            'status' => 'active',
            'distance' => 5.0,
        ]);

        $this->service->markVisited($this->player, $this->starA);

        // Star B should be discovered as DETECTED
        $knowledge = PlayerSystemKnowledge::where('player_id', $this->player->id)
            ->where('poi_id', $this->starB->id)
            ->first();

        $this->assertNotNull($knowledge);
        $this->assertGreaterThanOrEqual(KnowledgeLevel::DETECTED->value, $knowledge->knowledge_level);
    }

    public function test_inhabited_systems_discover_2_hops_out(): void
    {
        // A -> B -> C (all connected, A is inhabited)
        WarpGate::create([
            'uuid' => Str::uuid(),
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $this->starA->id,
            'destination_poi_id' => $this->starB->id,
            'source_x' => $this->starA->x,
            'source_y' => $this->starA->y,
            'dest_x' => $this->starB->x,
            'dest_y' => $this->starB->y,
            'is_hidden' => false,
            'status' => 'active',
            'distance' => 5.0,
        ]);

        WarpGate::create([
            'uuid' => Str::uuid(),
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $this->starB->id,
            'destination_poi_id' => $this->starC->id,
            'source_x' => $this->starB->x,
            'source_y' => $this->starB->y,
            'dest_x' => $this->starC->x,
            'dest_y' => $this->starC->y,
            'is_hidden' => false,
            'status' => 'active',
            'distance' => 10.0,
        ]);

        // Visit inhabited Star A → should discover 2 hops: B and C
        $this->service->markVisited($this->player, $this->starA);

        $knowledgeB = PlayerSystemKnowledge::where('player_id', $this->player->id)
            ->where('poi_id', $this->starB->id)
            ->first();
        $knowledgeC = PlayerSystemKnowledge::where('player_id', $this->player->id)
            ->where('poi_id', $this->starC->id)
            ->first();

        $this->assertNotNull($knowledgeB, 'Star B should be discovered (1 hop)');
        $this->assertNotNull($knowledgeC, 'Star C should be discovered (2 hops from inhabited)');
    }

    public function test_uninhabited_systems_discover_only_1_hop(): void
    {
        // Make starA uninhabited for this test
        $uninhabited = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'sector_id' => $this->sector->id,
            'x' => 50,
            'y' => 50,
            'name' => 'Frontier Star',
            'is_inhabited' => false,
        ]);

        // Frontier -> B -> C
        WarpGate::create([
            'uuid' => Str::uuid(),
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $uninhabited->id,
            'destination_poi_id' => $this->starB->id,
            'source_x' => $uninhabited->x,
            'source_y' => $uninhabited->y,
            'dest_x' => $this->starB->x,
            'dest_y' => $this->starB->y,
            'is_hidden' => false,
            'status' => 'active',
            'distance' => 5.0,
        ]);

        WarpGate::create([
            'uuid' => Str::uuid(),
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $this->starB->id,
            'destination_poi_id' => $this->starC->id,
            'source_x' => $this->starB->x,
            'source_y' => $this->starB->y,
            'dest_x' => $this->starC->x,
            'dest_y' => $this->starC->y,
            'is_hidden' => false,
            'status' => 'active',
            'distance' => 10.0,
        ]);

        // Visit uninhabited system → should only discover 1 hop (B), not C
        $this->service->markVisited($this->player, $uninhabited);

        $knowledgeB = PlayerSystemKnowledge::where('player_id', $this->player->id)
            ->where('poi_id', $this->starB->id)
            ->first();
        $knowledgeC = PlayerSystemKnowledge::where('player_id', $this->player->id)
            ->where('poi_id', $this->starC->id)
            ->first();

        $this->assertNotNull($knowledgeB, 'Star B should be discovered (1 hop)');
        $this->assertNull($knowledgeC, 'Star C should NOT be discovered (2 hops from uninhabited)');
    }

    // -------------------------------------------------------
    // Freshness / Decay tests
    // -------------------------------------------------------

    public function test_visited_knowledge_always_fresh(): void
    {
        $knowledge = PlayerSystemKnowledge::create([
            'player_id' => $this->player->id,
            'poi_id' => $this->starA->id,
            'knowledge_level' => KnowledgeLevel::VISITED->value,
            'source' => 'visit',
            'acquired_at' => now()->subDays(30),
        ]);

        $freshness = $this->service->calculateFreshness($knowledge);
        $this->assertEquals(1.0, $freshness);
    }

    public function test_chart_knowledge_decays_over_time(): void
    {
        // Fresh chart
        $fresh = PlayerSystemKnowledge::create([
            'player_id' => $this->player->id,
            'poi_id' => $this->starA->id,
            'knowledge_level' => KnowledgeLevel::SURVEYED->value,
            'source' => 'chart',
            'acquired_at' => now(),
        ]);

        $this->assertEqualsWithDelta(1.0, $this->service->calculateFreshness($fresh), 0.01);

        // Old chart (7+ days)
        $fresh->acquired_at = now()->subHours(170);
        $freshness = $this->service->calculateFreshness($fresh);
        $this->assertEqualsWithDelta(0.1, $freshness, 0.01);
    }

    public function test_effective_knowledge_level_degrades_with_staleness(): void
    {
        // SURVEYED from chart, but old
        $old = PlayerSystemKnowledge::create([
            'player_id' => $this->player->id,
            'poi_id' => $this->starA->id,
            'knowledge_level' => KnowledgeLevel::SURVEYED->value,
            'source' => 'chart',
            'acquired_at' => now()->subHours(150), // Very stale
        ]);

        $effectiveLevel = $this->service->getEffectiveKnowledgeLevel($old);
        // Should be degraded to DETECTED (floor)
        $this->assertEquals(KnowledgeLevel::DETECTED->value, $effectiveLevel);
    }

    public function test_visited_never_degrades(): void
    {
        $old = PlayerSystemKnowledge::create([
            'player_id' => $this->player->id,
            'poi_id' => $this->starA->id,
            'knowledge_level' => KnowledgeLevel::VISITED->value,
            'source' => 'visit',
            'acquired_at' => now()->subDays(365),
        ]);

        $effectiveLevel = $this->service->getEffectiveKnowledgeLevel($old);
        $this->assertEquals(KnowledgeLevel::VISITED->value, $effectiveLevel);
    }

    public function test_warp_lane_knowledge_never_degrades(): void
    {
        $old = PlayerSystemKnowledge::create([
            'player_id' => $this->player->id,
            'poi_id' => $this->starA->id,
            'knowledge_level' => KnowledgeLevel::DETECTED->value,
            'source' => 'warp_lane',
            'acquired_at' => now()->subDays(365),
        ]);

        $effectiveLevel = $this->service->getEffectiveKnowledgeLevel($old);
        $this->assertEquals(KnowledgeLevel::DETECTED->value, $effectiveLevel);
    }

    // -------------------------------------------------------
    // getKnowledgeMap tests
    // -------------------------------------------------------

    public function test_knowledge_map_includes_stored_knowledge(): void
    {
        $this->service->grantKnowledge(
            $this->player,
            $this->starA,
            KnowledgeLevel::VISITED,
            'visit',
        );

        $map = $this->service->getKnowledgeMap($this->player);

        $this->assertArrayHasKey($this->starA->id, $map);
        $this->assertEquals(KnowledgeLevel::VISITED->value, $map[$this->starA->id]['knowledge_level']);
    }

    public function test_knowledge_map_merges_sources_with_highest_level(): void
    {
        // Grant DETECTED via sensor
        $this->service->grantKnowledge(
            $this->player,
            $this->starB,
            KnowledgeLevel::DETECTED,
            'sensor',
        );

        // Grant VISITED via visit (higher)
        $this->service->grantKnowledge(
            $this->player,
            $this->starB,
            KnowledgeLevel::VISITED,
            'visit',
        );

        $map = $this->service->getKnowledgeMap($this->player);

        $this->assertArrayHasKey($this->starB->id, $map);
        $this->assertEquals(KnowledgeLevel::VISITED->value, $map[$this->starB->id]['knowledge_level']);
    }

    // -------------------------------------------------------
    // Player relationship test
    // -------------------------------------------------------

    public function test_player_has_system_knowledge_relationship(): void
    {
        $this->service->grantKnowledge(
            $this->player,
            $this->starA,
            KnowledgeLevel::VISITED,
            'visit',
        );

        $knowledge = $this->player->systemKnowledge;
        $this->assertCount(1, $knowledge);
        $this->assertEquals($this->starA->id, $knowledge->first()->poi_id);
    }
}

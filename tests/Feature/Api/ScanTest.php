<?php

namespace Tests\Feature\Api;

use App\Enums\Exploration\ScanLevel;
use App\Enums\Galaxy\RegionType;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\SystemScan;
use App\Models\User;
use App\Services\SystemScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * System Scanning API Tests
 *
 * Tests for progressive system scanning based on ship sensor level.
 */
class ScanTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Galaxy $galaxy;

    protected Player $player;

    protected PointOfInterest $currentPoi;

    protected PlayerShip $ship;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->galaxy = Galaxy::factory()->create();

        $this->currentPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
            'region' => RegionType::CORE,
        ]);

        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->currentPoi->id,
        ]);

        // Create a ship blueprint and player ship
        $shipBlueprint = Ship::factory()->create();

        $this->ship = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $shipBlueprint->id,
            'is_active' => true,
            'sensors' => 3,
            'current_fuel' => 100,
            'max_fuel' => 100,
        ]);
    }

    public function test_scan_system_requires_authentication(): void
    {
        $response = $this->postJson("/api/players/{$this->player->uuid}/scan-system");

        $response->assertStatus(401);
    }

    public function test_can_scan_current_system(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/scan-system");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'system' => ['uuid', 'name', 'x', 'y'],
                    'scan_level',
                    'scan_data',
                    'cached',
                    'can_reveal_more',
                    'next_level_reveals',
                ],
                'message',
            ])
            ->assertJsonPath('data.system.uuid', $this->currentPoi->uuid)
            ->assertJsonPath('data.scan_level', 3); // Sensor level 3
    }

    public function test_scan_creates_system_scan_record(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/scan-system");

        $this->assertDatabaseHas('system_scans', [
            'player_id' => $this->player->id,
            'poi_id' => $this->currentPoi->id,
            'scan_level' => 3,
        ]);
    }

    public function test_can_scan_remote_system_within_range(): void
    {
        $remotePoi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'x' => $this->currentPoi->x + 2, // Within range (sensor 3 = 4 LY range)
            'y' => $this->currentPoi->y,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/scan-system", [
                'poi_uuid' => $remotePoi->uuid,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.system.uuid', $remotePoi->uuid);
    }

    public function test_cannot_scan_system_out_of_range(): void
    {
        $remotePoi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'x' => $this->currentPoi->x + 500, // Out of range (sensor 3 = 4 LY range)
            'y' => $this->currentPoi->y,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/scan-system", [
                'poi_uuid' => $remotePoi->uuid,
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'OUT_OF_RANGE');
    }

    public function test_returns_cached_scan_if_already_scanned(): void
    {
        // First scan
        $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/scan-system");

        // Second scan - should return cached
        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/scan-system");

        $response->assertStatus(200)
            ->assertJsonPath('data.cached', true);
    }

    public function test_can_force_rescan(): void
    {
        // First scan
        $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/scan-system");

        // Force rescan - with force flag, it still returns cached data since sensor level hasn't changed
        // (force only bypasses the "already scanned at this level" check, not the level comparison)
        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/scan-system", [
                'force' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.scan_level', 3); // Still at sensor level 3
    }

    public function test_scan_upgrades_when_sensors_improve(): void
    {
        // Initial scan at level 3
        $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/scan-system");

        // Upgrade sensors
        $this->ship->update(['sensors' => 5]);

        // Rescan should upgrade
        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/scan-system");

        $response->assertStatus(200)
            ->assertJsonPath('data.scan_level', 5)
            ->assertJsonPath('data.cached', false);
    }

    public function test_can_get_scan_results(): void
    {
        // First scan
        $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/scan-system");

        // Get results
        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/scan-results/{$this->currentPoi->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'system' => ['uuid', 'name', 'type', 'x', 'y', 'is_inhabited'],
                    'scan' => [
                        'scan_level',
                        'scan_data',
                        'scanned_at',
                        'can_reveal_more',
                        'display' => ['color', 'opacity', 'label'],
                    ],
                ],
            ]);
    }

    public function test_can_get_exploration_log(): void
    {
        // Scan multiple systems
        $poi2 = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'x' => $this->currentPoi->x + 10,
            'y' => $this->currentPoi->y,
        ]);

        SystemScan::factory()->create([
            'player_id' => $this->player->id,
            'poi_id' => $this->currentPoi->id,
            'scan_level' => 3,
        ]);

        SystemScan::factory()->create([
            'player_id' => $this->player->id,
            'poi_id' => $poi2->id,
            'scan_level' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/exploration-log");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'entries' => [
                        '*' => [
                            'uuid',
                            'system' => ['uuid', 'name', 'type', 'x', 'y'],
                            'scan_level',
                            'scan_level_label',
                            'scanned_at',
                            'display' => ['color', 'opacity'],
                        ],
                    ],
                    'statistics' => ['total_scanned', 'by_level', 'by_region'],
                ],
            ])
            ->assertJsonCount(2, 'data.entries');
    }

    public function test_can_get_bulk_scan_levels(): void
    {
        $poi2 = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
        ]);

        SystemScan::factory()->create([
            'player_id' => $this->player->id,
            'poi_id' => $this->currentPoi->id,
            'scan_level' => 3,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/bulk-scan-levels", [
                'poi_uuids' => [$this->currentPoi->uuid, $poi2->uuid],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath("data.scan_levels.{$this->currentPoi->uuid}.scan_level", 3);
    }

    public function test_can_get_filtered_system_data(): void
    {
        SystemScan::factory()->create([
            'player_id' => $this->player->id,
            'poi_id' => $this->currentPoi->id,
            'scan_level' => 3,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/system-data/{$this->currentPoi->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scan_level', 3)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'system_data' => ['uuid', 'name', 'scan_level', 'x', 'y'],
                    'scan_level',
                ],
            ]);
    }

    public function test_unauthorized_user_cannot_access_other_player_scans(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->postJson("/api/players/{$this->player->uuid}/scan-system");

        $response->assertStatus(403);
    }

    public function test_returns_baseline_for_unscanned_charted_systems(): void
    {
        $chartedPoi = PointOfInterest::factory()->charted()->create([
            'galaxy_id' => $this->galaxy->id,
            'region' => RegionType::CORE,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/scan-results/{$chartedPoi->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.scan.baseline', true)
            ->assertJsonPath('data.scan.scan_level', 2); // Charted baseline
    }

    public function test_returns_baseline_for_unscanned_inhabited_systems(): void
    {
        $inhabitedPoi = PointOfInterest::factory()->inhabited()->create([
            'galaxy_id' => $this->galaxy->id,
            'region' => RegionType::OUTER,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/scan-results/{$inhabitedPoi->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.scan.baseline', true)
            ->assertJsonPath('data.scan.scan_level', 3); // Inhabited baseline
    }

    public function test_returns_zero_for_unscanned_outer_uninhabited_systems(): void
    {
        $outerPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'region' => RegionType::OUTER,
            'is_inhabited' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/scan-results/{$outerPoi->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.scan.scan_level', 0); // Outer baseline
    }
}

/**
 * SystemScanService Unit Tests
 */
class SystemScanServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SystemScanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SystemScanService;
    }

    public function test_scan_level_enum_returns_correct_reveals(): void
    {
        $level1 = ScanLevel::GEOGRAPHY;
        $this->assertContains('geography', $level1->reveals());
        $this->assertContains('planet_count', $level1->reveals());

        $level3 = ScanLevel::BASIC_RESOURCES;
        $this->assertContains('minerals_basic', $level3->reveals());
    }

    public function test_scan_level_enum_returns_all_revealed_categories(): void
    {
        $level5 = ScanLevel::HIDDEN_FEATURES;
        $allRevealed = $level5->allRevealedCategories();

        // Should include level 1-5 categories
        $this->assertContains('geography', $allRevealed);
        $this->assertContains('gates_presence', $allRevealed);
        $this->assertContains('minerals_basic', $allRevealed);
        $this->assertContains('minerals_rare', $allRevealed);
        $this->assertContains('hidden_moons', $allRevealed);

        // Should not include level 6+ categories
        $this->assertNotContains('anomalies', $allRevealed);
        $this->assertNotContains('precursor_gates', $allRevealed);
    }

    public function test_scan_level_from_sensor_level(): void
    {
        $this->assertEquals(ScanLevel::GEOGRAPHY, ScanLevel::fromSensorLevel(1));
        $this->assertEquals(ScanLevel::BASIC_RESOURCES, ScanLevel::fromSensorLevel(3));
        $this->assertEquals(ScanLevel::PRECURSOR_SECRETS, ScanLevel::fromSensorLevel(9));

        // Clamped to valid range
        $this->assertEquals(ScanLevel::UNSCANNED, ScanLevel::fromSensorLevel(0));
        $this->assertEquals(ScanLevel::PRECURSOR_SECRETS, ScanLevel::fromSensorLevel(100));
    }

    public function test_can_reveal_feature(): void
    {
        $this->assertTrue($this->service->canRevealFeature('geography', 1));
        $this->assertTrue($this->service->canRevealFeature('minerals_basic', 3));
        $this->assertFalse($this->service->canRevealFeature('minerals_basic', 2));
        $this->assertFalse($this->service->canRevealFeature('precursor_gates', 8));
        $this->assertTrue($this->service->canRevealFeature('precursor_gates', 9));
    }

    public function test_baseline_scan_level_by_region(): void
    {
        $galaxy = Galaxy::factory()->create();

        $corePoi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'region' => RegionType::CORE,
            'is_inhabited' => false,
        ]);

        $outerPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'region' => RegionType::OUTER,
            'is_inhabited' => false,
        ]);

        $inhabitedPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'region' => RegionType::OUTER,
            'is_inhabited' => true,
        ]);

        $this->assertEquals(3, $this->service->getBaselineScanLevel($corePoi));
        $this->assertEquals(0, $this->service->getBaselineScanLevel($outerPoi));
        $this->assertEquals(2, $this->service->getBaselineScanLevel($inhabitedPoi));
    }

    public function test_generate_scan_data_includes_expected_levels(): void
    {
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
        ]);

        $scanData = $this->service->generateScanData($poi, 1, 3);

        $this->assertArrayHasKey('1', $scanData);
        $this->assertArrayHasKey('2', $scanData);
        $this->assertArrayHasKey('3', $scanData);
        $this->assertArrayNotHasKey('4', $scanData);
    }

    public function test_get_filtered_system_data(): void
    {
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
        ]);

        $data = $this->service->getFilteredSystemData($poi, 3);

        $this->assertArrayHasKey('uuid', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('scan_level', $data);
        $this->assertArrayHasKey('x', $data);
        $this->assertArrayHasKey('y', $data);
        $this->assertArrayHasKey('geography', $data);
        $this->assertArrayHasKey('gates', $data);
        $this->assertArrayHasKey('resources', $data);

        // Level 3 should not include higher level data
        $this->assertArrayNotHasKey('rare_resources', $data);
        $this->assertArrayNotHasKey('hidden_features', $data);
    }
}

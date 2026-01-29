<?php

namespace Tests\Unit\Services;

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
use App\Models\WarpGate;
use App\Services\SystemScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for SystemScanService
 */
class SystemScanServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SystemScanService $service;

    protected Galaxy $galaxy;

    protected Player $player;

    protected PlayerShip $ship;

    protected PointOfInterest $starSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SystemScanService;
        $this->galaxy = Galaxy::factory()->create();

        $user = User::factory()->create();

        $this->starSystem = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
            'region' => RegionType::CORE,
            'attributes' => ['stellar_class' => 'G'],
        ]);

        $this->player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->starSystem->id,
        ]);

        $shipBlueprint = Ship::factory()->create();
        $this->ship = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $shipBlueprint->id,
            'is_active' => true,
            'sensors' => 3,
        ]);
    }

    // =========================================================================
    // scanSystem() Tests
    // =========================================================================

    public function test_scan_system_creates_new_scan_record(): void
    {
        $result = $this->service->scanSystem($this->player, $this->starSystem);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['scan_level']);
        $this->assertFalse($result['cached']);
        $this->assertDatabaseHas('system_scans', [
            'player_id' => $this->player->id,
            'poi_id' => $this->starSystem->id,
            'scan_level' => 3,
        ]);
    }

    public function test_scan_system_returns_cached_when_already_scanned_at_same_level(): void
    {
        // First scan
        $this->service->scanSystem($this->player, $this->starSystem);

        // Second scan
        $result = $this->service->scanSystem($this->player, $this->starSystem);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['cached']);
    }

    public function test_scan_system_upgrades_when_sensors_improved(): void
    {
        // Initial scan at level 3
        $this->service->scanSystem($this->player, $this->starSystem);

        // Upgrade sensors to 5
        $this->ship->update(['sensors' => 5]);
        $this->player->refresh();

        // Re-scan with new service instance (to clear internal cache)
        $newService = new SystemScanService;
        $result = $newService->scanSystem($this->player, $this->starSystem);

        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['scan_level']);
        $this->assertFalse($result['cached']);
        // new_discoveries contains string keys of new levels scanned
        $this->assertNotEmpty($result['new_discoveries']);
    }

    public function test_scan_system_fails_without_active_ship(): void
    {
        $this->ship->update(['is_active' => false]);
        $this->player->refresh();

        $result = $this->service->scanSystem($this->player, $this->starSystem);

        $this->assertFalse($result['success']);
        $this->assertEquals('No active ship', $result['message']);
    }

    public function test_scan_system_force_flag_bypasses_cache_check(): void
    {
        // First scan
        $this->service->scanSystem($this->player, $this->starSystem);

        // Force re-scan - even at same level, force=true bypasses cache
        $newService = new SystemScanService;
        $result = $newService->scanSystem($this->player, $this->starSystem, true);

        $this->assertTrue($result['success']);
        // With force=true, we go through the update path, so cached=false
        $this->assertFalse($result['cached']);
        $this->assertEquals(3, $result['scan_level']); // Still at same level
    }

    public function test_scan_system_returns_can_reveal_more_correctly(): void
    {
        $this->ship->update(['sensors' => 9]);
        $this->player->refresh();

        $result = $this->service->scanSystem($this->player, $this->starSystem);

        $this->assertFalse($result['can_reveal_more']); // At max level

        $this->ship->update(['sensors' => 5]);
        $this->player->refresh();

        // Create new service instance to clear cache
        $service = new SystemScanService;
        $newSystem = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
        ]);

        $result = $service->scanSystem($this->player, $newSystem);

        $this->assertTrue($result['can_reveal_more']); // Not at max level
    }

    // =========================================================================
    // getScanResults() Tests
    // =========================================================================

    public function test_get_scan_results_returns_existing_scan(): void
    {
        SystemScan::factory()->create([
            'player_id' => $this->player->id,
            'poi_id' => $this->starSystem->id,
            'scan_level' => 5,
            'scan_data' => ['1' => ['test' => 'data']],
        ]);

        $result = $this->service->getScanResults($this->player, $this->starSystem);

        $this->assertEquals(5, $result['scan_level']);
        $this->assertNotNull($result['scanned_at']);
        $this->assertArrayHasKey('display', $result);
    }

    public function test_get_scan_results_returns_baseline_for_core_unscanned(): void
    {
        $corePoi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'region' => RegionType::CORE,
            'is_inhabited' => false,
        ]);

        $result = $this->service->getScanResults($this->player, $corePoi);

        $this->assertEquals(3, $result['scan_level']); // Core baseline
        $this->assertTrue($result['baseline']);
        $this->assertNull($result['scanned_at']);
    }

    public function test_get_scan_results_returns_baseline_for_inhabited_unscanned(): void
    {
        $inhabitedPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'region' => RegionType::OUTER,
            'is_inhabited' => true,
        ]);

        $result = $this->service->getScanResults($this->player, $inhabitedPoi);

        $this->assertEquals(2, $result['scan_level']); // Inhabited baseline
        $this->assertTrue($result['baseline']);
    }

    public function test_get_scan_results_returns_zero_for_outer_uninhabited(): void
    {
        $outerPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'region' => RegionType::OUTER,
            'is_inhabited' => false,
        ]);

        $result = $this->service->getScanResults($this->player, $outerPoi);

        $this->assertEquals(0, $result['scan_level']); // Outer baseline = fog
        $this->assertTrue($result['can_reveal_more']);
    }

    // =========================================================================
    // getFilteredSystemData() Tests
    // =========================================================================

    public function test_get_filtered_system_data_includes_geography_at_level_1(): void
    {
        $data = $this->service->getFilteredSystemData($this->starSystem, 1);

        $this->assertArrayHasKey('uuid', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('coordinates', $data);
        $this->assertArrayHasKey('geography', $data);
        $this->assertArrayNotHasKey('gates', $data);
        $this->assertArrayNotHasKey('resources', $data);
    }

    public function test_get_filtered_system_data_includes_gates_at_level_2(): void
    {
        $data = $this->service->getFilteredSystemData($this->starSystem, 2);

        $this->assertArrayHasKey('geography', $data);
        $this->assertArrayHasKey('gates', $data);
        $this->assertArrayNotHasKey('resources', $data);
    }

    public function test_get_filtered_system_data_includes_resources_at_level_3(): void
    {
        $data = $this->service->getFilteredSystemData($this->starSystem, 3);

        $this->assertArrayHasKey('geography', $data);
        $this->assertArrayHasKey('gates', $data);
        $this->assertArrayHasKey('resources', $data);
        $this->assertArrayNotHasKey('rare_resources', $data);
    }

    public function test_get_filtered_system_data_includes_all_at_level_9(): void
    {
        $data = $this->service->getFilteredSystemData($this->starSystem, 9);

        $this->assertArrayHasKey('geography', $data);
        $this->assertArrayHasKey('gates', $data);
        $this->assertArrayHasKey('resources', $data);
        $this->assertArrayHasKey('rare_resources', $data);
        $this->assertArrayHasKey('hidden_features', $data);
        $this->assertArrayHasKey('anomalies', $data);
        $this->assertArrayHasKey('deep_scan', $data);
        $this->assertArrayHasKey('intel', $data);
        $this->assertArrayHasKey('precursor', $data);
    }

    // =========================================================================
    // canRevealFeature() Tests
    // =========================================================================

    public function test_can_reveal_feature_geography_at_level_1(): void
    {
        $this->assertTrue($this->service->canRevealFeature('geography', 1));
        $this->assertTrue($this->service->canRevealFeature('planet_count', 1));
    }

    public function test_can_reveal_feature_gates_at_level_2(): void
    {
        $this->assertFalse($this->service->canRevealFeature('gates_presence', 1));
        $this->assertTrue($this->service->canRevealFeature('gates_presence', 2));
    }

    public function test_can_reveal_feature_minerals_at_level_3(): void
    {
        $this->assertFalse($this->service->canRevealFeature('minerals_basic', 2));
        $this->assertTrue($this->service->canRevealFeature('minerals_basic', 3));
    }

    public function test_can_reveal_feature_precursor_at_level_9(): void
    {
        $this->assertFalse($this->service->canRevealFeature('precursor_gates', 8));
        $this->assertTrue($this->service->canRevealFeature('precursor_gates', 9));
    }

    // =========================================================================
    // generateScanData() Tests
    // =========================================================================

    public function test_generate_scan_data_creates_correct_levels(): void
    {
        $data = $this->service->generateScanData($this->starSystem, 1, 3);

        $this->assertArrayHasKey('1', $data);
        $this->assertArrayHasKey('2', $data);
        $this->assertArrayHasKey('3', $data);
        $this->assertArrayNotHasKey('4', $data);
    }

    public function test_generate_scan_data_partial_range(): void
    {
        $data = $this->service->generateScanData($this->starSystem, 3, 5);

        $this->assertArrayNotHasKey('1', $data);
        $this->assertArrayNotHasKey('2', $data);
        $this->assertArrayHasKey('3', $data);
        $this->assertArrayHasKey('4', $data);
        $this->assertArrayHasKey('5', $data);
    }

    public function test_generate_scan_data_includes_star_type(): void
    {
        $data = $this->service->generateScanData($this->starSystem, 1, 1);

        $this->assertArrayHasKey('star_type', $data['1']);
        $this->assertStringContainsString('G', $data['1']['star_type']);
    }

    // =========================================================================
    // getPlayerScannedSystems() Tests
    // =========================================================================

    public function test_get_player_scanned_systems_returns_all_scans(): void
    {
        $poi1 = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $poi2 = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);

        SystemScan::factory()->create([
            'player_id' => $this->player->id,
            'poi_id' => $poi1->id,
            'scanned_at' => now()->subHour(),
        ]);

        SystemScan::factory()->create([
            'player_id' => $this->player->id,
            'poi_id' => $poi2->id,
            'scanned_at' => now(),
        ]);

        $scans = $this->service->getPlayerScannedSystems($this->player);

        $this->assertCount(2, $scans);
        // Most recent first
        $this->assertEquals($poi2->id, $scans->first()->poi_id);
    }

    public function test_get_player_scanned_systems_includes_poi_relationship(): void
    {
        SystemScan::factory()->create([
            'player_id' => $this->player->id,
            'poi_id' => $this->starSystem->id,
        ]);

        $scans = $this->service->getPlayerScannedSystems($this->player);

        $this->assertTrue($scans->first()->relationLoaded('pointOfInterest'));
        $this->assertEquals($this->starSystem->name, $scans->first()->pointOfInterest->name);
    }

    // =========================================================================
    // getScanLevelFor() Tests
    // =========================================================================

    public function test_get_scan_level_for_returns_scan_level(): void
    {
        SystemScan::factory()->create([
            'player_id' => $this->player->id,
            'poi_id' => $this->starSystem->id,
            'scan_level' => 7,
        ]);

        $level = $this->service->getScanLevelFor($this->player, $this->starSystem);

        $this->assertEquals(7, $level);
    }

    public function test_get_scan_level_for_returns_baseline_when_unscanned(): void
    {
        $level = $this->service->getScanLevelFor($this->player, $this->starSystem);

        // Core + inhabited = should use inhabited baseline (2) since is_inhabited takes priority
        $this->assertEquals(2, $level);
    }

    // =========================================================================
    // getBulkScanLevels() Tests
    // =========================================================================

    public function test_get_bulk_scan_levels_returns_all_poi_levels(): void
    {
        $poi1 = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'region' => RegionType::OUTER,
            'is_inhabited' => false,
        ]);
        $poi2 = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'region' => RegionType::CORE,
            'is_inhabited' => false,
        ]);

        SystemScan::factory()->create([
            'player_id' => $this->player->id,
            'poi_id' => $poi1->id,
            'scan_level' => 5,
        ]);

        $levels = $this->service->getBulkScanLevels($this->player, [$poi1->id, $poi2->id]);

        $this->assertEquals(5, $levels[$poi1->id]); // Scanned level
        $this->assertEquals(3, $levels[$poi2->id]); // Core baseline
    }

    // =========================================================================
    // getBaselineScanLevel() Tests
    // =========================================================================

    public function test_get_baseline_scan_level_core_region(): void
    {
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'region' => RegionType::CORE,
            'is_inhabited' => false,
        ]);

        $level = $this->service->getBaselineScanLevel($poi);

        $this->assertEquals(3, $level);
    }

    public function test_get_baseline_scan_level_outer_uninhabited(): void
    {
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'region' => RegionType::OUTER,
            'is_inhabited' => false,
        ]);

        $level = $this->service->getBaselineScanLevel($poi);

        $this->assertEquals(0, $level);
    }

    public function test_get_baseline_scan_level_inhabited_takes_priority(): void
    {
        // Inhabited in outer region still gets inhabited baseline
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'region' => RegionType::OUTER,
            'is_inhabited' => true,
        ]);

        $level = $this->service->getBaselineScanLevel($poi);

        $this->assertEquals(2, $level);
    }

    // =========================================================================
    // Scan Data Generation - Geography
    // =========================================================================

    public function test_geography_scan_includes_planet_types(): void
    {
        // Create child planets
        PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'parent_poi_id' => $this->starSystem->id,
            'type' => PointOfInterestType::TERRESTRIAL,
        ]);

        PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'parent_poi_id' => $this->starSystem->id,
            'type' => PointOfInterestType::GAS_GIANT,
        ]);

        $data = $this->service->generateScanData($this->starSystem, 1, 1);

        $this->assertArrayHasKey('planet_count', $data['1']);
        $this->assertArrayHasKey('planet_types', $data['1']);
        $this->assertEquals(2, $data['1']['planet_count']);
    }

    public function test_geography_scan_includes_asteroid_belts(): void
    {
        PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'parent_poi_id' => $this->starSystem->id,
            'type' => PointOfInterestType::ASTEROID_BELT,
        ]);

        $data = $this->service->generateScanData($this->starSystem, 1, 1);

        $this->assertEquals(1, $data['1']['asteroid_belts']);
    }

    // =========================================================================
    // Scan Data Generation - Gates
    // =========================================================================

    public function test_gate_scan_includes_gate_count(): void
    {
        WarpGate::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $this->starSystem->id,
            'destination_poi_id' => PointOfInterest::factory()->create([
                'galaxy_id' => $this->galaxy->id,
            ])->id,
            'is_hidden' => false,
            'status' => 'active',
        ]);

        $data = $this->service->generateScanData($this->starSystem, 2, 2);

        $this->assertEquals(1, $data['2']['gate_count']);
        $this->assertEquals(1, $data['2']['active_gates']);
    }

    public function test_gate_scan_includes_dormant_gates(): void
    {
        WarpGate::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $this->starSystem->id,
            'destination_poi_id' => PointOfInterest::factory()->create([
                'galaxy_id' => $this->galaxy->id,
            ])->id,
            'is_hidden' => false,
            'status' => 'dormant',
        ]);

        $data = $this->service->generateScanData($this->starSystem, 2, 2);

        $this->assertEquals(1, $data['2']['dormant_gates']);
    }
}

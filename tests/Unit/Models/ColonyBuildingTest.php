<?php

namespace Tests\Unit\Models;

use App\Models\Colony;
use App\Models\ColonyBuilding;
use App\Models\Player;
use App\Models\PointOfInterest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Colony Building System Tests
 *
 * Critical game mechanics:
 * - Building costs scale 50% per level
 * - Building effects scale 30% per level
 * - Operating costs scale 20% per level
 * - Warp gates require stage 5, consume 1 Quantium/hr, generate 600 credits/hr
 * - Buildings shut down when resources run out
 *
 * @see /TESTING_ROADMAP.md#7-building-system-tests
 */
class ColonyBuildingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_buildings_require_correct_stage()
    {
        // Hab Module requires stage 1
        $this->assertTrue(ColonyBuilding::canBuildAtStage('hab_module', 1));
        $this->assertTrue(ColonyBuilding::canBuildAtStage('hab_module', 5));

        // Warp Gate requires stage 5
        $this->assertFalse(ColonyBuilding::canBuildAtStage('warp_gate', 1));
        $this->assertFalse(ColonyBuilding::canBuildAtStage('warp_gate', 4));
        $this->assertTrue(ColonyBuilding::canBuildAtStage('warp_gate', 5));

        // Orbital Sensor requires stage 8
        $this->assertFalse(ColonyBuilding::canBuildAtStage('orbital_sensor', 7));
        $this->assertTrue(ColonyBuilding::canBuildAtStage('orbital_sensor', 8));
    }

    /** @test */
    public function test_warp_gate_requires_stage_5()
    {
        // Explicitly test the critical warp gate requirement
        $this->assertFalse(ColonyBuilding::canBuildAtStage('warp_gate', 1));
        $this->assertFalse(ColonyBuilding::canBuildAtStage('warp_gate', 2));
        $this->assertFalse(ColonyBuilding::canBuildAtStage('warp_gate', 3));
        $this->assertFalse(ColonyBuilding::canBuildAtStage('warp_gate', 4));
        $this->assertTrue(ColonyBuilding::canBuildAtStage('warp_gate', 5));
        $this->assertTrue(ColonyBuilding::canBuildAtStage('warp_gate', 6));
    }

    /** @test */
    public function test_building_costs_scale_by_level()
    {
        // Critical formula: 50% increase per level
        // Level 1: Base cost
        $level1Costs = ColonyBuilding::getBuildingCosts('hydroponics', 1);
        $this->assertEquals(8000, $level1Costs['credits']);
        $this->assertEquals(3000, $level1Costs['minerals']);
        $this->assertEquals(15, $level1Costs['population']);

        // Level 2: Base * 1.5 (50% increase)
        $level2Costs = ColonyBuilding::getBuildingCosts('hydroponics', 2);
        $this->assertEquals(12000, $level2Costs['credits']); // 8000 * 1.5
        $this->assertEquals(4500, $level2Costs['minerals']); // 3000 * 1.5
        $this->assertEquals(22, $level2Costs['population']); // 15 * 1.5 (rounded down)

        // Level 3: Base * 2.0 (100% increase)
        $level3Costs = ColonyBuilding::getBuildingCosts('hydroponics', 3);
        $this->assertEquals(16000, $level3Costs['credits']); // 8000 * 2.0
        $this->assertEquals(6000, $level3Costs['minerals']); // 3000 * 2.0
        $this->assertEquals(30, $level3Costs['population']); // 15 * 2.0
    }

    /** @test */
    public function test_building_effects_scale_by_level()
    {
        // Critical formula: 30% increase per level
        // Hydroponics: base food_production = 200

        // Level 1: Base effect
        $level1Effects = ColonyBuilding::getBuildingEffects('hydroponics', 1);
        $this->assertEquals(200, $level1Effects['food_production']);

        // Level 2: Base * 1.3 (30% increase)
        $level2Effects = ColonyBuilding::getBuildingEffects('hydroponics', 2);
        $this->assertEquals(260, $level2Effects['food_production']); // 200 * 1.3

        // Level 3: Base * 1.6 (60% increase)
        $level3Effects = ColonyBuilding::getBuildingEffects('hydroponics', 3);
        $this->assertEquals(320, $level3Effects['food_production']); // 200 * 1.6

        // Level 5: Base * 2.2 (120% increase)
        $level5Effects = ColonyBuilding::getBuildingEffects('hydroponics', 5);
        $this->assertEquals(440, $level5Effects['food_production']); // 200 * 2.2
    }

    /** @test */
    public function test_operating_costs_scale_by_level()
    {
        // Critical formula: 20% increase per level
        // Warp Gate: base quantium = 1, credits = 0

        // Level 1: Base costs
        $level1Costs = ColonyBuilding::getOperatingCosts('warp_gate', 1);
        $this->assertEquals(0, $level1Costs['credits']);
        $this->assertEquals(1, $level1Costs['quantium']);

        // Level 2: Base * 1.2 (20% increase)
        $level2Costs = ColonyBuilding::getOperatingCosts('warp_gate', 2);
        $this->assertEquals(1, $level2Costs['quantium']); // 1 * 1.2 = 1.2 → 1 (int cast)

        // Level 3: Base * 1.4 (40% increase)
        $level3Costs = ColonyBuilding::getOperatingCosts('warp_gate', 3);
        $this->assertEquals(1, $level3Costs['quantium']); // 1 * 1.4 = 1.4 → 1 (int cast)

        // Test building with higher base costs
        // Orbital Defense: base credits = 100
        $level1Defense = ColonyBuilding::getOperatingCosts('orbital_defense', 1);
        $this->assertEquals(100, $level1Defense['credits']);

        $level2Defense = ColonyBuilding::getOperatingCosts('orbital_defense', 2);
        $this->assertEquals(120, $level2Defense['credits']); // 100 * 1.2
    }

    /** @test */
    public function test_warp_gate_consumes_1_quantium_per_cycle()
    {
        // Critical game mechanic: Warp gates consume exactly 1 Quantium per hour
        $operatingCosts = ColonyBuilding::getOperatingCosts('warp_gate', 1);

        $this->assertEquals(1, $operatingCosts['quantium']);
        $this->assertEquals(0, $operatingCosts['credits']);
        $this->assertEquals(0, $operatingCosts['food']);
        $this->assertEquals(0, $operatingCosts['minerals']);
    }

    /** @test */
    public function test_warp_gate_generates_600_credits_per_cycle()
    {
        // Critical game mechanic: Warp gates generate 600 credits per hour
        $income = ColonyBuilding::getIncomeGenerated('warp_gate', 1);

        $this->assertEquals(600, $income);
    }

    /** @test */
    public function test_gate_shuts_down_when_quantium_reaches_zero()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Test Colony',
            'population' => 1000,
            'max_population' => 5000,
            'development_level' => 5,
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
            'quantium_storage' => 0, // No Quantium
            'food_storage' => 1000,
            'mineral_storage' => 1000,
        ]);

        $warpGate = ColonyBuilding::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'colony_id' => $colony->id,
            'building_type' => 'warp_gate',
            'required_stage' => 5,
            'level' => 1,
            'status' => 'operational',
            'quantium_per_cycle' => 1,
            'credits_generated_per_cycle' => 600,
        ]);

        $this->assertEquals('operational', $warpGate->status);

        // Process cycle with no Quantium
        $log = $warpGate->processCycle($colony);

        // Gate should shut down
        $warpGate->refresh();
        $this->assertEquals('damaged', $warpGate->status);
        $this->assertStringContainsString('shut down', $log[0]);
        $this->assertStringContainsString('Quantium', $log[0]);
    }

    /** @test */
    public function test_orbital_defense_costs_100_credits_per_cycle()
    {
        // Critical game mechanic: Orbital Defense costs 100 credits per cycle
        $operatingCosts = ColonyBuilding::getOperatingCosts('orbital_defense', 1);

        $this->assertEquals(100, $operatingCosts['credits']);
    }

    /** @test */
    public function test_building_generates_income_when_operational()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Test Colony',
            'population' => 1000,
            'max_population' => 5000,
            'development_level' => 5,
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
            'quantium_storage' => 100,
            'food_storage' => 1000,
            'mineral_storage' => 1000,
            'credits_per_cycle' => 0,
        ]);

        $warpGate = ColonyBuilding::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'colony_id' => $colony->id,
            'building_type' => 'warp_gate',
            'required_stage' => 5,
            'level' => 1,
            'status' => 'operational',
            'quantium_per_cycle' => 1,
            'credits_generated_per_cycle' => 600,
        ]);

        $this->assertEquals(0, $colony->credits_per_cycle);

        // Process cycle
        $log = $warpGate->processCycle($colony);

        // Should generate 600 credits (method modifies colony but doesn't save)
        $colony->save(); // Save changes made by processCycle
        $colony->refresh();
        $this->assertEquals(600, $colony->credits_per_cycle);
        $this->assertStringContainsString('600 credits', $log[0]);
    }

    /** @test */
    public function test_building_construction_advances_progress()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Test Colony',
            'population' => 1000,
            'max_population' => 5000,
            'development_level' => 1,
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        $building = ColonyBuilding::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'colony_id' => $colony->id,
            'building_type' => 'hydroponics',
            'required_stage' => 1,
            'level' => 1,
            'status' => 'constructing',
            'construction_progress' => 0,
        ]);

        $this->assertEquals(0, $building->construction_progress);
        $this->assertEquals('constructing', $building->status);

        // Advance construction
        $building->advanceConstruction(50);

        $this->assertEquals(50, $building->construction_progress);
        $this->assertEquals('constructing', $building->status);
    }

    /** @test */
    public function test_building_becomes_operational_when_construction_complete()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Test Colony',
            'population' => 1000,
            'max_population' => 5000,
            'development_level' => 1,
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        $building = ColonyBuilding::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'colony_id' => $colony->id,
            'building_type' => 'hydroponics',
            'required_stage' => 1,
            'level' => 1,
            'status' => 'constructing',
            'construction_progress' => 90,
        ]);

        // Complete construction
        $building->advanceConstruction(20); // 90 + 20 = 110, capped at 100

        $building->refresh();
        $this->assertEquals(100, $building->construction_progress);
        $this->assertEquals('operational', $building->status);
        $this->assertNotNull($building->construction_completed_at);
    }

    /** @test */
    public function test_operational_building_sets_costs_and_income_automatically()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Test Colony',
            'population' => 1000,
            'max_population' => 5000,
            'development_level' => 5,
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        $building = ColonyBuilding::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'colony_id' => $colony->id,
            'building_type' => 'warp_gate',
            'required_stage' => 5,
            'level' => 1,
            'status' => 'constructing',
            'construction_progress' => 99,
        ]);

        $this->assertNull($building->quantium_per_cycle);
        $this->assertNull($building->credits_generated_per_cycle);

        // Complete construction
        $building->advanceConstruction(1);

        $building->refresh();
        $this->assertEquals(1, $building->quantium_per_cycle);
        $this->assertEquals(600, $building->credits_generated_per_cycle);
    }

    /** @test */
    public function test_building_upgrade_increases_level()
    {
        $player = Player::factory()->rich(1000000)->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Test Colony',
            'population' => 1000,
            'max_population' => 5000,
            'development_level' => 1,
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        $building = ColonyBuilding::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'colony_id' => $colony->id,
            'building_type' => 'hydroponics',
            'required_stage' => 1,
            'level' => 1,
            'status' => 'operational',
        ]);

        $this->assertEquals(1, $building->level);

        $result = $building->upgrade();

        $this->assertTrue($result);
        $building->refresh();
        $this->assertEquals(2, $building->level);
        $this->assertEquals('constructing', $building->status); // Back to constructing
    }

    /** @test */
    public function test_building_upgrade_fails_at_max_level()
    {
        $player = Player::factory()->rich(1000000)->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Test Colony',
            'population' => 1000,
            'max_population' => 5000,
            'development_level' => 1,
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        $building = ColonyBuilding::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'colony_id' => $colony->id,
            'building_type' => 'hydroponics',
            'required_stage' => 1,
            'level' => 5, // Max level
            'status' => 'operational',
        ]);

        $result = $building->upgrade();

        $this->assertFalse($result);
        $building->refresh();
        $this->assertEquals(5, $building->level); // Unchanged
    }

    /** @test */
    public function test_building_upgrade_fails_with_insufficient_credits()
    {
        $player = Player::factory()->broke()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Test Colony',
            'population' => 1000,
            'max_population' => 5000,
            'development_level' => 1,
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        $building = ColonyBuilding::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'colony_id' => $colony->id,
            'building_type' => 'hydroponics',
            'required_stage' => 1,
            'level' => 1,
            'status' => 'operational',
        ]);

        $result = $building->upgrade();

        $this->assertFalse($result);
        $building->refresh();
        $this->assertEquals(1, $building->level); // Unchanged
    }

    /** @test */
    public function test_income_scales_with_level()
    {
        // Warp gate base income: 600 credits
        // Income scales 50% per level
        $level1Income = ColonyBuilding::getIncomeGenerated('warp_gate', 1);
        $this->assertEquals(600, $level1Income);

        $level2Income = ColonyBuilding::getIncomeGenerated('warp_gate', 2);
        $this->assertEquals(900, $level2Income); // 600 * 1.5

        $level3Income = ColonyBuilding::getIncomeGenerated('warp_gate', 3);
        $this->assertEquals(1200, $level3Income); // 600 * 2.0
    }

    /** @test */
    public function test_buildings_with_no_income_return_zero()
    {
        // Hydroponics generates no income
        $income = ColonyBuilding::getIncomeGenerated('hydroponics', 1);
        $this->assertEquals(0, $income);

        // Even at higher levels
        $income = ColonyBuilding::getIncomeGenerated('hydroponics', 5);
        $this->assertEquals(0, $income);
    }

    /** @test */
    public function test_boolean_effects_dont_scale()
    {
        // Warp gate has 'gate_operational' => true
        $level1Effects = ColonyBuilding::getBuildingEffects('warp_gate', 1);
        $this->assertTrue($level1Effects['gate_operational']);

        // Boolean should remain unchanged at higher levels
        $level5Effects = ColonyBuilding::getBuildingEffects('warp_gate', 5);
        $this->assertTrue($level5Effects['gate_operational']);
    }

    /** @test */
    public function test_building_factory_creates_valid_building()
    {
        $building = ColonyBuilding::factory()->create();

        $this->assertNotNull($building->uuid);
        $this->assertNotNull($building->colony_id);
        $this->assertNotNull($building->building_type);
        $this->assertGreaterThanOrEqual(1, $building->level);
    }

    /** @test */
    public function test_building_uuid_is_auto_generated()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Test Colony',
            'population' => 1000,
            'max_population' => 5000,
            'development_level' => 1,
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        $building = ColonyBuilding::create([
            'uuid' => null,
            'colony_id' => $colony->id,
            'building_type' => 'hydroponics',
            'required_stage' => 1,
            'level' => 1,
            'status' => 'constructing',
        ]);

        $this->assertNotNull($building->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            (string) $building->uuid
        );
    }

    /** @test */
    public function test_building_consumes_resources_during_cycle()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Test Colony',
            'population' => 1000,
            'max_population' => 5000,
            'development_level' => 1,
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
            'quantium_storage' => 100,
            'food_storage' => 100,
            'mineral_storage' => 100,
        ]);

        $building = ColonyBuilding::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'colony_id' => $colony->id,
            'building_type' => 'hydroponics',
            'required_stage' => 1,
            'level' => 1,
            'status' => 'operational',
            'credits_per_cycle' => 10,
            'quantium_per_cycle' => 0,
            'food_per_cycle' => 0,
            'minerals_per_cycle' => 5,
        ]);

        $initialMinerals = $colony->mineral_storage;

        // Process cycle
        $building->processCycle($colony);

        // Save changes made by processCycle
        $colony->save();
        $colony->refresh();
        $this->assertEquals($initialMinerals - 5, $colony->mineral_storage);
    }
}

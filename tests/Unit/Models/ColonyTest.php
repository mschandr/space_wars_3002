<?php

namespace Tests\Unit\Models;

use App\Models\Colony;
use App\Models\ColonyBuilding;
use App\Models\Player;
use App\Models\PointOfInterest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColonyTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_colony_population_grows_each_cycle()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Test Colony',
            'population' => 1000,
            'population_growth_rate' => 0.02, // 2% growth
            'max_population' => 10000,
            'food_production' => 150, // Sufficient food
            'habitability_rating' => 1.0,
            'development_level' => 1,
            'status' => 'established',
        ]);

        $initialPopulation = $colony->population;
        $colony->processGrowth();

        $this->assertGreaterThan($initialPopulation, $colony->fresh()->population);
    }

    /** @test */
    public function test_growth_rate_affected_by_habitability()
    {
        $player = Player::factory()->create();
        $poi1 = PointOfInterest::factory()->create();
        $poi2 = PointOfInterest::factory()->create();

        // High habitability colony
        $highHabitability = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi1->id,
            'name' => 'High Habitability',
            'population' => 1000,
            'population_growth_rate' => 0.02,
            'max_population' => 10000,
            'food_production' => 150,
            'habitability_rating' => 1.5, // 150% habitability
            'development_level' => 1,
            'status' => 'established',
        ]);

        // Low habitability colony
        $lowHabitability = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi2->id,
            'name' => 'Low Habitability',
            'population' => 1000,
            'population_growth_rate' => 0.02,
            'max_population' => 10000,
            'food_production' => 150,
            'habitability_rating' => 0.5, // 50% habitability
            'development_level' => 1,
            'status' => 'established',
        ]);

        $highHabitability->processGrowth();
        $lowHabitability->processGrowth();

        $highGrowth = $highHabitability->fresh()->population - 1000;
        $lowGrowth = $lowHabitability->fresh()->population - 1000;

        $this->assertGreaterThan($lowGrowth, $highGrowth);
    }

    /** @test */
    public function test_growth_rate_affected_by_food_availability()
    {
        $player = Player::factory()->create();
        $poi1 = PointOfInterest::factory()->create();
        $poi2 = PointOfInterest::factory()->create();

        // Colony with sufficient food
        $goodFood = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi1->id,
            'name' => 'Good Food',
            'population' => 1000,
            'population_growth_rate' => 0.02,
            'max_population' => 10000,
            'food_production' => 200, // More than enough
            'habitability_rating' => 1.0,
            'development_level' => 1,
            'status' => 'established',
        ]);

        // Colony with low food
        $lowFood = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi2->id,
            'name' => 'Low Food',
            'population' => 1000,
            'population_growth_rate' => 0.02,
            'max_population' => 10000,
            'food_production' => 50, // Insufficient
            'habitability_rating' => 1.0,
            'development_level' => 1,
            'status' => 'established',
        ]);

        $goodFood->processGrowth();
        $lowFood->processGrowth();

        $goodGrowth = $goodFood->fresh()->population - 1000;
        $lowGrowth = $lowFood->fresh()->population - 1000;

        $this->assertGreaterThan($lowGrowth, $goodGrowth);
    }

    /** @test */
    public function test_population_cannot_exceed_max_population()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Capped Colony',
            'population' => 9900,
            'population_growth_rate' => 0.10, // 10% growth would exceed max
            'max_population' => 10000,
            'food_production' => 1500,
            'habitability_rating' => 1.0,
            'development_level' => 1,
            'status' => 'established',
        ]);

        $colony->processGrowth();

        $this->assertEquals(10000, $colony->fresh()->population);
        $this->assertLessThanOrEqual($colony->max_population, $colony->fresh()->population);
    }

    /** @test */
    public function test_no_growth_when_at_max_population()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Full Colony',
            'population' => 10000,
            'population_growth_rate' => 0.02,
            'max_population' => 10000,
            'food_production' => 1500,
            'habitability_rating' => 1.0,
            'development_level' => 1,
            'status' => 'established',
        ]);

        $colony->processGrowth();

        $this->assertEquals(10000, $colony->fresh()->population);
    }

    /** @test */
    public function test_calculate_production_sums_building_outputs()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Production Colony',
            'population' => 1000,
            'max_population' => 10000,
            'development_level' => 5,
            'status' => 'established',
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        // Create buildings
        ColonyBuilding::create([
            'colony_id' => $colony->id,
            'building_type' => 'hydroponics',
            'level' => 1,
            'status' => 'operational',
            'effects' => ['food_production' => 100],
        ]);

        ColonyBuilding::create([
            'colony_id' => $colony->id,
            'building_type' => 'hydroponics',
            'level' => 1,
            'status' => 'operational',
            'effects' => ['food_production' => 150],
        ]);

        ColonyBuilding::create([
            'colony_id' => $colony->id,
            'building_type' => 'mining_facility',
            'level' => 1,
            'status' => 'operational',
            'effects' => ['mineral_production' => 50],
        ]);

        ColonyBuilding::create([
            'colony_id' => $colony->id,
            'building_type' => 'trade_station',
            'level' => 1,
            'status' => 'operational',
            'effects' => ['credits_per_cycle' => 200],
        ]);

        $colony->calculateProduction();

        $this->assertEquals(250, $colony->fresh()->food_production); // 100 + 150
        $this->assertEquals(50, $colony->fresh()->mineral_production);
        $this->assertEquals(200, $colony->fresh()->credits_per_cycle);
    }

    /** @test */
    public function test_production_only_counts_operational_buildings()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Mixed Colony',
            'population' => 1000,
            'max_population' => 10000,
            'development_level' => 5,
            'status' => 'established',
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        // Operational building
        ColonyBuilding::create([
            'colony_id' => $colony->id,
            'building_type' => 'hydroponics',
            'level' => 1,
            'status' => 'operational',
            'effects' => ['food_production' => 100],
        ]);

        // Damaged building (should not count)
        ColonyBuilding::create([
            'colony_id' => $colony->id,
            'building_type' => 'hydroponics',
            'level' => 1,
            'status' => 'damaged',
            'effects' => ['food_production' => 150],
        ]);

        // Constructing building (should not count)
        ColonyBuilding::create([
            'colony_id' => $colony->id,
            'building_type' => 'hydroponics',
            'level' => 1,
            'status' => 'constructing',
            'effects' => ['food_production' => 200],
        ]);

        $colony->calculateProduction();

        $this->assertEquals(100, $colony->fresh()->food_production); // Only operational building
    }

    /** @test */
    public function test_can_build_building_respects_development_level_limit()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Limited Colony',
            'population' => 1000,
            'max_population' => 10000,
            'development_level' => 2, // Max 4 buildings (2 * 2)
            'status' => 'established',
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        // Create 3 buildings (below limit)
        for ($i = 0; $i < 3; $i++) {
            ColonyBuilding::create([
                'colony_id' => $colony->id,
                'building_type' => 'hydroponics',
                'level' => 1,
                'status' => 'operational',
            ]);
        }

        $this->assertTrue($colony->canBuildBuilding('mining_facility'));

        // Add one more (at limit)
        ColonyBuilding::create([
            'colony_id' => $colony->id,
            'building_type' => 'mining_facility',
            'level' => 1,
            'status' => 'operational',
        ]);

        $this->assertFalse($colony->canBuildBuilding('trade_station'));
    }

    /** @test */
    public function has_shipyard_detects_operational_shipyard()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Shipyard Colony',
            'population' => 1000,
            'max_population' => 10000,
            'development_level' => 5,
            'status' => 'established',
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        $this->assertFalse($colony->hasShipyard());

        // Add operational shipyard
        ColonyBuilding::create([
            'colony_id' => $colony->id,
            'building_type' => 'shipyard',
            'level' => 1,
            'status' => 'operational',
        ]);

        $this->assertTrue($colony->hasShipyard());
    }

    /** @test */
    public function has_shipyard_ignores_non_operational_shipyards()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Damaged Shipyard Colony',
            'population' => 1000,
            'max_population' => 10000,
            'development_level' => 5,
            'status' => 'established',
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        // Add damaged shipyard
        ColonyBuilding::create([
            'colony_id' => $colony->id,
            'building_type' => 'shipyard',
            'level' => 1,
            'status' => 'damaged',
        ]);

        $this->assertFalse($colony->hasShipyard());
    }

    /** @test */
    public function development_level_upgrade_increases_capacity()
    {
        $player = Player::factory()->create(['credits' => 10000]);
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Upgrading Colony',
            'population' => 1000,
            'max_population' => 5000,
            'development_level' => 1,
            'status' => 'established',
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        $result = $colony->upgradeDevelopmentLevel(5000, 100);

        $this->assertTrue($result);
        $this->assertEquals(2, $colony->fresh()->development_level);
        $this->assertEquals(6000, $colony->fresh()->max_population); // 5000 + 1000
        $this->assertEquals(5000, $player->fresh()->credits); // 10000 - 5000
    }

    /** @test */
    public function test_cannot_upgrade_beyond_max_development_level()
    {
        $player = Player::factory()->create(['credits' => 100000]);
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Max Level Colony',
            'population' => 1000,
            'max_population' => 50000,
            'development_level' => 10, // Already at max
            'status' => 'established',
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        $result = $colony->upgradeDevelopmentLevel(5000, 100);

        $this->assertFalse($result);
        $this->assertEquals(10, $colony->fresh()->development_level);
    }

    /** @test */
    public function test_cannot_upgrade_without_sufficient_credits()
    {
        $player = Player::factory()->create(['credits' => 1000]);
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Poor Colony',
            'population' => 1000,
            'max_population' => 5000,
            'development_level' => 1,
            'status' => 'established',
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        $result = $colony->upgradeDevelopmentLevel(5000, 100);

        $this->assertFalse($result);
        $this->assertEquals(1, $colony->fresh()->development_level);
        $this->assertEquals(1000, $player->fresh()->credits); // Unchanged
    }

    /** @test */
    public function test_colony_age_calculates_correctly()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Old Colony',
            'population' => 1000,
            'max_population' => 10000,
            'development_level' => 1,
            'status' => 'established',
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
            'established_at' => now()->subDays(5),
        ]);

        $this->assertEquals(5, $colony->getAgeInDays());
    }

    /** @test */
    public function test_colony_uuid_is_auto_generated()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'UUID Colony',
            'population' => 1000,
            'max_population' => 10000,
            'development_level' => 1,
            'status' => 'established',
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        $this->assertNotNull($colony->uuid);
        $this->assertIsString((string) $colony->uuid);
    }

    /** @test */
    public function test_colony_relationships_work()
    {
        $player = Player::factory()->create();
        $poi = PointOfInterest::factory()->create();

        $colony = Colony::create([
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'name' => 'Related Colony',
            'population' => 1000,
            'max_population' => 10000,
            'development_level' => 1,
            'status' => 'established',
            'habitability_rating' => 1.0,
            'population_growth_rate' => 0.02,
        ]);

        $this->assertEquals($player->id, $colony->player->id);
        $this->assertEquals($poi->id, $colony->poi->id);
    }
}

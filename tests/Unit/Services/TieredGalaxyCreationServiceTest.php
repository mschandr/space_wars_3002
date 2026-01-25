<?php

namespace Tests\Unit\Services;

use App\Enums\Defense\SystemDefenseType;
use App\Enums\Galaxy\GalaxySizeTier;
use App\Enums\Galaxy\RegionType;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\SystemDefense;
use App\Services\MiningService;
use App\Services\SystemDefenseFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TieredGalaxyCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_galaxy_size_tier_returns_correct_outer_bounds()
    {
        $this->assertEquals(500, GalaxySizeTier::SMALL->getOuterBounds());
        $this->assertEquals(1500, GalaxySizeTier::MEDIUM->getOuterBounds());
        $this->assertEquals(2500, GalaxySizeTier::LARGE->getOuterBounds());
    }

    public function test_galaxy_size_tier_returns_correct_core_bounds()
    {
        $this->assertEquals(250, GalaxySizeTier::SMALL->getCoreBounds());
        $this->assertEquals(750, GalaxySizeTier::MEDIUM->getCoreBounds());
        $this->assertEquals(1250, GalaxySizeTier::LARGE->getCoreBounds());
    }

    public function test_galaxy_size_tier_returns_correct_star_counts()
    {
        // Small: core = 100, outer = 150
        $this->assertEquals(100, GalaxySizeTier::SMALL->getCoreStars());
        $this->assertEquals(150, GalaxySizeTier::SMALL->getOuterStars());
        $this->assertEquals(250, GalaxySizeTier::SMALL->getTotalStars());

        // Medium: core = 300, outer = 450
        $this->assertEquals(300, GalaxySizeTier::MEDIUM->getCoreStars());
        $this->assertEquals(450, GalaxySizeTier::MEDIUM->getOuterStars());
        $this->assertEquals(750, GalaxySizeTier::MEDIUM->getTotalStars());

        // Large: core = 500, outer = 750
        $this->assertEquals(500, GalaxySizeTier::LARGE->getCoreStars());
        $this->assertEquals(750, GalaxySizeTier::LARGE->getOuterStars());
        $this->assertEquals(1250, GalaxySizeTier::LARGE->getTotalStars());
    }

    public function test_galaxy_size_tier_returns_correct_core_bounds_array()
    {
        $bounds = GalaxySizeTier::SMALL->getCoreBoundsArray();

        $this->assertEquals(125, $bounds['x_min']);
        $this->assertEquals(375, $bounds['x_max']);
        $this->assertEquals(125, $bounds['y_min']);
        $this->assertEquals(375, $bounds['y_max']);
    }

    public function test_region_type_has_correct_inhabited_percentage()
    {
        $this->assertEquals(1.0, RegionType::CORE->getInhabitedPercentage());
        $this->assertEquals(0.0, RegionType::OUTER->getInhabitedPercentage());
    }

    public function test_region_type_has_correct_mineral_multiplier()
    {
        $this->assertEquals(1.0, RegionType::CORE->getMineralMultiplier());
        $this->assertEquals(2.0, RegionType::OUTER->getMineralMultiplier());
    }

    public function test_system_defense_factory_creates_fortress_defenses()
    {
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'region' => RegionType::CORE,
        ]);

        $factory = app(SystemDefenseFactory::class);
        $defenses = $factory->deployFortressDefenses($poi);

        // Should create: 4 orbital cannons + 2 space lasers + 6 ground missiles + 1 shield + 1 fighter port = 14
        $this->assertCount(14, $defenses);

        // POI should be marked as fortified
        $poi->refresh();
        $this->assertTrue($poi->is_fortified);

        // Verify defense types
        $types = $defenses->pluck('defense_type');
        $this->assertEquals(4, $types->filter(fn ($t) => $t === SystemDefenseType::ORBITAL_CANNON)->count());
        $this->assertEquals(2, $types->filter(fn ($t) => $t === SystemDefenseType::SPACE_LASER)->count());
        $this->assertEquals(6, $types->filter(fn ($t) => $t === SystemDefenseType::GROUND_MISSILE)->count());
        $this->assertEquals(1, $types->filter(fn ($t) => $t === SystemDefenseType::PLANETARY_SHIELD)->count());
        $this->assertEquals(1, $types->filter(fn ($t) => $t === SystemDefenseType::FIGHTER_PORT)->count());
    }

    public function test_system_defense_calculates_damage_correctly()
    {
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
        ]);

        $defense = SystemDefense::create([
            'poi_id' => $poi->id,
            'defense_type' => SystemDefenseType::ORBITAL_CANNON,
            'level' => 1,
            'quantity' => 1,
            'health' => 500,
            'max_health' => 500,
            'is_active' => true,
            'attributes' => SystemDefenseType::ORBITAL_CANNON->getDefaultAttributes(),
        ]);

        // Level 1 orbital cannon should deal 50 base damage
        $this->assertEquals(50, $defense->calculateDamage());

        // Level 2 should deal 50 * 1.15 = 57.5, rounded to 57
        $defense->level = 2;
        $defense->save();
        $this->assertEquals(57, $defense->calculateDamage());
    }

    public function test_system_defense_takes_damage_correctly()
    {
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
        ]);

        $defense = SystemDefense::create([
            'poi_id' => $poi->id,
            'defense_type' => SystemDefenseType::ORBITAL_CANNON,
            'level' => 1,
            'quantity' => 1,
            'health' => 500,
            'max_health' => 500,
            'is_active' => true,
            'attributes' => [],
        ]);

        $result = $defense->takeDamage(100);

        $this->assertEquals(100, $result['damage_taken']);
        $this->assertEquals(400, $result['remaining_health']);
        $this->assertFalse($result['destroyed']);

        // Take enough damage to destroy
        $result = $defense->takeDamage(500);
        $this->assertTrue($result['destroyed']);
        $this->assertEquals(0, $defense->health);
    }

    public function test_system_defense_is_not_operational_when_destroyed()
    {
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
        ]);

        $defense = SystemDefense::create([
            'poi_id' => $poi->id,
            'defense_type' => SystemDefenseType::ORBITAL_CANNON,
            'level' => 1,
            'quantity' => 1,
            'health' => 0,  // Destroyed
            'max_health' => 500,
            'is_active' => true,
            'attributes' => [],
        ]);

        $this->assertFalse($defense->isOperational());
        $this->assertEquals(0, $defense->calculateDamage());
    }

    public function test_mining_service_allows_outer_region_mining()
    {
        $player = Player::factory()->create();
        $galaxy = Galaxy::factory()->create();
        $outerPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'region' => RegionType::OUTER,
            'owner_id' => null,
        ]);

        $miningService = new MiningService;
        $this->assertTrue($miningService->canMineAt($player, $outerPoi));
    }

    public function test_mining_service_restricts_core_region_with_other_owner()
    {
        $player = Player::factory()->create();
        $otherPlayer = Player::factory()->create();
        $galaxy = Galaxy::factory()->create();

        $corePoi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'region' => RegionType::CORE,
            'owner_id' => $otherPlayer->id,
        ]);

        $miningService = new MiningService;
        $this->assertFalse($miningService->canMineAt($player, $corePoi));
    }

    public function test_mining_service_allows_core_region_with_no_owner()
    {
        $player = Player::factory()->create();
        $galaxy = Galaxy::factory()->create();

        $corePoi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'region' => RegionType::CORE,
            'owner_id' => null,
        ]);

        $miningService = new MiningService;
        $this->assertTrue($miningService->canMineAt($player, $corePoi));
    }

    public function test_mining_service_allows_core_region_for_owner()
    {
        $player = Player::factory()->create();
        $galaxy = Galaxy::factory()->create();

        $corePoi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'region' => RegionType::CORE,
            'owner_id' => $player->id,
        ]);

        $miningService = new MiningService;
        $this->assertTrue($miningService->canMineAt($player, $corePoi));
    }

    public function test_galaxy_can_check_if_in_core_region()
    {
        $galaxy = Galaxy::factory()->create([
            'width' => 500,
            'height' => 500,
            'size_tier' => GalaxySizeTier::SMALL,
            'core_bounds' => GalaxySizeTier::SMALL->getCoreBoundsArray(),
        ]);

        // Inside core (center)
        $this->assertTrue($galaxy->isInCoreRegion(250, 250));

        // Just inside core boundary
        $this->assertTrue($galaxy->isInCoreRegion(126, 250));
        $this->assertTrue($galaxy->isInCoreRegion(374, 250));

        // Outside core
        $this->assertFalse($galaxy->isInCoreRegion(50, 50));
        $this->assertFalse($galaxy->isInCoreRegion(450, 450));
        $this->assertFalse($galaxy->isInCoreRegion(100, 250));
    }

    public function test_poi_can_check_region_type()
    {
        $galaxy = Galaxy::factory()->create();

        $corePoi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'region' => RegionType::CORE,
        ]);

        $outerPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'region' => RegionType::OUTER,
        ]);

        $this->assertTrue($corePoi->isInCoreRegion());
        $this->assertFalse($corePoi->isInOuterRegion());

        $this->assertFalse($outerPoi->isInCoreRegion());
        $this->assertTrue($outerPoi->isInOuterRegion());
    }
}

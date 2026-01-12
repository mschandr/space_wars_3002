<?php

namespace Tests\Unit\Services;

use App\Models\Colony;
use App\Models\ColonyBuilding;
use App\Models\Mineral;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Services\MiningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiningServiceTest extends TestCase
{
    use RefreshDatabase;

    private MiningService $miningService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->miningService = new MiningService;
    }

    /** @test */
    public function it_calculates_sensor_efficiency_correctly()
    {
        // Test the formula: min(2.0, 0.1 * (1.18^sensorLevel))

        // Sensor level 1: ~11.8%
        $efficiency1 = $this->miningService->calculateSensorEfficiency(1);
        $this->assertEqualsWithDelta(0.118, $efficiency1, 0.001);

        // Sensor level 6: ~62.3% (from requirements)
        $efficiency6 = $this->miningService->calculateSensorEfficiency(6);
        $this->assertEqualsWithDelta(0.623, $efficiency6, 0.01);

        // Sensor level 7: ~73.5% (from requirements)
        $efficiency7 = $this->miningService->calculateSensorEfficiency(7);
        $this->assertEqualsWithDelta(0.735, $efficiency7, 0.01);

        // Sensor level 16: should cap at 200%
        $efficiency16 = $this->miningService->calculateSensorEfficiency(16);
        $this->assertEqualsWithDelta(2.0, $efficiency16, 0.01);

        // Sensor level 20: should still cap at 200%
        $efficiency20 = $this->miningService->calculateSensorEfficiency(20);
        $this->assertEquals(2.0, $efficiency20);
    }

    /** @test */
    public function sensor_efficiency_increases_exponentially()
    {
        $efficiency5 = $this->miningService->calculateSensorEfficiency(5);
        $efficiency10 = $this->miningService->calculateSensorEfficiency(10);
        $efficiency15 = $this->miningService->calculateSensorEfficiency(15);

        // Each level should be significantly more than the previous
        $this->assertGreaterThan($efficiency5, $efficiency10);
        $this->assertGreaterThan($efficiency10, $efficiency15);

        // The gap should grow (exponential)
        $gap1 = $efficiency10 - $efficiency5;
        $gap2 = $efficiency15 - $efficiency10;
        $this->assertGreaterThan($gap1, $gap2);
    }

    /** @test */
    public function it_extracts_correct_amount_based_on_sensor_level()
    {
        $player = $this->createTestPlayer();
        $colony = $this->createTestColony($player);
        $miningFacility = $this->createTestMiningFacility($colony);
        $iceGiant = $this->createTestIceGiant();
        $ship = $this->createTestShip($player, sensorLevel: 6);
        $quantium = $this->createTestQuantium();

        $depositSize = 5000;
        $result = $this->miningService->extractResources(
            $miningFacility,
            $ship,
            $iceGiant,
            $quantium,
            $depositSize
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(6, $result['sensor_level']);

        // With sensor level 6 (~62.3%), should extract ~3,115 units
        $expectedAmount = (int) ($depositSize * 0.623);
        $this->assertEqualsWithDelta($expectedAmount, $result['amount_extracted'], 50);
    }

    /** @test */
    public function higher_sensor_level_extracts_more_resources()
    {
        $player = $this->createTestPlayer();
        $colony = $this->createTestColony($player);
        $miningFacility = $this->createTestMiningFacility($colony);
        $iceGiant = $this->createTestIceGiant();
        $quantium = $this->createTestQuantium();

        $depositSize = 5000;

        // Test with sensor level 6
        $ship6 = $this->createTestShip($player, sensorLevel: 6);
        $result6 = $this->miningService->extractResources(
            $miningFacility,
            $ship6,
            $iceGiant,
            $quantium,
            $depositSize
        );

        // Test with sensor level 10
        $ship10 = $this->createTestShip($player, sensorLevel: 10);
        $result10 = $this->miningService->extractResources(
            $miningFacility,
            $ship10,
            $iceGiant,
            $quantium,
            $depositSize
        );

        $this->assertGreaterThan($result6['amount_extracted'], $result10['amount_extracted']);
    }

    /** @test */
    public function facility_level_provides_bonus()
    {
        $player = $this->createTestPlayer();
        $colony = $this->createTestColony($player);
        $iceGiant = $this->createTestIceGiant();
        $ship = $this->createTestShip($player, sensorLevel: 6);
        $quantium = $this->createTestQuantium();

        $depositSize = 5000;

        // Level 1 facility
        $facility1 = $this->createTestMiningFacility($colony, level: 1);
        $result1 = $this->miningService->extractResources(
            $facility1,
            $ship,
            $iceGiant,
            $quantium,
            $depositSize
        );

        // Level 3 facility (should give 20% bonus)
        $facility3 = $this->createTestMiningFacility($colony, level: 3);
        $result3 = $this->miningService->extractResources(
            $facility3,
            $ship,
            $iceGiant,
            $quantium,
            $depositSize
        );

        $this->assertGreaterThan($result1['amount_extracted'], $result3['amount_extracted']);
    }

    /** @test */
    public function it_only_extracts_from_ice_giants_for_quantium()
    {
        $player = $this->createTestPlayer();
        $colony = $this->createTestColony($player);
        $miningFacility = $this->createTestMiningFacility($colony);
        $ship = $this->createTestShip($player, sensorLevel: 6);
        $quantium = $this->createTestQuantium();

        // Try to extract from a regular planet (should fail)
        $regularPlanet = PointOfInterest::factory()->create([
            'type' => 'planet',
            'planet_class' => 'terrestrial',
        ]);

        $result = $this->miningService->extractQuantium(
            $miningFacility,
            $ship,
            $regularPlanet,
            5000
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ice giant', $result['message']);
    }

    /** @test */
    public function it_validates_mining_facility_type()
    {
        $player = $this->createTestPlayer();
        $colony = $this->createTestColony($player);
        $iceGiant = $this->createTestIceGiant();
        $ship = $this->createTestShip($player, sensorLevel: 6);
        $quantium = $this->createTestQuantium();

        // Try with wrong building type
        $wrongBuilding = ColonyBuilding::factory()->create([
            'colony_id' => $colony->id,
            'building_type' => 'hydroponics', // Not a mining facility!
        ]);

        $result = $this->miningService->extractResources(
            $wrongBuilding,
            $ship,
            $iceGiant,
            $quantium,
            5000
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not an orbital mining facility', $result['message']);
    }

    // Helper methods to create test data
    private function createTestPlayer()
    {
        return \App\Models\Player::factory()->create();
    }

    private function createTestColony($player)
    {
        return Colony::factory()->create([
            'player_id' => $player->id,
        ]);
    }

    private function createTestMiningFacility($colony, $level = 1)
    {
        return ColonyBuilding::factory()->create([
            'colony_id' => $colony->id,
            'building_type' => 'orbital_mining',
            'level' => $level,
            'status' => 'operational',
        ]);
    }

    private function createTestIceGiant()
    {
        return PointOfInterest::factory()->create([
            'type' => 'gas_giant',
            'planet_class' => 'ice_giant',
        ]);
    }

    private function createTestShip($player, $sensorLevel = 1)
    {
        return PlayerShip::factory()->create([
            'player_id' => $player->id,
            'sensors' => $sensorLevel,
        ]);
    }

    private function createTestQuantium()
    {
        return Mineral::factory()->create([
            'name' => 'Quantium',
            'symbol' => 'Qm',
            'attributes' => [
                'found_in' => ['ice_giant'],
            ],
        ]);
    }
}

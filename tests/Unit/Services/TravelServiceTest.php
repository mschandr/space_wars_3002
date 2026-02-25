<?php

namespace Tests\Unit\Services;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\TradingHub;
use App\Models\WarpGate;
use App\Services\TravelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Travel & Fuel Calculation Tests
 *
 * Critical game mechanics:
 * - Distance calculated using Euclidean formula: sqrt((x2-x1)^2 + (y2-y1)^2)
 * - Fuel cost: ceil(distance / warp_efficiency), min 1
 * - Warp efficiency: 1 + ((warp_drive - 1) * 0.2) - 20% reduction per level
 * - Travel XP: max(10, distance * 5) - 5 XP per unit distance, min 10
 *
 * @see /TESTING_ROADMAP.md#12-travel--fuel-tests
 */
class TravelServiceTest extends TestCase
{
    use RefreshDatabase;

    private TravelService $travelService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelService = new TravelService;
    }

    /** @test */
    public function test_travel_distance_calculated_correctly_euclidean()
    {
        $galaxy = Galaxy::factory()->create();

        // Create two points at known coordinates
        $source = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 0,
            'y' => 0,
        ]);

        $destination = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 3,
            'y' => 4,
        ]);

        $gate = WarpGate::create([
            'galaxy_id' => $galaxy->id,
            'uuid' => \Illuminate\Support\Str::uuid(),
            'galaxy_id' => $galaxy->id,
            'source_poi_id' => $source->id,
            'destination_poi_id' => $destination->id,
            'is_active' => true,
        ]);

        // Euclidean distance: sqrt((3-0)^2 + (4-0)^2) = sqrt(9 + 16) = sqrt(25) = 5.0
        $distance = $gate->calculateDistance();

        $this->assertEquals(5.0, $distance);
    }

    /** @test */
    public function test_distance_calculation_with_large_coordinates()
    {
        $galaxy = Galaxy::factory()->create();

        $source = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 100,
            'y' => 200,
        ]);

        $destination = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 400,
            'y' => 600,
        ]);

        $gate = WarpGate::create([
            'galaxy_id' => $galaxy->id,
            'uuid' => \Illuminate\Support\Str::uuid(),
            'galaxy_id' => $galaxy->id,
            'source_poi_id' => $source->id,
            'destination_poi_id' => $destination->id,
            'is_active' => true,
        ]);

        // Distance: sqrt((400-100)^2 + (600-200)^2) = sqrt(300^2 + 400^2) = sqrt(90000 + 160000) = sqrt(250000) = 500
        $distance = $gate->calculateDistance();

        $this->assertEquals(500.0, $distance);
    }

    /** @test */
    public function test_fuel_cost_calculation_with_warp_drive_level_1()
    {
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'ship_id' => $ship->id,
            'warp_drive' => 1,
        ]);

        // Warp drive level 1: efficiency = 1.0 (no bonus)
        // Distance 10: fuel cost = ceil(10 / 1.0) = 10
        $fuelCost = $this->travelService->calculateFuelCost(10.0, $playerShip);

        $this->assertEquals(10, $fuelCost);
    }

    /** @test */
    public function test_fuel_cost_calculation_with_warp_drive_level_2()
    {
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'ship_id' => $ship->id,
            'warp_drive' => 2,
        ]);

        // Warp drive level 2: efficiency = 1 + (1 * 0.2) = 1.2 (20% reduction)
        // Distance 10: fuel cost = ceil(10 / 1.2) = ceil(8.33) = 9
        $fuelCost = $this->travelService->calculateFuelCost(10.0, $playerShip);

        $this->assertEquals(9, $fuelCost);
    }

    /** @test */
    public function test_fuel_cost_calculation_with_warp_drive_level_6()
    {
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'ship_id' => $ship->id,
            'warp_drive' => 6,
        ]);

        // Warp drive level 6: efficiency = 1 + (5 * 0.2) = 2.0 (100% better efficiency)
        // Distance 10: fuel cost = ceil(10 / 2.0) = 5
        $fuelCost = $this->travelService->calculateFuelCost(10.0, $playerShip);

        $this->assertEquals(5, $fuelCost);
    }

    /** @test */
    public function test_fuel_cost_has_minimum_of_1()
    {
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'ship_id' => $ship->id,
            'warp_drive' => 10, // Very high warp drive
        ]);

        // Even with very short distance and high warp drive, fuel cost is always at least 1
        $fuelCost = $this->travelService->calculateFuelCost(0.1, $playerShip);

        $this->assertEquals(1, $fuelCost);
    }

    /** @test */
    public function test_travel_xp_calculation()
    {
        // Formula: max(10, distance * 5)

        // Distance 2: XP = max(10, 2 * 5) = 10 (minimum)
        $xp = $this->travelService->calculateTravelXP(2.0);
        $this->assertEquals(10, $xp);

        // Distance 5: XP = max(10, 5 * 5) = 25
        $xp = $this->travelService->calculateTravelXP(5.0);
        $this->assertEquals(25, $xp);

        // Distance 10: XP = max(10, 10 * 5) = 50
        $xp = $this->travelService->calculateTravelXP(10.0);
        $this->assertEquals(50, $xp);

        // Distance 20: XP = max(10, 20 * 5) = 100
        $xp = $this->travelService->calculateTravelXP(20.0);
        $this->assertEquals(100, $xp);
    }

    /** @test */
    public function test_travel_xp_has_minimum_of_10()
    {
        // Very short distance still gives 10 XP minimum
        $xp = $this->travelService->calculateTravelXP(0.5);
        $this->assertEquals(10, $xp);

        $xp = $this->travelService->calculateTravelXP(1.0);
        $this->assertEquals(10, $xp);

        $xp = $this->travelService->calculateTravelXP(1.9);
        $this->assertEquals(10, $xp);
    }

    /** @test */
    public function test_cannot_travel_without_sufficient_fuel()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 5, // Only 5 fuel
            'warp_drive' => 1,
            'is_active' => true,
        ]);

        $galaxy = Galaxy::factory()->create();
        $source = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 0,
            'y' => 0,
        ]);
        $destination = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 10, // Distance 10, requires 10 fuel
            'y' => 0,
        ]);

        $gate = WarpGate::create([
            'galaxy_id' => $galaxy->id,
            'uuid' => \Illuminate\Support\Str::uuid(),
            'source_poi_id' => $source->id,
            'destination_poi_id' => $destination->id,
            'is_active' => true,
        ]);

        $player->current_poi_id = $source->id;
        $player->save();

        $result = $this->travelService->executeTravel($player, $gate);

        $this->assertFalse($result['success']);
        $this->assertEquals('Insufficient fuel', $result['message']);
        $this->assertEquals(10, $result['required_fuel']);
        $this->assertEquals(5, $result['current_fuel']);
        $this->assertEquals(0, $result['xp_earned']);

        // Player should still be at source
        $player->refresh();
        $this->assertEquals($source->id, $player->current_poi_id);

        // Fuel should be unchanged
        $playerShip->refresh();
        $this->assertEquals(5, $playerShip->current_fuel);
    }

    /** @test */
    public function test_successful_travel_updates_location_and_consumes_fuel()
    {
        config(['game_config.ships.fuel_regen_seconds_per_unit' => 30]);

        $player = Player::factory()->create();
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 20,
            'warp_drive' => 1,
            'is_active' => true,
        ]);

        $galaxy = Galaxy::factory()->create();
        $source = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 0,
            'y' => 0,
        ]);
        $destination = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 6,
            'y' => 8,
        ]);

        $gate = WarpGate::create([
            'galaxy_id' => $galaxy->id,
            'uuid' => \Illuminate\Support\Str::uuid(),
            'source_poi_id' => $source->id,
            'destination_poi_id' => $destination->id,
            'is_active' => true,
        ]);

        $player->current_poi_id = $source->id;
        $player->save();

        // Distance: sqrt(36 + 64) = sqrt(100) = 10
        // Fuel cost: 10
        $result = $this->travelService->executeTravel($player, $gate);

        $this->assertTrue($result['success']);
        $this->assertEquals('Travel successful', $result['message']);
        $this->assertEquals(10.0, $result['distance']);
        $this->assertEquals(10, $result['fuel_cost']);
        $this->assertEquals(10, $result['fuel_remaining']); // 20 - 10

        // Player should be at destination
        $player->refresh();
        $this->assertEquals($destination->id, $player->current_poi_id);

        // Fuel should be consumed
        $playerShip->refresh();
        $this->assertEquals(10, $playerShip->current_fuel);
    }

    /** @test */
    public function test_travel_awards_xp()
    {
        $player = Player::factory()->create([
            'experience' => 0,
            'level' => 1,
        ]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 50,
            'warp_drive' => 1,
            'is_active' => true,
        ]);

        $galaxy = Galaxy::factory()->create();
        $source = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 0,
            'y' => 0,
        ]);
        $destination = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 6,
            'y' => 8,
        ]);

        $gate = WarpGate::create([
            'galaxy_id' => $galaxy->id,
            'uuid' => \Illuminate\Support\Str::uuid(),
            'source_poi_id' => $source->id,
            'destination_poi_id' => $destination->id,
            'is_active' => true,
        ]);

        $player->current_poi_id = $source->id;
        $player->save();

        // Distance: 10, XP: max(10, 10 * 5) = 50
        $result = $this->travelService->executeTravel($player, $gate);

        $this->assertEquals(50, $result['xp_earned']);

        // Player should have gained XP
        $player->refresh();
        $this->assertEquals(50, $player->experience);
    }

    /** @test */
    public function test_travel_can_trigger_level_up()
    {
        // Player at 99 XP (just before level 2)
        $player = Player::factory()->create([
            'experience' => 99,
            'level' => 1,
        ]);
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 50,
            'warp_drive' => 1,
            'is_active' => true,
        ]);

        $galaxy = Galaxy::factory()->create();
        $source = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 0,
            'y' => 0,
        ]);
        $destination = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 6,
            'y' => 8,
        ]);

        $gate = WarpGate::create([
            'galaxy_id' => $galaxy->id,
            'uuid' => \Illuminate\Support\Str::uuid(),
            'source_poi_id' => $source->id,
            'destination_poi_id' => $destination->id,
            'is_active' => true,
        ]);

        $player->current_poi_id = $source->id;
        $player->save();

        // Distance: 10, XP: 50 (total will be 149, should level up to 2)
        $result = $this->travelService->executeTravel($player, $gate);

        $this->assertEquals(1, $result['old_level']);
        $this->assertEquals(2, $result['new_level']);
        $this->assertTrue($result['leveled_up']);

        // Verify player leveled up
        $player->refresh();
        $this->assertEquals(2, $player->level);
        $this->assertEquals(149, $player->experience);
    }

    /** @test */
    public function test_travel_tracks_last_trading_hub()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 50,
            'warp_drive' => 1,
            'is_active' => true,
        ]);

        $galaxy = Galaxy::factory()->create();
        $source = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 0,
            'y' => 0,
        ]);
        $destination = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 3,
            'y' => 4,
        ]);

        // Create trading hub at destination
        $tradingHub = TradingHub::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'poi_id' => $destination->id,
            'name' => 'Test Trading Hub',
            'is_active' => true,
        ]);

        $gate = WarpGate::create([
            'galaxy_id' => $galaxy->id,
            'uuid' => \Illuminate\Support\Str::uuid(),
            'source_poi_id' => $source->id,
            'destination_poi_id' => $destination->id,
            'is_active' => true,
        ]);

        $player->current_poi_id = $source->id;
        $player->last_trading_hub_poi_id = null;
        $player->save();

        $result = $this->travelService->executeTravel($player, $gate);

        $this->assertTrue($result['success']);

        // Player should have last trading hub tracked
        $player->refresh();
        $this->assertEquals($destination->id, $player->last_trading_hub_poi_id);
    }

    /** @test */
    public function test_travel_does_not_track_inactive_trading_hub()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 50,
            'warp_drive' => 1,
            'is_active' => true,
        ]);

        $galaxy = Galaxy::factory()->create();
        $source = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 0,
            'y' => 0,
        ]);
        $destination = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'x' => 3,
            'y' => 4,
        ]);

        // Create INACTIVE trading hub at destination
        $tradingHub = TradingHub::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'poi_id' => $destination->id,
            'name' => 'Inactive Hub',
            'is_active' => false,
        ]);

        $gate = WarpGate::create([
            'galaxy_id' => $galaxy->id,
            'uuid' => \Illuminate\Support\Str::uuid(),
            'source_poi_id' => $source->id,
            'destination_poi_id' => $destination->id,
            'is_active' => true,
        ]);

        $player->current_poi_id = $source->id;
        $player->last_trading_hub_poi_id = null;
        $player->save();

        $result = $this->travelService->executeTravel($player, $gate);

        $this->assertTrue($result['success']);

        // Last trading hub should NOT be updated
        $player->refresh();
        $this->assertNull($player->last_trading_hub_poi_id);
    }

    /** @test */
    public function test_travel_fails_when_no_active_ship()
    {
        $player = Player::factory()->create();
        // No active ship

        $galaxy = Galaxy::factory()->create();
        $source = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);
        $destination = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);

        $gate = WarpGate::create([
            'galaxy_id' => $galaxy->id,
            'uuid' => \Illuminate\Support\Str::uuid(),
            'source_poi_id' => $source->id,
            'destination_poi_id' => $destination->id,
            'is_active' => true,
        ]);

        $result = $this->travelService->executeTravel($player, $gate);

        $this->assertFalse($result['success']);
        $this->assertEquals('No active ship', $result['message']);
    }

    /** @test */
    public function test_warp_drive_efficiency_20_percent_per_level()
    {
        $ship = Ship::factory()->create();

        // Test multiple warp drive levels
        $tests = [
            ['warp' => 1, 'distance' => 10, 'expected' => 10], // 10 / 1.0 = 10
            ['warp' => 2, 'distance' => 10, 'expected' => 9],  // 10 / 1.2 = 8.33 → 9
            ['warp' => 3, 'distance' => 10, 'expected' => 8],  // 10 / 1.4 = 7.14 → 8
            ['warp' => 4, 'distance' => 10, 'expected' => 7],  // 10 / 1.6 = 6.25 → 7
            ['warp' => 5, 'distance' => 10, 'expected' => 6],  // 10 / 1.8 = 5.56 → 6
            ['warp' => 6, 'distance' => 10, 'expected' => 5],  // 10 / 2.0 = 5.00
        ];

        foreach ($tests as $test) {
            $playerShip = PlayerShip::factory()->create([
                'ship_id' => $ship->id,
                'warp_drive' => $test['warp'],
            ]);

            $fuelCost = $this->travelService->calculateFuelCost($test['distance'], $playerShip);

            $this->assertEquals(
                $test['expected'],
                $fuelCost,
                "Warp drive {$test['warp']} should cost {$test['expected']} fuel for distance {$test['distance']}"
            );
        }
    }

    /** @test */
    public function test_direct_jump_fuel_cost_uses_reduced_warp_efficiency()
    {
        $ship = Ship::factory()->create();

        // Direct jumps use 25% warp efficiency factor + 4.0x penalty
        // directEfficiency = 1 + ((warpLevel - 1) * 0.2 * 0.25)
        // baseCost = ceil(ceil(distance) / directEfficiency)
        // finalCost = ceil(baseCost * 4.0)
        $tests = [
            // Warp 1: eff=1.0, base=ceil(10/1.0)=10, final=ceil(10*4.0)=40
            ['warp' => 1, 'distance' => 10.0, 'expected' => 40],
            // Warp 6: eff=1+5*0.2*0.25=1.25, base=ceil(10/1.25)=8, final=ceil(8*4.0)=32
            ['warp' => 6, 'distance' => 10.0, 'expected' => 32],
            // Warp 10: eff=1+9*0.2*0.25=1.45, base=ceil(10/1.45)=ceil(6.896)=7, final=ceil(7*4.0)=28
            ['warp' => 10, 'distance' => 10.0, 'expected' => 28],
        ];

        foreach ($tests as $test) {
            $playerShip = PlayerShip::factory()->create([
                'ship_id' => $ship->id,
                'warp_drive' => $test['warp'],
            ]);

            $fuelCost = $this->travelService->calculateDirectJumpFuelCost($test['distance'], $playerShip);

            $this->assertEquals(
                $test['expected'],
                $fuelCost,
                "Warp drive {$test['warp']} direct jump should cost {$test['expected']} fuel for distance {$test['distance']}, got {$fuelCost}"
            );
        }
    }

    /** @test */
    public function test_direct_jump_efficiency_gap_grows_with_warp_level()
    {
        $ship = Ship::factory()->create();
        $distance = 10.0;

        $previousRatio = 0;

        foreach ([1, 3, 6, 10] as $warpLevel) {
            $playerShip = PlayerShip::factory()->create([
                'ship_id' => $ship->id,
                'warp_drive' => $warpLevel,
            ]);

            $gateCost = $this->travelService->calculateFuelCost($distance, $playerShip);
            $directCost = $this->travelService->calculateDirectJumpFuelCost($distance, $playerShip);

            $ratio = $directCost / $gateCost;

            if ($previousRatio > 0) {
                $this->assertGreaterThan(
                    $previousRatio,
                    $ratio,
                    "Direct/gate cost ratio should grow with warp level (warp {$warpLevel}: {$ratio} should be > {$previousRatio})"
                );
            }

            $previousRatio = $ratio;
        }
    }

    /** @test */
    public function test_direct_jump_fuel_cost_is_symmetric_between_points()
    {
        $ship = Ship::factory()->create();

        // Points: A(47, 183) and B(219, 62)
        // Distance = sqrt((219-47)^2 + (62-183)^2) = sqrt(172^2 + 121^2) = sqrt(44225) ≈ 210.30
        $ax = 47;
        $ay = 183;
        $bx = 219;
        $by = 62;
        $distance = sqrt(pow($bx - $ax, 2) + pow($by - $ay, 2));

        foreach ([1, 3, 6] as $warpLevel) {
            $playerShip = PlayerShip::factory()->create([
                'ship_id' => $ship->id,
                'warp_drive' => $warpLevel,
            ]);

            // Direct jump cost should be the same A→B and B→A (same distance)
            $costAB = $this->travelService->calculateDirectJumpFuelCost($distance, $playerShip);
            $costBA = $this->travelService->calculateDirectJumpFuelCost($distance, $playerShip);

            $this->assertEquals(
                $costAB,
                $costBA,
                "Direct jump fuel cost should be symmetric at warp {$warpLevel}"
            );

            // Gate cost should also be symmetric
            $gateCostAB = $this->travelService->calculateFuelCost($distance, $playerShip);
            $gateCostBA = $this->travelService->calculateFuelCost($distance, $playerShip);

            $this->assertEquals(
                $gateCostAB,
                $gateCostBA,
                "Gate fuel cost should be symmetric at warp {$warpLevel}"
            );
        }
    }
}

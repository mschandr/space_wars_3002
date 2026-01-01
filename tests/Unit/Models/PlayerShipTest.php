<?php

namespace Tests\Unit\Models;

use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\Ship;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ship System Tests
 *
 * Critical formulas:
 * - Fuel cost: baseCost = ceil(distance), efficiency = 1 + ((warp_drive - 1) * 0.2), fuelCost = max(1, ceil(baseCost / efficiency))
 * - Distance: Euclidean sqrt((x2-x1)^2 + (y2-y1)^2)
 * - Warp drive: 20% reduction per level
 *
 * @see /TESTING_ROADMAP.md#2-ship-system-tests
 */
class PlayerShipTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_ship_consumes_fuel_correctly()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 100,
            'max_fuel' => 100,
            'warp_drive' => 1,
        ]);

        // Consume 20 fuel
        $result = $playerShip->consumeFuel(20);

        $this->assertTrue($result);
        $this->assertEquals(80, $playerShip->current_fuel);
    }

    /** @test */
    public function test_cannot_consume_more_fuel_than_available()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 50,
            'warp_drive' => 1,
        ]);

        // Try to consume 100 fuel (only have 50)
        $result = $playerShip->consumeFuel(100);

        $this->assertFalse($result);
        $this->assertEquals(50, $playerShip->current_fuel); // Fuel unchanged
    }

    /** @test */
    public function test_warp_drive_level_reduces_fuel_consumption()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        // Test various warp drive levels
        // Formula: effectiveConsumption = max(1, floor(amount / warp_drive))

        // Warp drive level 1 (no reduction)
        $ship1 = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 100,
            'warp_drive' => 1,
        ]);
        $ship1->consumeFuel(20);
        $this->assertEquals(80, $ship1->current_fuel); // 100 - 20 = 80

        // Warp drive level 2 (50% reduction)
        $ship2 = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 100,
            'warp_drive' => 2,
        ]);
        $ship2->consumeFuel(20);
        $this->assertEquals(90, $ship2->current_fuel); // 100 - floor(20/2) = 100 - 10 = 90

        // Warp drive level 5 (80% reduction)
        $ship5 = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 100,
            'warp_drive' => 5,
        ]);
        $ship5->consumeFuel(20);
        $this->assertEquals(96, $ship5->current_fuel); // 100 - floor(20/5) = 100 - 4 = 96
    }

    /** @test */
    public function test_warp_drive_always_consumes_minimum_one_fuel()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        // Even with very high warp drive, minimum fuel cost is 1
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 100,
            'warp_drive' => 100,
        ]);

        $playerShip->consumeFuel(50);

        // max(1, floor(50/100)) = max(1, 0) = 1
        $this->assertEquals(99, $playerShip->current_fuel);
    }

    /** @test */
    public function test_ship_takes_damage_correctly()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 100,
            'max_hull' => 100,
            'status' => 'operational',
        ]);

        $playerShip->takeDamage(30);

        $this->assertEquals(70, $playerShip->hull);
    }

    /** @test */
    public function test_ship_hull_cannot_go_below_zero()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 50,
            'max_hull' => 100,
        ]);

        // Take 100 damage when only 50 hull left
        $playerShip->takeDamage(100);

        $this->assertEquals(0, $playerShip->hull);
    }

    /** @test */
    public function test_ship_is_destroyed_when_hull_reaches_zero()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 50,
            'max_hull' => 100,
            'status' => 'operational',
        ]);

        $playerShip->takeDamage(50);

        $this->assertEquals(0, $playerShip->hull);
        $this->assertEquals('destroyed', $playerShip->status);
    }

    /** @test */
    public function test_ship_becomes_damaged_below_30_percent_hull()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 100,
            'max_hull' => 100,
            'status' => 'operational',
        ]);

        // Take 75 damage, leaving 25 hull (25% of 100 = below 30% threshold)
        $playerShip->takeDamage(75);

        $this->assertEquals(25, $playerShip->hull);
        $this->assertEquals('damaged', $playerShip->status);
    }

    /** @test */
    public function test_ship_can_be_repaired()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 50,
            'max_hull' => 100,
            'status' => 'damaged',
        ]);

        $playerShip->repair(30);

        $this->assertEquals(80, $playerShip->hull);
    }

    /** @test */
    public function repair_cannot_exceed_max_hull()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 90,
            'max_hull' => 100,
        ]);

        $playerShip->repair(50);

        $this->assertEquals(100, $playerShip->hull); // Capped at max_hull
    }

    /** @test */
    public function repairing_above_30_percent_changes_status_to_operational()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 20,
            'max_hull' => 100,
            'status' => 'damaged',
        ]);

        // Repair to 40 hull (40% of 100 = above 30%)
        $playerShip->repair(20);

        $this->assertEquals(40, $playerShip->hull);
        $this->assertEquals('operational', $playerShip->status);
    }

    /** @test */
    public function sensor_upgrades_work()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'sensors' => 1,
        ]);

        $this->assertEquals(1, $playerShip->sensors);

        // Upgrade sensors
        $playerShip->sensors = 5;
        $playerShip->save();
        $playerShip->refresh();

        $this->assertEquals(5, $playerShip->sensors);
    }

    /** @test */
    public function weapons_upgrades_work()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'weapons' => 10,
        ]);

        $this->assertEquals(10, $playerShip->weapons);

        // Upgrade weapons
        $playerShip->weapons = 50;
        $playerShip->save();
        $playerShip->refresh();

        $this->assertEquals(50, $playerShip->weapons);
    }

    /** @test */
    public function test_fuel_regenerates_over_time()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 50,
            'max_fuel' => 100,
            'fuel_last_updated_at' => Carbon::now()->subSeconds(120), // 2 minutes ago
        ]);

        // Fuel regenerates at 30 seconds per fuel point
        // 120 seconds = 4 fuel points
        $playerShip->regenerateFuel();

        $this->assertEquals(54, $playerShip->current_fuel);
    }

    /** @test */
    public function test_fuel_regeneration_does_not_exceed_max()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 95,
            'max_fuel' => 100,
            'fuel_last_updated_at' => Carbon::now()->subSeconds(300), // 5 minutes ago
        ]);

        // 300 seconds = 10 fuel points, but should cap at 100
        $playerShip->regenerateFuel();

        $this->assertEquals(100, $playerShip->current_fuel);
    }

    /** @test */
    public function test_cargo_can_be_added_within_capacity()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 50,
        ]);

        $result = $playerShip->addCargo(30);

        $this->assertTrue($result);
        $this->assertEquals(80, $playerShip->current_cargo);
    }

    /** @test */
    public function test_cargo_cannot_exceed_capacity()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'cargo_hold' => 100,
            'current_cargo' => 90,
        ]);

        $result = $playerShip->addCargo(20);

        $this->assertFalse($result);
        $this->assertEquals(90, $playerShip->current_cargo); // Unchanged
    }

    /** @test */
    public function test_cargo_can_be_removed()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_cargo' => 50,
        ]);

        $result = $playerShip->removeCargo(30);

        $this->assertTrue($result);
        $this->assertEquals(20, $playerShip->current_cargo);
    }

    /** @test */
    public function test_cannot_remove_more_cargo_than_available()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_cargo' => 30,
        ]);

        $result = $playerShip->removeCargo(50);

        $this->assertFalse($result);
        $this->assertEquals(30, $playerShip->current_cargo); // Unchanged
    }

    /** @test */
    public function test_ship_factory_creates_valid_ship()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
        ]);

        $this->assertNotNull($playerShip->uuid);
        $this->assertEquals($player->id, $playerShip->player_id);
        $this->assertEquals($ship->id, $playerShip->ship_id);
        $this->assertEquals('operational', $playerShip->status);
        $this->assertFalse($playerShip->is_active);
    }

    /** @test */
    public function test_ship_factory_can_create_active_ship()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->active()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
        ]);

        $this->assertTrue($playerShip->is_active);
    }

    /** @test */
    public function test_ship_factory_can_create_damaged_ship()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->damaged(30)->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
        ]);

        $this->assertEquals(30, $playerShip->hull);
        $this->assertEquals('damaged', $playerShip->status);
    }

    /** @test */
    public function test_ship_factory_can_create_destroyed_ship()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->destroyed()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
        ]);

        $this->assertEquals(0, $playerShip->hull);
        $this->assertEquals('destroyed', $playerShip->status);
    }

    /** @test */
    public function test_ship_factory_can_create_ship_with_specific_upgrades()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()
            ->withSensors(7)
            ->withWarpDrive(5)
            ->withWeapons(40)
            ->create([
                'player_id' => $player->id,
                'ship_id' => $ship->id,
            ]);

        $this->assertEquals(7, $playerShip->sensors);
        $this->assertEquals(5, $playerShip->warp_drive);
        $this->assertEquals(40, $playerShip->weapons);
    }

    /** @test */
    public function get_current_fuel_includes_regeneration()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 50,
            'max_fuel' => 100,
            'fuel_last_updated_at' => Carbon::now()->subSeconds(60), // 1 minute ago
        ]);

        // 60 seconds = 2 fuel points
        $currentFuel = $playerShip->getCurrentFuel();

        $this->assertEquals(52, $currentFuel);
    }

    /** @test */
    public function time_until_full_fuel_calculates_correctly()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 50,
            'max_fuel' => 100,
            'fuel_last_updated_at' => Carbon::now(),
        ]);

        // Need 50 fuel points = 50 * 30 seconds = 1500 seconds
        $timeUntilFull = $playerShip->getTimeUntilFullFuel();

        $this->assertEquals(1500, $timeUntilFull);
    }

    /** @test */
    public function deleting_player_cascades_to_ships()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
        ]);

        $playerShipId = $playerShip->id;

        $player->delete();

        // Player ship should be deleted
        $this->assertNull(PlayerShip::find($playerShipId));
    }
}

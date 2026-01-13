<?php

namespace Tests\Feature\Commands;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\Ship;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegenerateFuelCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_regenerates_fuel_for_ships_below_max()
    {
        // Create test data
        $galaxy = Galaxy::factory()->create();
        $user = User::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $shipBlueprint = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $shipBlueprint->id,
            'current_fuel' => 50,
            'max_fuel' => 100,
            'warp_drive' => 1,
            'is_active' => true,
            'fuel_last_updated_at' => Carbon::now()->subHour(), // 1 hour ago
        ]);

        // Run command
        $this->artisan('fuel:regenerate')
            ->assertExitCode(0);

        // Verify fuel increased
        $playerShip->refresh();
        $this->assertGreaterThan(50, $playerShip->current_fuel);
        $this->assertLessThanOrEqual(100, $playerShip->current_fuel);
    }

    public function test_it_does_not_exceed_max_fuel()
    {
        $galaxy = Galaxy::factory()->create();
        $user = User::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $shipBlueprint = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $shipBlueprint->id,
            'current_fuel' => 95,
            'max_fuel' => 100,
            'warp_drive' => 5,
            'is_active' => true,
            'fuel_last_updated_at' => Carbon::now()->subHours(10), // 10 hours ago
        ]);

        $this->artisan('fuel:regenerate')
            ->assertExitCode(0);

        $playerShip->refresh();
        $this->assertEquals(100, $playerShip->current_fuel);
    }

    public function test_it_skips_ships_at_max_fuel()
    {
        $galaxy = Galaxy::factory()->create();
        $user = User::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $shipBlueprint = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $shipBlueprint->id,
            'current_fuel' => 100,
            'max_fuel' => 100,
            'warp_drive' => 1,
            'is_active' => true,
            'fuel_last_updated_at' => Carbon::now()->subHour(),
        ]);

        $this->artisan('fuel:regenerate')
            ->expectsOutput('No ships need fuel regeneration')
            ->assertExitCode(0);

        $playerShip->refresh();
        $this->assertEquals(100, $playerShip->current_fuel);
    }

    public function test_it_skips_inactive_ships()
    {
        $galaxy = Galaxy::factory()->create();
        $user = User::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $shipBlueprint = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $shipBlueprint->id,
            'current_fuel' => 50,
            'max_fuel' => 100,
            'warp_drive' => 1,
            'is_active' => false, // Not active
            'fuel_last_updated_at' => Carbon::now()->subHour(),
        ]);

        $this->artisan('fuel:regenerate')
            ->expectsOutput('No ships need fuel regeneration')
            ->assertExitCode(0);

        $playerShip->refresh();
        $this->assertEquals(50, $playerShip->current_fuel);
    }

    public function test_it_regenerates_more_fuel_with_higher_warp_drive()
    {
        $galaxy = Galaxy::factory()->create();
        $user = User::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $shipBlueprint = Ship::factory()->create();

        // Ship with warp drive 1
        $shipLow = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $shipBlueprint->id,
            'current_fuel' => 0,
            'max_fuel' => 1000,
            'warp_drive' => 1,
            'is_active' => true,
            'fuel_last_updated_at' => Carbon::now()->subHour(),
        ]);

        // Ship with warp drive 5
        $shipHigh = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $shipBlueprint->id,
            'current_fuel' => 0,
            'max_fuel' => 1000,
            'warp_drive' => 5,
            'is_active' => true,
            'fuel_last_updated_at' => Carbon::now()->subHour(),
        ]);

        $this->artisan('fuel:regenerate')
            ->assertExitCode(0);

        $shipLow->refresh();
        $shipHigh->refresh();

        // Higher warp drive should regenerate more fuel
        $this->assertGreaterThan($shipLow->current_fuel, $shipHigh->current_fuel);
    }

    public function test_dry_run_does_not_save_changes()
    {
        $galaxy = Galaxy::factory()->create();
        $user = User::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $shipBlueprint = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $shipBlueprint->id,
            'current_fuel' => 50,
            'max_fuel' => 100,
            'warp_drive' => 1,
            'is_active' => true,
            'fuel_last_updated_at' => Carbon::now()->subHour(),
        ]);

        $this->artisan('fuel:regenerate --dry-run')
            ->assertExitCode(0);

        $playerShip->refresh();
        $this->assertEquals(50, $playerShip->current_fuel);
    }

    public function test_it_processes_specific_player()
    {
        $galaxy = Galaxy::factory()->create();

        // Player 1
        $user1 = User::factory()->create();
        $player1 = Player::factory()->create([
            'user_id' => $user1->id,
            'galaxy_id' => $galaxy->id,
        ]);

        // Player 2
        $user2 = User::factory()->create();
        $player2 = Player::factory()->create([
            'user_id' => $user2->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $shipBlueprint = Ship::factory()->create();

        $ship1 = PlayerShip::factory()->create([
            'player_id' => $player1->id,
            'ship_id' => $shipBlueprint->id,
            'current_fuel' => 50,
            'max_fuel' => 100,
            'is_active' => true,
            'fuel_last_updated_at' => Carbon::now()->subHour(),
        ]);

        $ship2 = PlayerShip::factory()->create([
            'player_id' => $player2->id,
            'ship_id' => $shipBlueprint->id,
            'current_fuel' => 50,
            'max_fuel' => 100,
            'is_active' => true,
            'fuel_last_updated_at' => Carbon::now()->subHour(),
        ]);

        // Process only player 1
        $this->artisan("fuel:regenerate --player={$player1->uuid}")
            ->assertExitCode(0);

        $ship1->refresh();
        $ship2->refresh();

        // Only ship1 should have regenerated
        $this->assertGreaterThan(50, $ship1->current_fuel);
        $this->assertEquals(50, $ship2->current_fuel);
    }

    public function test_it_updates_fuel_last_updated_at()
    {
        $galaxy = Galaxy::factory()->create();
        $user = User::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
        ]);

        $shipBlueprint = Ship::factory()->create();
        $oldTime = Carbon::now()->subHour();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $shipBlueprint->id,
            'current_fuel' => 50,
            'max_fuel' => 100,
            'warp_drive' => 1,
            'is_active' => true,
            'fuel_last_updated_at' => $oldTime,
        ]);

        $this->artisan('fuel:regenerate')
            ->assertExitCode(0);

        $playerShip->refresh();
        $this->assertNotEquals($oldTime->toDateTimeString(), $playerShip->fuel_last_updated_at->toDateTimeString());
    }
}

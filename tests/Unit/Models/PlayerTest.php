<?php

namespace Tests\Unit\Models;

use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\Ship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Player & XP System Tests
 *
 * Critical game mechanic: XP formula must match exactly: Level = floor(sqrt(XP / 100)) + 1
 *
 * @see /TESTING_ROADMAP.md#1-player--xp-system
 */
class PlayerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_xp_formula_calculates_correctly()
    {
        // Test the core XP formula: Level = floor(sqrt(XP / 100)) + 1

        // Level 1: 0 XP (minimum)
        $player = Player::factory()->withExperience(0)->create();
        $this->assertEquals(1, $player->level);

        // Level 2: 100 XP (exactly)
        $player = Player::factory()->withExperience(100)->create();
        $this->assertEquals(2, $player->level);

        // Level 2: 150 XP (mid-range)
        $player = Player::factory()->withExperience(150)->create();
        $this->assertEquals(2, $player->level);

        // Level 3: 400 XP
        $player = Player::factory()->withExperience(400)->create();
        $this->assertEquals(3, $player->level);

        // Level 5: 1600 XP
        $player = Player::factory()->withExperience(1600)->create();
        $this->assertEquals(5, $player->level);

        // Level 10: 8100 XP
        $player = Player::factory()->withExperience(8100)->create();
        $this->assertEquals(10, $player->level);

        // Level 20: 36100 XP
        $player = Player::factory()->withExperience(36100)->create();
        $this->assertEquals(20, $player->level);
    }

    /** @test */
    public function test_player_levels_up_when_reaching_xp_threshold()
    {
        $player = Player::factory()->create([
            'experience' => 0,
            'level' => 1,
        ]);

        $this->assertEquals(1, $player->level);
        $this->assertEquals(0, $player->experience);

        // Add 100 XP - should level up to 2
        $player->addExperience(100);

        $this->assertEquals(2, $player->level);
        $this->assertEquals(100, $player->experience);

        // Add 300 more XP (total 400) - should level up to 3
        $player->addExperience(300);

        $this->assertEquals(3, $player->level);
        $this->assertEquals(400, $player->experience);
    }

    /** @test */
    public function test_player_levels_up_correctly_across_multiple_levels()
    {
        $player = Player::factory()->create([
            'experience' => 0,
            'level' => 1,
        ]);

        // Add massive XP that should skip multiple levels
        // 10,000 XP should result in Level 11
        // Level = floor(sqrt(10000 / 100)) + 1 = floor(sqrt(100)) + 1 = 10 + 1 = 11
        $player->addExperience(10000);

        $this->assertEquals(11, $player->level);
        $this->assertEquals(10000, $player->experience);
    }

    /** @test */
    public function test_player_can_add_experience()
    {
        $player = Player::factory()->create([
            'experience' => 500,
        ]);

        $player->addExperience(250);

        $this->assertEquals(750, $player->experience);
    }

    /** @test */
    public function test_adding_experience_saves_to_database()
    {
        $player = Player::factory()->create([
            'experience' => 0,
            'level' => 1,
        ]);

        $player->addExperience(100);

        // Refresh from database
        $player->refresh();

        $this->assertEquals(100, $player->experience);
        $this->assertEquals(2, $player->level);
    }

    /** @test */
    public function test_player_can_add_credits()
    {
        $player = Player::factory()->create([
            'credits' => 1000.00,
        ]);

        $player->addCredits(500.50);

        $this->assertEquals(1500.50, $player->credits);
    }

    /** @test */
    public function test_player_can_deduct_credits()
    {
        $player = Player::factory()->create([
            'credits' => 1000.00,
        ]);

        $result = $player->deductCredits(300.00);

        $this->assertTrue($result);
        $this->assertEquals(700.00, $player->credits);
    }

    /** @test */
    public function test_cannot_deduct_more_credits_than_available()
    {
        $player = Player::factory()->create([
            'credits' => 500.00,
        ]);

        $result = $player->deductCredits(1000.00);

        $this->assertFalse($result);
        $this->assertEquals(500.00, $player->credits); // Credits unchanged
    }

    /** @test */
    public function test_credits_are_saved_to_database()
    {
        $player = Player::factory()->create([
            'credits' => 1000.00,
        ]);

        $player->addCredits(500.00);
        $player->refresh();

        $this->assertEquals(1500.00, $player->credits);
    }

    /** @test */
    public function test_player_can_have_multiple_ships()
    {
        $player = Player::factory()->create();
        $ship1 = Ship::factory()->create();
        $ship2 = Ship::factory()->create();

        PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship1->id,
        ]);

        PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship2->id,
        ]);

        $this->assertCount(2, $player->ships);
    }

    /** @test */
    public function test_active_ship_relationship_works()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        // Create inactive ship
        PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'is_active' => false,
        ]);

        // Create active ship
        $activeShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'is_active' => true,
        ]);

        $this->assertNotNull($player->activeShip);
        $this->assertEquals($activeShip->id, $player->activeShip->id);
    }

    /** @test */
    public function test_player_with_no_active_ship_returns_null()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        // Create only inactive ships
        PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'is_active' => false,
        ]);

        $this->assertNull($player->activeShip);
    }

    /** @test */
    public function test_player_factory_creates_valid_player()
    {
        $player = Player::factory()->create();

        $this->assertNotNull($player->uuid);
        $this->assertNotNull($player->user_id);
        $this->assertNotNull($player->call_sign);
        $this->assertEquals(1000.00, $player->credits);
        $this->assertEquals(0, $player->experience);
        $this->assertEquals(1, $player->level);
        $this->assertEquals('active', $player->status);
    }

    /** @test */
    public function test_player_factory_can_create_player_at_specific_level()
    {
        $player = Player::factory()->atLevel(5)->create();

        $this->assertEquals(5, $player->level);
        $this->assertGreaterThanOrEqual(1600, $player->experience); // Min XP for level 5
    }

    /** @test */
    public function test_player_factory_can_create_rich_player()
    {
        $player = Player::factory()->rich(50000.00)->create();

        $this->assertEquals(50000.00, $player->credits);
    }

    /** @test */
    public function test_player_factory_can_create_broke_player()
    {
        $player = Player::factory()->broke()->create();

        $this->assertEquals(0.00, $player->credits);
    }

    /** @test */
    public function test_player_factory_can_create_veteran_player()
    {
        $player = Player::factory()->veteran()->create();

        $this->assertEquals(10, $player->level);
        $this->assertEquals(50000.00, $player->credits);
    }

    /** @test */
    public function level_never_decreases_when_adding_experience()
    {
        $player = Player::factory()->atLevel(5)->create();

        $currentLevel = $player->level;

        // Add small amount of XP
        $player->addExperience(10);

        $this->assertGreaterThanOrEqual($currentLevel, $player->level);
    }

    /** @test */
    public function xp_threshold_for_level_2_is_100()
    {
        // Verify Level 2 requires exactly 100 XP
        $player = Player::factory()->create([
            'experience' => 99,
            'level' => 1,
        ]);

        $this->assertEquals(1, $player->level);

        // Add 1 more XP to reach 100
        $player->addExperience(1);

        $this->assertEquals(2, $player->level);
    }

    /** @test */
    public function xp_threshold_for_level_3_is_400()
    {
        // Verify Level 3 requires exactly 400 XP
        $player = Player::factory()->create([
            'experience' => 399,
            'level' => 2,
        ]);

        $this->assertEquals(2, $player->level);

        // Add 1 more XP to reach 400
        $player->addExperience(1);

        $this->assertEquals(3, $player->level);
    }

    /** @test */
    public function test_experience_is_cumulative()
    {
        $player = Player::factory()->create([
            'experience' => 0,
        ]);

        $player->addExperience(50);
        $player->addExperience(50);
        $player->addExperience(50);

        $this->assertEquals(150, $player->experience);
    }

    /** @test */
    public function test_player_uuid_is_auto_generated()
    {
        $player = Player::factory()->create([
            'uuid' => null,
        ]);

        $this->assertNotNull($player->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $player->uuid
        );
    }

    /** @test */
    public function test_player_call_sign_must_be_unique_per_galaxy()
    {
        $callSign = 'TestPilot123';
        $galaxy = \App\Models\Galaxy::factory()->create();

        Player::factory()->create([
            'galaxy_id' => $galaxy->id,
            'call_sign' => $callSign,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Player::factory()->create([
            'galaxy_id' => $galaxy->id,
            'call_sign' => $callSign,
        ]);
    }

    /** @test */
    public function deleting_player_cascades_to_ships()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
        ]);

        $this->assertCount(1, $player->ships);

        $player->delete();

        // Player ships should be deleted
        $this->assertEquals(0, PlayerShip::where('player_id', $player->id)->count());
    }
}

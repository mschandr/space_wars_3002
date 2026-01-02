<?php

namespace Tests\Unit\Services;

use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PirateFleet;
use App\Models\Ship;
use App\Services\CombatResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Combat System Tests
 *
 * Critical mechanics:
 * - Player attacks first (targets weakest pirate)
 * - Damage = weapons ± 20% randomization
 * - Combat continues until one side destroyed
 * - XP Formula: baseXP = 50 * pirateCount + (avgWeapons / 2) + ((pirateCount - 1) * 25)
 * - Minimum XP: 25
 * - Level up can trigger during combat
 *
 * @see /TESTING_ROADMAP.md#3-combat-system-tests
 */
class CombatResolutionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CombatResolutionService $combatService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->combatService = new CombatResolutionService();
    }

    /** @test */
    public function test_player_attacks_first()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 200,
            'max_hull' => 200,
            'weapons' => 50,
        ]);

        $pirateFleet = collect([
            PirateFleet::factory()->create([
                'ship_id' => $ship->id,
                'hull' => 100,
                'max_hull' => 100,
                'weapons' => 10,
            ]),
        ]);

        $result = $this->combatService->resolveCombat($player, $playerShip, $pirateFleet);

        $log = collect($result['log']);

        // Find first attack in combat log
        $firstAttack = $log->first(function ($entry) {
            return in_array($entry['type'], ['player_attack', 'enemy_attack']);
        });

        $this->assertEquals('player_attack', $firstAttack['type']);
    }

    /** @test */
    public function test_player_targets_weakest_pirate()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 200,
            'max_hull' => 200,
            'weapons' => 100, // High damage to finish quickly
        ]);

        $pirateFleet = collect([
            PirateFleet::factory()->create([
                'ship_id' => $ship->id,
                'ship_name' => 'Strong Pirate',
                'hull' => 150,
                'max_hull' => 150,
                'weapons' => 10,
            ]),
            PirateFleet::factory()->create([
                'ship_id' => $ship->id,
                'ship_name' => 'Weak Pirate',
                'hull' => 50, // Weakest
                'max_hull' => 100,
                'weapons' => 10,
            ]),
        ]);

        $result = $this->combatService->resolveCombat($player, $playerShip, $pirateFleet);

        $log = collect($result['log']);

        // First player attack should target "Weak Pirate"
        $firstAttack = $log->first(fn ($e) => $e['type'] === 'player_attack');
        $this->assertStringContainsString('Weak Pirate', $firstAttack['message']);
    }

    /** @test */
    public function test_damage_applies_randomization()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 500,
            'max_hull' => 500,
            'weapons' => 100,
        ]);

        // Run combat multiple times to verify randomization
        $damages = [];
        for ($i = 0; $i < 10; $i++) {
            $pirateFleet = collect([
                PirateFleet::factory()->create([
                    'ship_id' => $ship->id,
                    'hull' => 1000, // High hull to survive multiple hits
                    'max_hull' => 1000,
                    'weapons' => 5, // Low weapons so player survives
                ]),
            ]);

            $result = $this->combatService->resolveCombat($player, $playerShip, $pirateFleet);

            $log = collect($result['log']);
            $firstAttack = $log->first(fn ($e) => $e['type'] === 'player_attack');

            // Extract damage from message (e.g., "for 95 damage")
            preg_match('/for (\d+) damage/', $firstAttack['message'], $matches);
            if (isset($matches[1])) {
                $damages[] = (int) $matches[1];
            }

            // Reset player ship for next iteration
            $playerShip->hull = 500;
            $playerShip->save();
        }

        // Verify damage varies (not all the same)
        $uniqueDamages = array_unique($damages);
        $this->assertGreaterThan(1, count($uniqueDamages), 'Damage should vary due to randomization');

        // Verify damage is within ±20% range (weapons = 100, so 80-120)
        foreach ($damages as $damage) {
            $this->assertGreaterThanOrEqual(80, $damage);
            $this->assertLessThanOrEqual(120, $damage);
        }
    }

    /** @test */
    public function test_combat_continues_until_one_side_destroyed()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 150,
            'max_hull' => 150,
            'weapons' => 30,
        ]);

        $pirateFleet = collect([
            PirateFleet::factory()->create([
                'ship_id' => $ship->id,
                'hull' => 100,
                'max_hull' => 100,
                'weapons' => 20,
            ]),
        ]);

        $result = $this->combatService->resolveCombat($player, $playerShip, $pirateFleet);

        // Either player or pirate should be destroyed
        $playerDestroyed = $playerShip->hull <= 0;
        $piratesDestroyed = $pirateFleet->where('hull', '>', 0)->count() === 0;

        $this->assertTrue($playerDestroyed || $piratesDestroyed);
    }

    /** @test */
    public function victory_awards_xp()
    {
        $player = Player::factory()->create([
            'experience' => 0,
            'level' => 1,
        ]);
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 200,
            'max_hull' => 200,
            'weapons' => 100, // High weapons to ensure victory
        ]);

        $pirateFleet = collect([
            PirateFleet::factory()->create([
                'ship_id' => $ship->id,
                'hull' => 50,
                'max_hull' => 50,
                'weapons' => 10,
            ]),
        ]);

        $initialXP = $player->experience;

        $result = $this->combatService->resolveCombat($player, $playerShip, $pirateFleet);

        $this->assertTrue($result['victory']);
        $this->assertGreaterThan(0, $result['xp_earned']);
        $this->assertGreaterThan($initialXP, $player->fresh()->experience);
    }

    /** @test */
    public function test_xp_formula_calculates_correctly()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 500,
            'max_hull' => 500,
            'weapons' => 100,
        ]);

        // Test 1: Single pirate with 20 weapons
        // Expected: baseXP = 50 * 1 = 50
        //          difficultyBonus = 20 / 2 = 10
        //          fleetBonus = (1-1) * 25 = 0
        //          Total = 50 + 10 + 0 = 60 XP
        $pirateFleet1 = collect([
            PirateFleet::factory()->create([
                'ship_id' => $ship->id,
                'hull' => 50,
                'weapons' => 20,
            ]),
        ]);

        $result1 = $this->combatService->resolveCombat($player, $playerShip, $pirateFleet1);
        $this->assertEquals(60, $result1['xp_earned']);

        // Reset
        $playerShip->hull = 500;
        $playerShip->save();

        // Test 2: Two pirates with 30 weapons each
        // Expected: baseXP = 50 * 2 = 100
        //          avgWeapons = 60 / 2 = 30
        //          difficultyBonus = 30 / 2 = 15
        //          fleetBonus = (2-1) * 25 = 25
        //          Total = 100 + 15 + 25 = 140 XP
        $pirateFleet2 = collect([
            PirateFleet::factory()->create([
                'ship_id' => $ship->id,
                'hull' => 50,
                'weapons' => 30,
            ]),
            PirateFleet::factory()->create([
                'ship_id' => $ship->id,
                'hull' => 50,
                'weapons' => 30,
            ]),
        ]);

        $result2 = $this->combatService->resolveCombat($player, $playerShip, $pirateFleet2);
        $this->assertEquals(140, $result2['xp_earned']);
    }

    /** @test */
    public function test_xp_has_minimum_value()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 200,
            'max_hull' => 200,
            'weapons' => 100,
        ]);

        // Very weak pirate (should still give at least 25 XP)
        $pirateFleet = collect([
            PirateFleet::factory()->create([
                'ship_id' => $ship->id,
                'hull' => 10,
                'weapons' => 1,
            ]),
        ]);

        $result = $this->combatService->resolveCombat($player, $playerShip, $pirateFleet);

        $this->assertGreaterThanOrEqual(25, $result['xp_earned']);
    }

    /** @test */
    public function test_xp_scales_with_pirate_difficulty()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 500,
            'max_hull' => 500,
            'weapons' => 100,
        ]);

        // Weak pirate
        $weakPirate = collect([
            PirateFleet::factory()->weak()->create([
                'ship_id' => $ship->id,
            ]),
        ]);

        $result1 = $this->combatService->resolveCombat($player, $playerShip, $weakPirate);
        $weakXP = $result1['xp_earned'];

        // Reset
        $playerShip->hull = 500;
        $playerShip->save();

        // Strong pirate
        $strongPirate = collect([
            PirateFleet::factory()->strong()->create([
                'ship_id' => $ship->id,
            ]),
        ]);

        $result2 = $this->combatService->resolveCombat($player, $playerShip, $strongPirate);
        $strongXP = $result2['xp_earned'];

        $this->assertGreaterThan($weakXP, $strongXP);
    }

    /** @test */
    public function level_up_triggers_during_combat()
    {
        // Player at 95 XP (5 XP away from level 2 which requires 100 XP)
        $player = Player::factory()->create([
            'experience' => 95,
            'level' => 1,
        ]);
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 200,
            'max_hull' => 200,
            'weapons' => 100,
        ]);

        $pirateFleet = collect([
            PirateFleet::factory()->create([
                'ship_id' => $ship->id,
                'hull' => 50,
                'weapons' => 20, // Will give ~60 XP, enough to level up
            ]),
        ]);

        $result = $this->combatService->resolveCombat($player, $playerShip, $pirateFleet);

        $this->assertTrue($result['victory']);
        $this->assertEquals(2, $player->fresh()->level);

        // Check combat log for level up message
        $log = collect($result['log']);
        $levelUpEntry = $log->first(fn ($e) => $e['type'] === 'levelup');
        $this->assertNotNull($levelUpEntry);
        $this->assertStringContainsString('LEVEL UP', $levelUpEntry['message']);
    }

    /** @test */
    public function test_combat_log_records_all_events()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 200,
            'max_hull' => 200,
            'weapons' => 50,
        ]);

        $pirateFleet = collect([
            PirateFleet::factory()->create([
                'ship_id' => $ship->id,
                'hull' => 50,
                'weapons' => 20,
            ]),
        ]);

        $result = $this->combatService->resolveCombat($player, $playerShip, $pirateFleet);

        $log = collect($result['log']);

        // Should have header
        $this->assertTrue($log->contains(fn ($e) => $e['type'] === 'header'));

        // Should have round markers
        $this->assertTrue($log->contains(fn ($e) => $e['type'] === 'round'));

        // Should have player attacks
        $this->assertTrue($log->contains(fn ($e) => $e['type'] === 'player_attack'));

        // Should have XP award (if victory)
        if ($result['victory']) {
            $this->assertTrue($log->contains(fn ($e) => $e['type'] === 'xp'));
        }

        // Should have dividers
        $this->assertTrue($log->contains(fn ($e) => $e['type'] === 'divider'));
    }

    /** @test */
    public function test_player_death_triggers_when_hull_reaches_zero()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 50, // Low hull
            'max_hull' => 100,
            'weapons' => 10,
        ]);

        $pirateFleet = collect([
            PirateFleet::factory()->create([
                'ship_id' => $ship->id,
                'hull' => 200,
                'weapons' => 50, // High damage
            ]),
        ]);

        $result = $this->combatService->resolveCombat($player, $playerShip, $pirateFleet);

        $this->assertFalse($result['victory']);
        $this->assertEquals(0, $result['player_hull_remaining']);

        // Check combat log for defeat message
        $log = collect($result['log']);
        $this->assertTrue($log->contains(fn ($e) => $e['type'] === 'defeat'));
    }

    /** @test */
    public function multiple_pirates_all_attack_player()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 500,
            'max_hull' => 500,
            'weapons' => 30,
        ]);

        $pirateFleet = collect([
            PirateFleet::factory()->create([
                'ship_id' => $ship->id,
                'ship_name' => 'Pirate 1',
                'hull' => 200,
                'weapons' => 20,
            ]),
            PirateFleet::factory()->create([
                'ship_id' => $ship->id,
                'ship_name' => 'Pirate 2',
                'hull' => 200,
                'weapons' => 20,
            ]),
            PirateFleet::factory()->create([
                'ship_id' => $ship->id,
                'ship_name' => 'Pirate 3',
                'hull' => 200,
                'weapons' => 20,
            ]),
        ]);

        $result = $this->combatService->resolveCombat($player, $playerShip, $pirateFleet);

        $log = collect($result['log']);

        // In first round, should have attacks from all 3 pirates
        $round1Attacks = $log->filter(function ($entry) {
            return $entry['type'] === 'enemy_attack';
        })->take(3); // First 3 enemy attacks should be from round 1

        $this->assertGreaterThanOrEqual(3, $round1Attacks->count());
    }

    /** @test */
    public function test_combat_preview_estimates_difficulty()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'weapons' => 50,
        ]);

        // Easy fight
        $weakPirates = collect([
            PirateFleet::factory()->weak()->create([
                'ship_id' => $ship->id,
            ]),
        ]);

        $preview = $this->combatService->getCombatPreview($playerShip, $weakPirates);

        $this->assertEquals('Easy', $preview['difficulty']);
        $this->assertEquals(90, $preview['estimated_win_chance']);
    }

    /** @test */
    public function test_no_xp_awarded_on_defeat()
    {
        $player = Player::factory()->create([
            'experience' => 100,
        ]);
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 50,
            'weapons' => 10,
        ]);

        $pirateFleet = collect([
            PirateFleet::factory()->strong()->create([
                'ship_id' => $ship->id,
            ]),
        ]);

        $initialXP = $player->experience;

        $result = $this->combatService->resolveCombat($player, $playerShip, $pirateFleet);

        $this->assertFalse($result['victory']);
        $this->assertEquals(0, $result['xp_earned']);
        $this->assertEquals($initialXP, $player->fresh()->experience);
    }

    /** @test */
    public function test_combat_updates_player_ship_state()
    {
        $player = Player::factory()->create();
        $ship = Ship::factory()->create();

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hull' => 150,
            'max_hull' => 150,
            'weapons' => 30,
        ]);

        $initialHull = $playerShip->hull;

        // Create a pirate strong enough to hit back at least once
        $pirateFleet = collect([
            PirateFleet::factory()->create([
                'ship_id' => $ship->id,
                'hull' => 100,
                'max_hull' => 100,
                'weapons' => 25,
            ]),
        ]);

        $result = $this->combatService->resolveCombat($player, $playerShip, $pirateFleet);

        // Hull should be updated in database
        $playerShip->refresh();

        // Combat should change hull state (either damage if victory, or zero if defeat)
        $this->assertNotEquals($initialHull, $playerShip->hull);

        if ($result['victory']) {
            // Player survived but took damage
            $this->assertGreaterThan(0, $playerShip->hull);
        } else {
            // Player was destroyed
            $this->assertEquals(0, $playerShip->hull);
        }
    }
}

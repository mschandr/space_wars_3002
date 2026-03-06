<?php

namespace Tests\Feature;

use App\Models\Flotilla;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\User;
use App\Models\WarpLanePirate;
use App\Services\Combat\CombatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlotillaCombatIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Player $player;
    private User $user;
    private Flotilla $flotilla;
    private CombatService $combatService;
    private PointOfInterest $poi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->combatService = app(CombatService::class);
        $this->user = User::factory()->create();
        $this->player = Player::factory()->create(['user_id' => $this->user->id]);
        $this->poi = PointOfInterest::factory()->create();

        // Create a flotilla with 2 ships
        $flagship = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'current_poi_id' => $this->poi->id,
            'weapons' => 5,
            'hull' => 100,
            'warp_drive' => 3,
        ]);
        $member = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'current_poi_id' => $this->poi->id,
            'weapons' => 3,
            'hull' => 80,
            'warp_drive' => 3,
        ]);


        $this->flotilla = Flotilla::create([
            'player_id' => $this->player->id,
            'flagship_ship_id' => $flagship->id,
            'name' => 'Combat Flotilla',
        ]);
        $flagship->update(['flotilla_id' => $this->flotilla->id]);
        $member->update(['flotilla_id' => $this->flotilla->id]);
    }

    /** @test */
    public function testCanCombatAsFlotilla_returns_true_for_multi_ship_flotilla(): void
    {
        $canFight = $this->combatService->canCombatAsFlotilla($this->player);

        $this->assertTrue($canFight);
    }

    /** @test */
    public function testGetCombatReadiness_shows_flotilla_info(): void
    {
        $readiness = $this->combatService->getCombatReadiness($this->player);

        $this->assertTrue($readiness['has_flotilla']);
        $this->assertEquals(2, $readiness['flotilla_ship_count']);
        $this->assertTrue($readiness['will_engage_as_flotilla']);
        $this->assertNotNull($readiness['flotilla_details']);
    }

    // NOTE: Tests requiring WarpLanePirate factory are disabled until factory is created
    // These would test advanced combat integration scenarios

    /** @test */
    public function testAttemptFlotillaEscape_has_escape_chance(): void
    {
        $pirateFleet = [
            ['ship_name' => 'Pirate 1', 'speed' => 2, 'hull' => 50],
        ];

        $result = $this->combatService->attemptFlotillaEscape($this->flotilla, $pirateFleet);

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('escape_chance', $result);
        $this->assertGreaterThanOrEqual(0, $result['escape_chance']);
        $this->assertLessThanOrEqual(100, $result['escape_chance']);
    }

    /** @test */
    public function testAttemptFlotillaEscape_uses_slowest_ship_speed(): void
    {
        // Add a slow ship
        $slowShip = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'current_poi_id' => $this->flotilla->flagship->current_poi_id,
            'warp_drive' => 1, // Very slow
            'hull' => 50,
        ]);
        $slowShip->update(['flotilla_id' => $this->flotilla->id]);

        $pirateFleet = [
            ['ship_name' => 'Pirate 1', 'speed' => 3, 'hull' => 50], // Faster than flotilla
        ];

        $result = $this->combatService->attemptFlotillaEscape($this->flotilla, $pirateFleet);

        // Escape should be harder with slow ship
        $this->assertLessThan(50, $result['escape_chance']);
    }

    /** @test */
    public function testHandleFlotillaSurrender_loses_cargo_from_all_ships(): void
    {
        $this->flotilla->flagship->update(['current_cargo' => 100]);
        $this->flotilla->ships[1]->update(['current_cargo' => 50]);

        $result = $this->combatService->handleFlotillaSurrender($this->player, $this->flotilla);

        $this->assertTrue($result['surrendered']);
        $this->assertEquals(
            (int) ((100 + 50) * 0.7),
            $result['total_cargo_lost']
        );
    }

    /** @test */
    public function testGetCombatReadiness_returns_single_ship_when_no_flotilla(): void
    {
        // Create new player without flotilla
        $newUser = User::factory()->create();
        $newPlayer = Player::factory()->create(['user_id' => $newUser->id]);
        $ship = PlayerShip::factory()->create(['player_id' => $newPlayer->id]);
        $newPlayer->update(['current_ship_id' => $ship->id]);

        $readiness = $this->combatService->getCombatReadiness($newPlayer);

        $this->assertFalse($readiness['has_flotilla']);
        $this->assertFalse($readiness['will_engage_as_flotilla']);
    }

    /** @test */
    public function testGetCombatReadiness_returns_single_ship_when_flotilla_has_one_ship(): void
    {
        // Remove member ship
        $this->flotilla->ships()->where('id', '!=', $this->flotilla->flagship_ship_id)->delete();

        $readiness = $this->combatService->getCombatReadiness($this->player);

        $this->assertTrue($readiness['has_flotilla']);
        $this->assertFalse($readiness['will_engage_as_flotilla']);
    }
}

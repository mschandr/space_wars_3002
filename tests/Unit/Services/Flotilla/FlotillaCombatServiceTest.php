<?php

namespace Tests\Unit\Services\Flotilla;

use App\Models\Flotilla;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Services\Flotilla\FlotillaCombatService;
use App\Services\Flotilla\FlotillaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlotillaCombatServiceTest extends TestCase
{
    use RefreshDatabase;

    private FlotillaCombatService $combatService;
    private FlotillaService $flotillaService;
    private Player $player;
    private Flotilla $flotilla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->combatService = app(FlotillaCombatService::class);
        $this->flotillaService = app(FlotillaService::class);

        $this->player = Player::factory()->create();
        $flagship = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'weapons' => 5,
            'hull' => 100,
        ]);
        $this->flotilla = $this->flotillaService->createFlotilla($this->player, $flagship);
    }

    /** @test */
    public function testGetTotalFlotillaWeaponDamage_returns_positive_damage(): void
    {
        $damage = $this->combatService->getTotalFlotillaWeaponDamage($this->flotilla);

        $this->assertGreaterThan(0, $damage);
    }

    /** @test */
    public function testSelectPirateFocusTarget_returns_weakest_ship(): void
    {
        $member = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'current_poi_id' => $this->flotilla->flagship->current_poi_id,
            'hull' => 50, // Weaker than flagship (100)
        ]);
        $this->flotillaService->addShipToFlotilla($this->flotilla, $member);

        $target = $this->combatService->selectPirateFocusTarget($this->flotilla);

        $this->assertEquals($member->id, $target->id);
    }

    /** @test */
    public function testApplyDamageToFlotilla_damages_target_ship(): void
    {
        $initialHull = $this->flotilla->flagship->hull;

        $result = $this->combatService->applyDamageToFlotilla($this->flotilla, 30);

        $this->assertEquals(30, $result['damage_applied']);
        $this->assertLessThan($initialHull, $this->flotilla->flagship->refresh()->hull);
    }

    /** @test */
    public function testApplyDamageToFlotilla_marks_ship_destroyed_at_zero_hull(): void
    {
        $this->flotilla->flagship->update(['hull' => 30]);

        $result = $this->combatService->applyDamageToFlotilla($this->flotilla, 40);

        $this->assertTrue($result['ship_destroyed']);
        $this->assertLessThanOrEqual(0, $this->flotilla->flagship->refresh()->hull);
    }

    /** @test */
    public function testHandleShipDestructionInCombat_promotes_next_largest_if_flagship_destroyed(): void
    {
        $member1 = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'current_poi_id' => $this->flotilla->flagship->current_poi_id,
            'hull' => 80,
        ]);
        $member2 = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'current_poi_id' => $this->flotilla->flagship->current_poi_id,
            'hull' => 60,
        ]);
        $this->flotillaService->addShipToFlotilla($this->flotilla, $member1);
        $this->flotillaService->addShipToFlotilla($this->flotilla, $member2);

        $flagship = $this->flotilla->flagship;
        $this->combatService->handleShipDestructionInCombat($this->flotilla, $flagship);

        $this->assertEquals($member1->id, $this->flotilla->refresh()->flagship_ship_id);
    }

    /** @test */
    public function testIsFlotillaCombatCapable_returns_true_when_has_ships(): void
    {
        $capable = $this->combatService->isFlotillaCombatCapable($this->flotilla);

        $this->assertTrue($capable);
    }

    /** @test */
    public function testGetFlotillaCombatStatus_returns_complete_status(): void
    {
        $status = $this->combatService->getFlotillaCombatStatus($this->flotilla);

        $this->assertArrayHasKey('total_ships', $status);
        $this->assertArrayHasKey('total_hull', $status);
        $this->assertArrayHasKey('total_weapon_damage', $status);
        $this->assertArrayHasKey('is_combat_capable', $status);
        $this->assertTrue($status['is_combat_capable']);
    }
}

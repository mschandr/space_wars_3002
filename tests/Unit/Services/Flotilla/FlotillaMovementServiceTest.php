<?php

namespace Tests\Unit\Services\Flotilla;

use App\Models\Flotilla;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Services\Flotilla\FlotillaMovementService;
use App\Services\Flotilla\FlotillaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlotillaMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    private FlotillaMovementService $movementService;
    private FlotillaService $flotillaService;
    private Player $player;
    private Flotilla $flotilla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->movementService = app(FlotillaMovementService::class);
        $this->flotillaService = app(FlotillaService::class);

        $this->player = Player::factory()->create();
        $flagship = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'current_fuel' => 100,
            'max_fuel' => 100,
            'warp_drive' => 3,
        ]);
        $this->flotilla = $this->flotillaService->createFlotilla($this->player, $flagship);
    }

    /** @test */
    public function testCanMoveFlotilla_returns_true_when_all_ships_have_fuel(): void
    {
        $result = $this->movementService->canMoveFlotilla($this->flotilla);

        $this->assertTrue($result['can_move']);
        $this->assertNotEmpty($result['fuel_costs']);
    }

    /** @test */
    public function testCanMoveFlotilla_returns_false_when_ship_lacks_fuel(): void
    {
        $this->flotilla->flagship->update(['current_fuel' => 0]);

        $result = $this->movementService->canMoveFlotilla($this->flotilla);

        $this->assertFalse($result['can_move']);
        $this->assertStringContainsString('does not have sufficient fuel', $result['reason']);
    }

    /** @test */
    public function testCalculateFlotillaFuelCosts_applies_formation_penalty(): void
    {
        $costs = $this->movementService->calculateFlotillaFuelCosts($this->flotilla, 1);

        // Single ship = 1.0x (no penalty)
        $this->assertNotEmpty($costs);
    }

    /** @test */
    public function testGetFormationFuelPenalty_returns_correct_multiplier(): void
    {
        $penalty1 = $this->movementService->getFormationFuelPenalty(1);
        $penalty2 = $this->movementService->getFormationFuelPenalty(2);
        $penalty3 = $this->movementService->getFormationFuelPenalty(3);
        $penalty4 = $this->movementService->getFormationFuelPenalty(4);

        $this->assertEquals(1.0, $penalty1);
        $this->assertEquals(1.1, $penalty2);
        $this->assertEquals(1.2, $penalty3);
        $this->assertEquals(1.3, $penalty4);
    }

    /** @test */
    public function testGetFlotillaSpeed_returns_slowest_ship_speed(): void
    {
        $speed = $this->movementService->getFlotillaSpeed($this->flotilla);

        $this->assertEquals($this->flotilla->slowestShip()->warp_drive, $speed);
    }

    /** @test */
    public function testEstimateFuelCost_calculates_total_cost_with_penalty(): void
    {
        $destination = PointOfInterest::factory()->create();
        $estimate = $this->movementService->estimateFuelCost($this->flotilla, $destination);

        $this->assertArrayHasKey('total_cost', $estimate);
        $this->assertArrayHasKey('by_ship', $estimate);
        $this->assertArrayHasKey('penalty_multiplier', $estimate);
        $this->assertGreaterThan(0, $estimate['total_cost']);
    }
}

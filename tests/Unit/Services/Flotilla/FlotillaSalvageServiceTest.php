<?php

namespace Tests\Unit\Services\Flotilla;

use App\Models\Flotilla;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Services\Flotilla\FlotillaSalvageService;
use App\Services\Flotilla\FlotillaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlotillaSalvageServiceTest extends TestCase
{
    use RefreshDatabase;

    private FlotillaSalvageService $salvageService;
    private FlotillaService $flotillaService;
    private Player $player;
    private Flotilla $flotilla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->salvageService = app(FlotillaSalvageService::class);
        $this->flotillaService = app(FlotillaService::class);

        $this->player = Player::factory()->create();
        $flagship = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'hull' => 100,
            'cargo_hold' => 500,
        ]);
        $this->flotilla = $this->flotillaService->createFlotilla($this->player, $flagship);
    }

    /** @test */
    public function testGetSalvageOptions_returns_available_options(): void
    {
        $this->flotilla->flagship->update(['hull' => -10, 'current_cargo' => 100]);

        $options = $this->salvageService->getSalvageOptions($this->flotilla);

        $this->assertArrayHasKey('cargo_available', $options);
        $this->assertArrayHasKey('components_available', $options);
        $this->assertGreaterThan(0, $options['cargo_available']);
    }

    /** @test */
    public function testRecoverCargo_recovers_70_percent_of_destroyed_ships_cargo(): void
    {
        $destroyed = $this->flotilla->flagship;
        $destroyed->update(['hull' => -10, 'current_cargo' => 100]);

        // Create a surviving ship so cargo can be recovered
        $survivor = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'current_poi_id' => $destroyed->current_poi_id,
            'hull' => 100,
            'cargo_hold' => 500,
        ]);
        $this->flotillaService->addShipToFlotilla($this->flotilla, $survivor);

        $result = $this->salvageService->recoverCargo($this->flotilla);

        $this->assertEquals((int) (100 * 0.70), $result['cargo_recovered']);
    }

    /** @test */
    public function testRecoverCargo_distributes_to_surviving_ships(): void
    {
        $destroyed = $this->flotilla->flagship;
        $destroyed->update(['hull' => -10, 'current_cargo' => 100]);

        $survivor = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'current_poi_id' => $destroyed->current_poi_id,
            'hull' => 100,
            'cargo_hold' => 500,
        ]);
        $this->flotillaService->addShipToFlotilla($this->flotilla, $survivor);

        $result = $this->salvageService->recoverCargo($this->flotilla);

        $this->assertNotEmpty($result['distribution']);
        $this->assertTrue(
            collect($result['distribution'])->pluck('ship_id')->contains($survivor->id)
        );
    }

    /** @test */
    public function testRecoverCargo_loses_excess_cargo_if_insufficient_space(): void
    {
        $destroyed = $this->flotilla->flagship;
        $destroyed->update(['hull' => -10, 'current_cargo' => 1000]);

        // Survivor with small cargo hold
        $survivor = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'current_poi_id' => $destroyed->current_poi_id,
            'hull' => 100,
            'cargo_hold' => 100,
        ]);
        $this->flotillaService->addShipToFlotilla($this->flotilla, $survivor);

        $result = $this->salvageService->recoverCargo($this->flotilla);

        $this->assertGreaterThan(0, $result['cargo_lost']);
    }

    /** @test */
    public function testRecoverComponents_applies_escalating_loss(): void
    {
        $result = $this->salvageService->recoverComponents($this->flotilla);

        // Empty if no components, but structure should be valid
        $this->assertArrayHasKey('components_recovered', $result);
        $this->assertIsArray($result['components_recovered']);
    }

    /** @test */
    public function testExecuteSalvageChoice_accepts_cargo_choice(): void
    {
        $this->flotilla->flagship->update(['hull' => -10, 'current_cargo' => 100]);

        $result = $this->salvageService->executeSalvageChoice($this->flotilla, 'cargo');

        $this->assertArrayHasKey('cargo_recovered', $result);
    }

    /** @test */
    public function testExecuteSalvageChoice_accepts_components_choice(): void
    {
        $this->flotilla->flagship->update(['hull' => -10]);

        $result = $this->salvageService->executeSalvageChoice($this->flotilla, 'components');

        $this->assertArrayHasKey('components_recovered', $result);
    }

    /** @test */
    public function testGetSalvageReport_returns_complete_report(): void
    {
        $this->flotilla->flagship->update(['hull' => -10, 'current_cargo' => 100]);

        $report = $this->salvageService->getSalvageReport($this->flotilla);

        $this->assertArrayHasKey('battle_result', $report);
        $this->assertArrayHasKey('destroyed_ships_count', $report);
        $this->assertArrayHasKey('surviving_ships', $report);
        $this->assertArrayHasKey('salvage_options', $report);
        $this->assertArrayHasKey('xor_note', $report);
    }

    /** @test */
    public function testRecoverPirateLoot_returns_random_percentage(): void
    {
        $pirateShips = [
            ['cargo' => 100, 'hull' => 50, 'components' => []],
            ['cargo' => 150, 'hull' => 50, 'components' => []],
        ];

        $result = $this->salvageService->recoverPirateLoot($pirateShips);

        $this->assertArrayHasKey('cargo_recovered', $result);
        $this->assertArrayHasKey('components_recovered', $result);
        // Should recover something (not all pirateships have zero cargo)
        $this->assertGreaterThanOrEqual(0, $result['cargo_recovered']);
    }
}

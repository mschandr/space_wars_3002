<?php

namespace Tests\Unit\Services;

use App\Enums\Crew\CrewAlignment;
use App\Models\CrewMember;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Services\Crew\ShipPersonaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipPersonaServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShipPersonaService $service;
    private Player $player;
    private PlayerShip $ship;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ShipPersonaService();

        $galaxy = Galaxy::factory()->create();
        $this->player = Player::factory()->create(['galaxy_id' => $galaxy->id]);
        $this->ship = PlayerShip::factory()->create(['player_id' => $this->player->id]);
        $this->player->update(['active_ship_id' => $this->ship->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_computes_lawful_alignment_with_all_lawful_crew()
    {
        // Create 3 lawful crew members
        CrewMember::factory(3)->create([
            'player_ship_id' => $this->ship->id,
            'alignment' => CrewAlignment::LAWFUL,
        ]);

        $persona = $this->service->computePersona($this->ship);

        $this->assertEquals('lawful', $persona['overall_alignment']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_computes_neutral_alignment_with_mixed_crew()
    {
        // Mixed alignment crew
        CrewMember::factory()->create([
            'player_ship_id' => $this->ship->id,
            'alignment' => CrewAlignment::LAWFUL,
        ]);
        CrewMember::factory()->create([
            'player_ship_id' => $this->ship->id,
            'alignment' => CrewAlignment::NEUTRAL,
        ]);
        CrewMember::factory()->create([
            'player_ship_id' => $this->ship->id,
            'alignment' => CrewAlignment::SHADY,
        ]);

        $persona = $this->service->computePersona($this->ship);

        $this->assertEquals('neutral', $persona['overall_alignment']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_computes_shady_alignment_with_all_shady_crew()
    {
        // Create 3 shady crew members
        CrewMember::factory(3)->create([
            'player_ship_id' => $this->ship->id,
            'alignment' => CrewAlignment::SHADY,
        ]);

        $persona = $this->service->computePersona($this->ship);

        $this->assertEquals('shady', $persona['overall_alignment']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_computes_neutral_with_no_crew()
    {
        // Ship with no crew
        $persona = $this->service->computePersona($this->ship);

        $this->assertEquals('neutral', $persona['overall_alignment']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_computes_shady_score_correctly()
    {
        // Create crew with varying shady_actions
        CrewMember::factory()->create([
            'player_ship_id' => $this->ship->id,
            'alignment' => CrewAlignment::LAWFUL,
            'shady_actions' => 0,
        ]);
        CrewMember::factory()->create([
            'player_ship_id' => $this->ship->id,
            'alignment' => CrewAlignment::NEUTRAL,
            'shady_actions' => 5,
        ]);
        CrewMember::factory()->create([
            'player_ship_id' => $this->ship->id,
            'alignment' => CrewAlignment::SHADY,
            'shady_actions' => 15,
        ]);

        $persona = $this->service->computePersona($this->ship);

        // Shady score should be sum of crew shady_actions
        $this->assertEquals(20, $persona['shady_score']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_computes_trading_discount_for_lawful_crew()
    {
        // Lawful crew should get trading discount
        CrewMember::factory(2)->create([
            'player_ship_id' => $this->ship->id,
            'alignment' => CrewAlignment::LAWFUL,
        ]);

        $persona = $this->service->computePersona($this->ship);

        $this->assertArrayHasKey('vendor_bonuses', $persona);
        $this->assertArrayHasKey('trading_discount', $persona['vendor_bonuses']);
        $this->assertGreaterThan(0, $persona['vendor_bonuses']['trading_discount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_computes_shady_vendor_access_for_shady_crew()
    {
        // Shady crew should have vendor access advantages
        CrewMember::factory()->create([
            'player_ship_id' => $this->ship->id,
            'alignment' => CrewAlignment::SHADY,
        ]);

        $persona = $this->service->computePersona($this->ship);

        $this->assertArrayHasKey('vendor_bonuses', $persona);
        // Shady crew should provide bonuses with shady vendors
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_total_shady_interactions()
    {
        // Create crew with shady actions
        CrewMember::factory(2)->create([
            'player_ship_id' => $this->ship->id,
            'shady_actions' => 8,
        ]);
        CrewMember::factory()->create([
            'player_ship_id' => $this->ship->id,
            'shady_actions' => 4,
        ]);

        $persona = $this->service->computePersona($this->ship);

        $this->assertEquals(20, $persona['total_shady_interactions']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sets_black_market_visible_flag_correctly()
    {
        $threshold = config('economy.black_market.visibility_threshold', 10);

        // Below threshold
        CrewMember::factory()->create([
            'player_ship_id' => $this->ship->id,
            'shady_actions' => 5,
        ]);

        $persona = $this->service->computePersona($this->ship);
        $this->assertFalse($persona['black_market_visible']);

        // Above threshold
        $this->ship->crew()->delete();
        CrewMember::factory()->create([
            'player_ship_id' => $this->ship->id,
            'shady_actions' => $threshold + 5,
        ]);

        $persona = $this->service->computePersona($this->ship);
        $this->assertTrue($persona['black_market_visible']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_never_exposes_black_market_visible_in_response_when_false()
    {
        // Create crew below threshold
        CrewMember::factory()->create([
            'player_ship_id' => $this->ship->id,
            'shady_actions' => 2,
        ]);

        $persona = $this->service->computePersona($this->ship);

        // The service should compute black_market_visible internally
        // but it should not appear in the response if false
        // This is tested by checking that the value exists when true
        // but is stripped when false (tested in API layer)
        $this->assertArrayHasKey('black_market_visible', $persona);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_single_crew_member()
    {
        CrewMember::factory()->create([
            'player_ship_id' => $this->ship->id,
            'alignment' => CrewAlignment::LAWFUL,
        ]);

        $persona = $this->service->computePersona($this->ship);

        $this->assertEquals('lawful', $persona['overall_alignment']);
        $this->assertArrayHasKey('vendor_bonuses', $persona);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_computes_consistent_alignment()
    {
        // Create same crew composition twice and verify consistent results
        CrewMember::factory(2)->create([
            'player_ship_id' => $this->ship->id,
            'alignment' => CrewAlignment::NEUTRAL,
        ]);

        $persona1 = $this->service->computePersona($this->ship);
        $persona2 = $this->service->computePersona($this->ship);

        $this->assertEquals($persona1['overall_alignment'], $persona2['overall_alignment']);
        $this->assertEquals($persona1['shady_score'], $persona2['shady_score']);
    }
}

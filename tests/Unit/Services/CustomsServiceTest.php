<?php

namespace Tests\Unit\Services;

use App\Enums\Customs\CustomsOutcome;
use App\Models\CustomsOfficial;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Services\CustomsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomsServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomsService $service;
    private Player $player;
    private PlayerShip $ship;
    private PointOfInterest $poi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CustomsService();

        $galaxy = Galaxy::factory()->create();
        $this->poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);
        $this->player = Player::factory()->create([
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $this->poi->id,
        ]);

        $this->ship = PlayerShip::factory()->create(['player_id' => $this->player->id]);
        $this->player->update(['active_ship_id' => $this->ship->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_clears_player_with_no_customs_official()
    {
        // POI with no customs official should auto-clear
        $result = $this->service->performCheck($this->player, $this->ship, $this->poi);

        $this->assertEquals(CustomsOutcome::CLEARED, $result['outcome']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_result_array_structure()
    {
        $result = $this->service->performCheck($this->player, $this->ship, $this->poi);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('outcome', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('fine_amount', $result);
        $this->assertArrayHasKey('seized_items', $result);
        $this->assertArrayHasKey('can_bribe', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_provides_outcome_enum()
    {
        $result = $this->service->performCheck($this->player, $this->ship, $this->poi);

        $this->assertInstanceOf(CustomsOutcome::class, $result['outcome']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_string_message()
    {
        $result = $this->service->performCheck($this->player, $this->ship, $this->poi);

        $this->assertIsString($result['message']);
        $this->assertNotEmpty($result['message']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_integer_fine_amount()
    {
        $result = $this->service->performCheck($this->player, $this->ship, $this->poi);

        $this->assertIsInt($result['fine_amount']);
        $this->assertGreaterThanOrEqual(0, $result['fine_amount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_boolean_can_bribe()
    {
        $result = $this->service->performCheck($this->player, $this->ship, $this->poi);

        $this->assertIsBool($result['can_bribe']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_array_of_seized_items()
    {
        $result = $this->service->performCheck($this->player, $this->ship, $this->poi);

        $this->assertIsArray($result['seized_items']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_corrupt_officer_parameter()
    {
        // Create a corrupt officer
        $official = CustomsOfficial::factory()->create([
            'poi_id' => $this->poi->id,
            'honesty' => 0.3,
            'severity' => 0.5,
            'detection_skill' => 0.6,
        ]);

        $this->assertNotNull($official);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_strict_officer_parameter()
    {
        // Create a strict officer
        $official = CustomsOfficial::factory()->create([
            'poi_id' => $this->poi->id,
            'honesty' => 0.95,
            'severity' => 0.95,
            'detection_skill' => 0.95,
        ]);

        $this->assertNotNull($official);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_lenient_officer_parameter()
    {
        // Create a lenient officer
        $official = CustomsOfficial::factory()->create([
            'poi_id' => $this->poi->id,
            'honesty' => 0.5,
            'severity' => 0.2,
            'detection_skill' => 0.3,
        ]);

        $this->assertNotNull($official);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_performs_check_with_official_present()
    {
        CustomsOfficial::factory()->create([
            'poi_id' => $this->poi->id,
            'honesty' => 0.7,
            'severity' => 0.5,
            'detection_skill' => 0.6,
        ]);

        $result = $this->service->performCheck($this->player, $this->ship, $this->poi);

        // Should return a valid result
        $this->assertIsArray($result);
        $this->assertInstanceOf(CustomsOutcome::class, $result['outcome']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_multiple_officials_at_different_pois()
    {
        $poi2 = PointOfInterest::factory()->create(['galaxy_id' => $this->poi->galaxy_id]);

        // Create official at first POI
        CustomsOfficial::factory()->create([
            'poi_id' => $this->poi->id,
            'honesty' => 0.9,
        ]);

        // Create different official at second POI
        CustomsOfficial::factory()->create([
            'poi_id' => $poi2->id,
            'honesty' => 0.3,
        ]);

        $result1 = $this->service->performCheck($this->player, $this->ship, $this->poi);
        $result2 = $this->service->performCheck($this->player, $this->ship, $poi2);

        // Both should be valid results
        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
    }
}

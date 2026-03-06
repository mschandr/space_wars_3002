<?php

namespace Tests\Unit\Services;

use App\Models\Commodity;
use App\Models\Contract;
use App\Models\TradingHub;
use App\Services\Contracts\ContractGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractGenerationServiceTest extends TestCase
{
    use RefreshDatabase;
    private ContractGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContractGenerationService::class);
    }

    /** @test */
    public function generateTransportContract_creates_contract_with_correct_type(): void
    {
        $origin = \App\Models\PointOfInterest::factory()->create();
        $destination = \App\Models\PointOfInterest::factory()->create();
        $commodity = Commodity::factory()->create(['base_value' => 100]);

        $contract = $this->service->generateTransportContract($origin, $destination, $commodity, 200);

        $this->assertEquals('TRANSPORT', $contract->type);
        $this->assertEquals('POSTED', $contract->status);
        $this->assertEquals($origin->id, $contract->origin_location_id);
        $this->assertEquals($destination->id, $contract->destination_location_id);
    }

    /** @test */
    public function generateTransportContract_calculates_reward_as_10_percent(): void
    {
        $origin = \App\Models\PointOfInterest::factory()->create(['x' => 0, 'y' => 0]);
        $destination = \App\Models\PointOfInterest::factory()->create(['x' => 100, 'y' => 0]);
        $commodity = Commodity::factory()->create(['base_value' => 100]);

        $contract = $this->service->generateTransportContract($origin, $destination, $commodity, 200);

        // Reward = 100 * 200 * 0.1 * distance_factor
        // distance_factor = max(1, 100/1000) = 1
        $expected_minimum = (int) ceil(100 * 200 * 0.1);
        $this->assertGreaterThanOrEqual($expected_minimum, $contract->reward_credits);
    }

    /** @test */
    public function generateTransportContract_sets_risk_based_on_distance(): void
    {
        $origin = \App\Models\PointOfInterest::factory()->create(['x' => 0, 'y' => 0]);
        $far_destination = \App\Models\PointOfInterest::factory()->create(['x' => 3000, 'y' => 0]);

        $commodity = Commodity::factory()->create();

        $contract = $this->service->generateTransportContract($origin, $far_destination, $commodity, 200);

        $this->assertEquals('HIGH', $contract->risk_rating);
    }

    /** @test */
    public function generateTransportContract_includes_cargo_manifest(): void
    {
        $origin = \App\Models\PointOfInterest::factory()->create();
        $destination = \App\Models\PointOfInterest::factory()->create();
        $commodity = Commodity::factory()->create();

        $contract = $this->service->generateTransportContract($origin, $destination, $commodity, 250);

        $this->assertIsArray($contract->cargo_manifest);
        $this->assertCount(1, $contract->cargo_manifest);
        $this->assertEquals($commodity->id, $contract->cargo_manifest[0]['commodity_id']);
        $this->assertEquals(250, $contract->cargo_manifest[0]['quantity']);
    }

    /** @test */
    public function generateSupplyContract_creates_contract_with_correct_type(): void
    {
        $destination = \App\Models\PointOfInterest::factory()->create();
        $commodity = Commodity::factory()->create();

        $contract = $this->service->generateSupplyContract($destination, $commodity, 300);

        $this->assertEquals('SUPPLY', $contract->type);
        $this->assertEquals('POSTED', $contract->status);
        $this->assertEquals($destination->id, $contract->destination_location_id);
    }

    /** @test */
    public function generateSupplyContract_calculates_reward_as_15_percent(): void
    {
        $destination = \App\Models\PointOfInterest::factory()->create();
        $commodity = Commodity::factory()->create(['base_value' => 100]);

        $contract = $this->service->generateSupplyContract($destination, $commodity, 200);

        // Reward = 100 * 200 * 0.15 = 3000
        $expected = (int) ceil(100 * 200 * 0.15);
        $this->assertEquals($expected, $contract->reward_credits);
    }

    /** @test */
    public function generateSupplyContract_requires_minimum_reputation(): void
    {
        $destination = \App\Models\PointOfInterest::factory()->create();
        $commodity = Commodity::factory()->create();

        $contract = $this->service->generateSupplyContract($destination, $commodity, 200);

        $this->assertGreaterThan(0, $contract->reputation_min);
    }

    /** @test */
    public function generateContractsForHub_generates_multiple_contracts(): void
    {
        $hub = TradingHub::factory()->create();

        $contracts = $this->service->generateContractsForHub($hub, 3);

        $this->assertGreaterThan(0, count($contracts));
        $this->assertLessThanOrEqual(3, count($contracts));
    }

    /** @test */
    public function generateContractsForHub_all_contracts_are_posted(): void
    {
        $hub = TradingHub::factory()->create();

        $contracts = $this->service->generateContractsForHub($hub, 2);

        foreach ($contracts as $contract) {
            $this->assertEquals('POSTED', $contract->status);
            $this->assertTrue($contract->isPosted());
        }
    }
}

<?php

namespace Tests\Unit\Services;

use App\Models\Contract;
use App\Models\Player;
use App\Models\PlayerContractReputation;
use App\Services\Contracts\ReputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReputationServiceTest extends TestCase
{
    use RefreshDatabase;
    private ReputationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReputationService::class);
    }

    /** @test */
    public function getPlayerReputation_returns_default_50_for_new_player(): void
    {
        $player = Player::factory()->create();

        $reputation = $this->service->getPlayerReputation($player);

        $this->assertEquals(50, $reputation);
    }

    /** @test */
    public function getPlayerReputation_creates_record_if_missing(): void
    {
        $player = Player::factory()->create();

        $this->assertNull(PlayerContractReputation::where('player_id', $player->id)->first());

        $reputation = $this->service->getPlayerReputation($player);

        $this->assertEquals(50, $reputation);
        $this->assertNotNull(PlayerContractReputation::where('player_id', $player->id)->first());
    }

    /** @test */
    public function recordSuccess_increments_completed_count(): void
    {
        $player = Player::factory()->create();
        $contract = Contract::factory()->create(['accepted_by_player_id' => $player->id]);

        $this->service->recordSuccess($player, $contract);

        $rep = PlayerContractReputation::where('player_id', $player->id)->first();
        $this->assertEquals(1, $rep->completed_count);
    }

    /** @test */
    public function recordSuccess_increases_reputation_max_100(): void
    {
        $player = Player::factory()->create();
        $contract = Contract::factory()->create();

        // Record 30 successes (should max out at 100)
        for ($i = 0; $i < 30; $i++) {
            $this->service->recordSuccess($player, $contract);
        }

        $rep = PlayerContractReputation::where('player_id', $player->id)->first();
        $this->assertEquals(100, $rep->reliability_score);
    }

    /** @test */
    public function recordFailure_increments_failed_count(): void
    {
        $player = Player::factory()->create();
        $contract = Contract::factory()->create();

        $this->service->recordFailure($player, $contract);

        $rep = PlayerContractReputation::where('player_id', $player->id)->first();
        $this->assertEquals(1, $rep->failed_count);
    }

    /** @test */
    public function recordFailure_decreases_reputation(): void
    {
        $player = Player::factory()->create();
        $contract = Contract::factory()->create();

        $initial = $this->service->getPlayerReputation($player);
        $this->service->recordFailure($player, $contract);

        $after = $this->service->getPlayerReputation($player);
        $this->assertLessThan($initial, $after);
    }

    /** @test */
    public function recordAbandonment_applies_steeper_penalty_than_failure(): void
    {
        $player1 = Player::factory()->create();
        $player2 = Player::factory()->create();
        $contract = Contract::factory()->create();

        $this->service->recordFailure($player1, $contract);
        $failure_penalty = 50 - $this->service->getPlayerReputation($player1);

        $this->service->recordAbandonment($player2, $contract);
        $abandonment_penalty = 50 - $this->service->getPlayerReputation($player2);

        $this->assertGreaterThan($failure_penalty, $abandonment_penalty);
    }

    /** @test */
    public function resetReputation_restores_defaults(): void
    {
        $player = Player::factory()->create();
        $contract = Contract::factory()->create();

        // Degrade reputation
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordFailure($player, $contract);
        }

        $this->service->resetReputation($player);

        $rep = PlayerContractReputation::where('player_id', $player->id)->first();
        $this->assertEquals(50, $rep->reliability_score);
        $this->assertEquals(0, $rep->completed_count);
        $this->assertEquals(0, $rep->failed_count);
    }
}

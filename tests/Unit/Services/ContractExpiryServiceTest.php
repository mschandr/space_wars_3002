<?php

namespace Tests\Unit\Services;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\Player;
use App\Services\Contracts\ContractExpiryService;
use App\Services\Contracts\ReputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ContractExpiryServiceTest extends TestCase
{
    use RefreshDatabase;
    private ContractExpiryService $service;
    private ReputationService $reputationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContractExpiryService::class);
        $this->reputationService = app(ReputationService::class);
    }

    /** @test */
    public function processExpirations_expires_posted_contracts_past_expiry(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'POSTED',
            'expires_at' => now()->subHour(),
        ]);

        $result = $this->service->processExpirations();

        $contract->refresh();
        $this->assertEquals('EXPIRED', $contract->status);
        $this->assertEquals(1, $result['expired']);
    }

    /** @test */
    public function processExpirations_does_not_expire_valid_posted_contracts(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'POSTED',
            'expires_at' => now()->addDays(1),
        ]);

        $result = $this->service->processExpirations();

        $contract->refresh();
        $this->assertEquals('POSTED', $contract->status);
        $this->assertEquals(0, $result['expired']);
    }

    /** @test */
    public function processExpirations_fails_accepted_contracts_past_deadline(): void
    {
        $player = Player::factory()->create();
        $contract = Contract::factory()->create([
            'status' => 'ACCEPTED',
            'accepted_by_player_id' => $player->id,
            'deadline_at' => now()->subHour(),
        ]);

        $result = $this->service->processExpirations();

        $contract->refresh();
        $this->assertEquals('FAILED', $contract->status);
        $this->assertEquals('Deadline exceeded', $contract->failure_reason);
        $this->assertEquals(1, $result['failed']);
    }

    /** @test */
    public function processExpirations_does_not_fail_valid_accepted_contracts(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'ACCEPTED',
            'deadline_at' => now()->addDays(1),
        ]);

        $result = $this->service->processExpirations();

        $contract->refresh();
        $this->assertEquals('ACCEPTED', $contract->status);
        $this->assertEquals(0, $result['failed']);
    }

    /** @test */
    public function processExpirations_applies_reputation_penalty_on_failure(): void
    {
        $player = Player::factory()->create();
        $contract = Contract::factory()->create([
            'status' => 'ACCEPTED',
            'accepted_by_player_id' => $player->id,
            'deadline_at' => now()->subHour(),
        ]);

        $initial_rep = $this->reputationService->getPlayerReputation($player);

        $this->service->processExpirations();

        $final_rep = $this->reputationService->getPlayerReputation($player);
        $this->assertLessThan($initial_rep, $final_rep);
    }

    /** @test */
    public function processExpirations_creates_expired_event(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'POSTED',
            'expires_at' => now()->subHour(),
        ]);

        $this->service->processExpirations();

        $event = ContractEvent::where('contract_id', $contract->id)
            ->where('event_type', 'EXPIRED')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('SYSTEM', $event->actor_type);
    }

    /** @test */
    public function processExpirations_creates_failed_event(): void
    {
        $player = Player::factory()->create();
        $contract = Contract::factory()->create([
            'status' => 'ACCEPTED',
            'accepted_by_player_id' => $player->id,
            'deadline_at' => now()->subHour(),
        ]);

        $this->service->processExpirations();

        $event = ContractEvent::where('contract_id', $contract->id)
            ->where('event_type', 'FAILED')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('SYSTEM', $event->actor_type);
        $this->assertEquals($player->id, $event->actor_id);
    }

    /** @test */
    public function processExpirations_handles_multiple_contracts(): void
    {
        // Create 3 expired POSTED contracts
        for ($i = 0; $i < 3; $i++) {
            Contract::factory()->create([
                'status' => 'POSTED',
                'expires_at' => now()->subHour(),
            ]);
        }

        // Create 2 overdue ACCEPTED contracts
        $player = Player::factory()->create();
        for ($i = 0; $i < 2; $i++) {
            Contract::factory()->create([
                'status' => 'ACCEPTED',
                'accepted_by_player_id' => $player->id,
                'deadline_at' => now()->subHour(),
            ]);
        }

        $result = $this->service->processExpirations();

        $this->assertEquals(3, $result['expired']);
        $this->assertEquals(2, $result['failed']);
    }

    /** @test */
    public function processExpirations_is_idempotent(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'POSTED',
            'expires_at' => now()->subHour(),
        ]);

        // Run twice
        $result1 = $this->service->processExpirations();
        $result2 = $this->service->processExpirations();

        // Second run should find no contracts to process
        $this->assertEquals(1, $result1['expired']);
        $this->assertEquals(0, $result2['expired']);
    }
}

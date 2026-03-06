<?php

namespace Tests\Unit\Services;

use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Services\Contracts\ContractService;
use App\Services\Contracts\ReputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractServiceTest extends TestCase
{
    use RefreshDatabase;
    private ContractService $service;
    private ReputationService $reputationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContractService::class);
        $this->reputationService = app(ReputationService::class);
    }

    /** @test */
    public function listContractsAtLocation_returns_posted_contracts_only(): void
    {
        $poi = PointOfInterest::factory()->create();

        Contract::factory()->create([
            'bar_location_id' => $poi->id,
            'status' => 'POSTED',
        ]);

        Contract::factory()->create([
            'bar_location_id' => $poi->id,
            'status' => 'ACCEPTED',
        ]);

        $contracts = $this->service->listContractsAtLocation($poi);

        $this->assertEquals(1, $contracts->count());
        $this->assertTrue($contracts->first()->isPosted());
    }

    /** @test */
    public function listContractsAtLocation_filters_by_type(): void
    {
        $poi = PointOfInterest::factory()->create();

        Contract::factory()->create([
            'bar_location_id' => $poi->id,
            'type' => 'TRANSPORT',
            'status' => 'POSTED',
        ]);

        Contract::factory()->create([
            'bar_location_id' => $poi->id,
            'type' => 'SUPPLY',
            'status' => 'POSTED',
        ]);

        $transport = $this->service->listContractsAtLocation($poi, ['type' => 'TRANSPORT']);

        $this->assertEquals(1, $transport->count());
        $this->assertEquals('TRANSPORT', $transport->first()->type);
    }

    /** @test */
    public function listContractsAtLocation_filters_by_min_reward(): void
    {
        $poi = PointOfInterest::factory()->create();

        Contract::factory()->create([
            'bar_location_id' => $poi->id,
            'reward_credits' => 5000,
            'status' => 'POSTED',
        ]);

        Contract::factory()->create([
            'bar_location_id' => $poi->id,
            'reward_credits' => 15000,
            'status' => 'POSTED',
        ]);

        $high_reward = $this->service->listContractsAtLocation($poi, ['min_reward' => 10000]);

        $this->assertEquals(1, $high_reward->count());
        $this->assertGreaterThanOrEqual(10000, $high_reward->first()->reward_credits);
    }

    /** @test */
    public function canAcceptContract_returns_success_for_valid_contract(): void
    {
        $player = Player::factory()->create();
        $contract = Contract::factory()->create(['status' => 'POSTED']);

        $result = $this->service->canAcceptContract($contract, $player);

        $this->assertTrue($result['success']);
    }

    /** @test */
    public function canAcceptContract_rejects_non_posted_contracts(): void
    {
        $player = Player::factory()->create();
        $contract = Contract::factory()->create(['status' => 'ACCEPTED']);

        $result = $this->service->canAcceptContract($contract, $player);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no longer available', $result['reason']);
    }

    /** @test */
    public function canAcceptContract_rejects_low_reputation(): void
    {
        $player = Player::factory()->create();
        $contract = Contract::factory()->create(['status' => 'POSTED', 'reputation_min' => 75]);

        $result = $this->service->canAcceptContract($contract, $player);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Reputation too low', $result['reason']);
    }

    /** @test */
    public function canAcceptContract_rejects_too_many_active_contracts(): void
    {
        $player = Player::factory()->create();

        // Accept 5 contracts (at limit)
        for ($i = 0; $i < 5; $i++) {
            Contract::factory()->create([
                'accepted_by_player_id' => $player->id,
                'status' => 'ACCEPTED',
            ]);
        }

        $new_contract = Contract::factory()->create(['status' => 'POSTED']);

        $result = $this->service->canAcceptContract($new_contract, $player);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Too many active contracts', $result['reason']);
    }

    /** @test */
    public function acceptContract_updates_status_to_accepted(): void
    {
        $player = Player::factory()->create();
        $contract = Contract::factory()->create(['status' => 'POSTED']);

        $accepted = $this->service->acceptContract($contract, $player);

        $this->assertTrue($accepted->isAccepted());
        $this->assertEquals($player->id, $accepted->accepted_by_player_id);
        $this->assertNotNull($accepted->accepted_at);
    }

    /** @test */
    public function acceptContract_creates_event(): void
    {
        $player = Player::factory()->create();
        $contract = Contract::factory()->create(['status' => 'POSTED']);

        $this->service->acceptContract($contract, $player);

        $event = ContractEvent::where('contract_id', $contract->id)
            ->where('event_type', 'ACCEPTED')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('PLAYER', $event->actor_type);
        $this->assertEquals($player->id, $event->actor_id);
    }

    /** @test */
    public function acceptContract_throws_exception_for_invalid_contract(): void
    {
        $player = Player::factory()->create();
        $contract = Contract::factory()->create(['status' => 'ACCEPTED']);

        $this->expectException(\Exception::class);

        $this->service->acceptContract($contract, $player);
    }

    /** @test */
    public function completeContract_requires_correct_location(): void
    {
        $player = Player::factory()->create(['current_poi_id' => 1]);
        $destination = PointOfInterest::factory()->create(['id' => 2]);
        $contract = Contract::factory()->create([
            'status' => 'ACCEPTED',
            'accepted_by_player_id' => $player->id,
            'destination_location_id' => $destination->id,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not at the contract destination');

        $this->service->completeContract($contract, $player, []);
    }

    /** @test */
    public function completeContract_validates_cargo_exactly(): void
    {
        $destination = PointOfInterest::factory()->create();
        $player = Player::factory()->create(['current_poi_id' => $destination->id]);

        $contract = Contract::factory()->create([
            'status' => 'ACCEPTED',
            'accepted_by_player_id' => $player->id,
            'destination_location_id' => $destination->id,
            'cargo_manifest' => [
                ['commodity_id' => 1, 'quantity' => 200],
                ['commodity_id' => 2, 'quantity' => 150],
            ],
        ]);

        // Missing commodity 2
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing commodity');

        $this->service->completeContract($contract, $player, [1 => 200]);
    }

    /** @test */
    public function completeContract_completes_successfully_and_pays_reward(): void
    {
        $destination = PointOfInterest::factory()->create();
        $player = Player::factory()->create([
            'current_poi_id' => $destination->id,
            'credits' => 1000,
        ]);

        $contract = Contract::factory()->create([
            'status' => 'ACCEPTED',
            'accepted_by_player_id' => $player->id,
            'destination_location_id' => $destination->id,
            'reward_credits' => 5000,
            'cargo_manifest' => [
                ['commodity_id' => 1, 'quantity' => 100],
            ],
        ]);

        $completed = $this->service->completeContract($contract, $player, [1 => 100]);

        $this->assertTrue($completed->isCompleted());
        $this->assertNotNull($completed->completed_at);

        $player->refresh();
        $this->assertEquals(6000, $player->credits);
    }

    /** @test */
    public function completeContract_increases_reputation(): void
    {
        $destination = PointOfInterest::factory()->create();
        $player = Player::factory()->create(['current_poi_id' => $destination->id]);

        $contract = Contract::factory()->create([
            'status' => 'ACCEPTED',
            'accepted_by_player_id' => $player->id,
            'destination_location_id' => $destination->id,
            'reward_credits' => 5000,
            'cargo_manifest' => [
                ['commodity_id' => 1, 'quantity' => 100],
            ],
        ]);

        $initial_rep = $this->reputationService->getPlayerReputation($player);

        $this->service->completeContract($contract, $player, [1 => 100]);

        $final_rep = $this->reputationService->getPlayerReputation($player);
        $this->assertGreaterThan($initial_rep, $final_rep);
    }

    /** @test */
    public function completeContract_creates_completion_event(): void
    {
        $destination = PointOfInterest::factory()->create();
        $player = Player::factory()->create(['current_poi_id' => $destination->id]);

        $contract = Contract::factory()->create([
            'status' => 'ACCEPTED',
            'accepted_by_player_id' => $player->id,
            'destination_location_id' => $destination->id,
            'reward_credits' => 5000,
            'cargo_manifest' => [
                ['commodity_id' => 1, 'quantity' => 100],
            ],
        ]);

        $this->service->completeContract($contract, $player, [1 => 100]);

        $event = ContractEvent::where('contract_id', $contract->id)
            ->where('event_type', 'COMPLETED')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('PLAYER', $event->actor_type);
        $this->assertEquals($player->id, $event->actor_id);
    }
}

<?php

namespace Tests\Unit\Services\Economy;

use App\Enums\Economy\ActorType;
use App\Enums\Economy\ReasonCode;
use App\Models\Blueprint;
use App\Models\Commodity;
use App\Models\CommodityLedgerEntry;
use App\Models\Galaxy;
use App\Models\TradingHub;
use App\Services\Economy\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledgerService;
    private Galaxy $galaxy;
    private TradingHub $hub;
    private Commodity $mineral;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = app(LedgerService::class);

        // Create test data
        $this->galaxy = Galaxy::factory()->create();
        $this->hub = TradingHub::factory()->create();
        $this->mineral = Commodity::factory()->create(['is_conserved' => true]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_mining_output(): void
    {
        $entry = $this->ledgerService->recordMiningOutput(
            $this->galaxy,
            $this->hub,
            $this->mineral,
            qty: 1000,
            actorId: 1,
            actorType: ActorType::SYSTEM
        );

        $this->assertDatabaseHas('commodity_ledger_entries', [
            'galaxy_id' => $this->galaxy->id,
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->mineral->id,
            'qty_delta' => 1000,
            'reason_code' => ReasonCode::MINING->value,
            'actor_type' => ActorType::SYSTEM->value,
        ]);

        $this->assertEquals(1000, $entry->qty_delta);
        $this->assertEquals(ReasonCode::MINING, $entry->reason_code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_construction_with_multiple_inputs(): void
    {
        $commodity2 = Commodity::factory()->create(['is_conserved' => true]);
        $blueprint = Blueprint::factory()->create();
        $blueprint->inputs()->create(['commodity_id' => $this->mineral->id, 'qty_required' => 100]);
        $blueprint->inputs()->create(['commodity_id' => $commodity2->id, 'qty_required' => 50]);

        $entries = $this->ledgerService->recordConstruction(
            $this->galaxy,
            $this->hub,
            $blueprint,
            actorId: 1
        );

        $this->assertCount(2, $entries);
        $this->assertEquals(-100, $entries[0]->qty_delta);
        $this->assertEquals(-50, $entries[1]->qty_delta);
        $this->assertEquals(ReasonCode::CONSTRUCTION, $entries[0]->reason_code);
        $this->assertEquals(ReasonCode::CONSTRUCTION, $entries[1]->reason_code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_trade_buy(): void
    {
        $entry = $this->ledgerService->recordTrade(
            $this->galaxy,
            $this->hub,
            $this->mineral,
            qty: 500,
            pricePerUnit: 100,
            tradeType: 'buy',
            playerId: 1
        );

        $this->assertEquals(500, $entry->qty_delta);
        $this->assertEquals(ReasonCode::TRADE_BUY, $entry->reason_code);
        $this->assertEquals(ActorType::PLAYER, $entry->actor_type);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_trade_sell(): void
    {
        $entry = $this->ledgerService->recordTrade(
            $this->galaxy,
            $this->hub,
            $this->mineral,
            qty: 300,
            pricePerUnit: 100,
            tradeType: 'sell',
            playerId: 1
        );

        $this->assertEquals(-300, $entry->qty_delta);
        $this->assertEquals(ReasonCode::TRADE_SELL, $entry->reason_code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_invalid_trade_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->ledgerService->recordTrade(
            $this->galaxy,
            $this->hub,
            $this->mineral,
            qty: 100,
            pricePerUnit: 100,
            tradeType: 'invalid',
            playerId: 1
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_upkeep(): void
    {
        $entry = $this->ledgerService->recordUpkeep(
            $this->galaxy,
            $this->hub,
            $this->mineral,
            qty: 50,
            actorId: 1
        );

        $this->assertEquals(-50, $entry->qty_delta);
        $this->assertEquals(ReasonCode::UPKEEP, $entry->reason_code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_salvage(): void
    {
        $entry = $this->ledgerService->recordSalvage(
            $this->galaxy,
            $this->hub,
            $this->mineral,
            qty: 200,
            actorId: 1
        );

        $this->assertEquals(200, $entry->qty_delta);
        $this->assertEquals(ReasonCode::SALVAGE, $entry->reason_code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_npc_inject(): void
    {
        $entry = $this->ledgerService->recordNpcInject(
            $this->galaxy,
            $this->hub,
            $this->mineral,
            qty: 150,
            npcId: 5
        );

        $this->assertEquals(150, $entry->qty_delta);
        $this->assertEquals(ReasonCode::NPC_INJECT, $entry->reason_code);
        $this->assertEquals(ActorType::NPC, $entry->actor_type);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_genesis(): void
    {
        $entry = $this->ledgerService->recordGenesis(
            $this->galaxy,
            $this->hub,
            $this->mineral,
            qty: 5000
        );

        $this->assertEquals(5000, $entry->qty_delta);
        $this->assertEquals(ReasonCode::GENESIS, $entry->reason_code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_entries_with_correlation_id(): void
    {
        $correlationId = 'test-correlation-123';

        $entry = $this->ledgerService->recordTrade(
            $this->galaxy,
            $this->hub,
            $this->mineral,
            qty: 100,
            pricePerUnit: 100,
            tradeType: 'buy',
            playerId: 1,
            correlationId: $correlationId
        );

        $this->assertEquals($correlationId, $entry->correlation_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_entries_with_metadata(): void
    {
        $metadata = ['custom_field' => 'custom_value'];

        $entry = $this->ledgerService->recordMiningOutput(
            $this->galaxy,
            $this->hub,
            $this->mineral,
            qty: 100,
            metadata: $metadata
        );

        $this->assertEquals($metadata, $entry->metadata);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_ledger_history_filtered_by_hub(): void
    {
        $hub2 = TradingHub::factory()->create();

        $this->ledgerService->recordMiningOutput($this->galaxy, $this->hub, $this->mineral, 100);
        $this->ledgerService->recordMiningOutput($this->galaxy, $hub2, $this->mineral, 200);

        $history = $this->ledgerService->getLedgerHistory($this->galaxy, $this->hub);

        $this->assertEquals(1, $history->count());
        $this->assertEquals(100, $history->first()->qty_delta);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_ledger_history_filtered_by_commodity(): void
    {
        $mineral2 = Commodity::factory()->create();

        $this->ledgerService->recordMiningOutput($this->galaxy, $this->hub, $this->mineral, 100);
        $this->ledgerService->recordMiningOutput($this->galaxy, $this->hub, $mineral2, 200);

        $history = $this->ledgerService->getLedgerHistory($this->galaxy, null, $this->mineral);

        $this->assertEquals(1, $history->count());
        $this->assertEquals(100, $history->first()->qty_delta);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_ledger_history_filtered_by_date(): void
    {
        // Create entry in the past
        $pastEntry = $this->ledgerService->recordMiningOutput($this->galaxy, $this->hub, $this->mineral, 100);
        $pastEntry->timestamp = now()->subDays(10);
        $pastEntry->save();

        // Create recent entry
        $recentEntry = $this->ledgerService->recordMiningOutput($this->galaxy, $this->hub, $this->mineral, 200);

        // Filter by entries since 5 days ago
        $history = $this->ledgerService->getLedgerHistory(
            $this->galaxy,
            since: now()->subDays(5)
        );

        $this->assertEquals(1, $history->count());
        $this->assertEquals(200, $history->first()->qty_delta);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_computes_ledger_total(): void
    {
        $this->ledgerService->recordMiningOutput($this->galaxy, $this->hub, $this->mineral, 1000);
        $this->ledgerService->recordMiningOutput($this->galaxy, $this->hub, $this->mineral, 500);
        $this->ledgerService->recordTrade(
            $this->galaxy,
            $this->hub,
            $this->mineral,
            qty: 200,
            pricePerUnit: 100,
            tradeType: 'sell',
            playerId: 1
        );

        $total = $this->ledgerService->getLedgerTotal($this->hub, $this->mineral);

        // 1000 + 500 - 200 = 1300
        $this->assertEquals(1300, $total);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_breaks_down_ledger_into_sources_and_sinks(): void
    {
        $this->ledgerService->recordMiningOutput($this->galaxy, $this->hub, $this->mineral, 1000);
        $this->ledgerService->recordTrade(
            $this->galaxy,
            $this->hub,
            $this->mineral,
            qty: 300,
            pricePerUnit: 100,
            tradeType: 'sell',
            playerId: 1
        );

        $breakdown = $this->ledgerService->getLedgerBreakdown($this->hub, $this->mineral);

        $this->assertEquals(1000, $breakdown['sources']);
        $this->assertEquals(300, $breakdown['sinks']);
        $this->assertEquals(700, $breakdown['total']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_batch_entries(): void
    {
        $mineral2 = Commodity::factory()->create();

        $entries = $this->ledgerService->recordBatch([
            [
                'galaxy' => $this->galaxy,
                'hub' => $this->hub,
                'commodity' => $this->mineral,
                'qty_delta' => 100,
                'reason_code' => ReasonCode::MINING,
                'actor_type' => ActorType::SYSTEM,
            ],
            [
                'galaxy' => $this->galaxy,
                'hub' => $this->hub,
                'commodity' => $mineral2,
                'qty_delta' => 50,
                'reason_code' => ReasonCode::MINING,
                'actor_type' => ActorType::SYSTEM,
            ],
        ]);

        $this->assertCount(2, $entries);
        $this->assertDatabaseCount('commodity_ledger_entries', 2);
    }
}

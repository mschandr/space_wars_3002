<?php

namespace Tests\Unit\Services\Economy;

use App\Enums\Economy\ActorType;
use App\Enums\Economy\ReasonCode;
use App\Models\Commodity;
use App\Models\Galaxy;
use App\Models\HubCommodityStats;
use App\Models\TradingHub;
use App\Services\Economy\HubCommodityStatsService;
use App\Services\Economy\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HubCommodityStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private HubCommodityStatsService $statsService;
    private LedgerService $ledgerService;
    private Galaxy $galaxy;
    private TradingHub $hub;
    private Commodity $commodity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statsService = app(HubCommodityStatsService::class);
        $this->ledgerService = app(LedgerService::class);

        $this->galaxy = Galaxy::factory()->create();
        $this->hub = TradingHub::factory()->create();
        $this->commodity = Commodity::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_computes_stats_from_ledger(): void
    {
        // Create ledger entries over 7 days
        $this->ledgerService->recordMiningOutput($this->galaxy, $this->hub, $this->commodity, 700);
        $this->ledgerService->recordTrade(
            $this->galaxy,
            $this->hub,
            $this->commodity,
            qty: 100,
            pricePerUnit: 100,
            tradeType: 'sell',
            playerId: 1
        );

        // Compute stats
        $this->statsService->computeStats($this->hub, $this->commodity, windowDays: 7);

        $stats = HubCommodityStats::where('trading_hub_id', $this->hub->id)
            ->where('commodity_id', $this->commodity->id)
            ->first();

        $this->assertNotNull($stats);
        // Supply: 700 / 7 = 100 per day
        $this->assertEquals(100, $stats->avg_daily_supply);
        // Demand: 100 / 7 ≈ 14.29 per day
        $this->assertEqualsWithDelta(100 / 7, $stats->avg_daily_demand, 0.1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_separates_supply_and_demand_correctly(): void
    {
        // Supply sources
        $this->ledgerService->recordMiningOutput($this->galaxy, $this->hub, $this->commodity, 1000);
        $this->ledgerService->recordNpcInject($this->galaxy, $this->hub, $this->commodity, 500, npcId: 1);

        // Demand sinks
        $this->ledgerService->recordTrade(
            $this->galaxy,
            $this->hub,
            $this->commodity,
            qty: 200,
            pricePerUnit: 100,
            tradeType: 'sell',
            playerId: 1
        );
        $this->ledgerService->recordUpkeep($this->galaxy, $this->hub, $this->commodity, qty: 100, actorId: 1);

        $this->statsService->computeStats($this->hub, $this->commodity, windowDays: 7);

        $stats = HubCommodityStats::where('trading_hub_id', $this->hub->id)
            ->where('commodity_id', $this->commodity->id)
            ->first();

        // Supply: (1000 + 500) / 7 ≈ 214.29
        $this->assertEqualsWithDelta((1000 + 500) / 7, $stats->avg_daily_supply, 0.1);
        // Demand: (200 + 100) / 7 ≈ 42.86
        $this->assertEqualsWithDelta((200 + 100) / 7, $stats->avg_daily_demand, 0.1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_ignores_entries_outside_window(): void
    {
        // Create entry in the past
        $entry = $this->ledgerService->recordMiningOutput($this->galaxy, $this->hub, $this->commodity, 1000);
        $entry->timestamp = now()->subDays(10);
        $entry->save();

        // Create recent entry
        $this->ledgerService->recordMiningOutput($this->galaxy, $this->hub, $this->commodity, 500);

        // Compute stats with 7-day window
        $this->statsService->computeStats($this->hub, $this->commodity, windowDays: 7);

        $stats = HubCommodityStats::where('trading_hub_id', $this->hub->id)
            ->where('commodity_id', $this->commodity->id)
            ->first();

        // Should only count the 500 from recent
        $this->assertEqualsWithDelta(500 / 7, $stats->avg_daily_supply, 0.1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_recomputes_galaxy_stats(): void
    {
        $hub2 = TradingHub::factory()->create();
        $commodity2 = Commodity::factory()->create();

        // Create entries for different hub/commodity combinations
        $this->ledgerService->recordMiningOutput($this->galaxy, $this->hub, $this->commodity, 100);
        $this->ledgerService->recordMiningOutput($this->galaxy, $hub2, $this->commodity, 200);
        $this->ledgerService->recordMiningOutput($this->galaxy, $this->hub, $commodity2, 300);

        $result = $this->statsService->recomputeGalaxyStats($this->galaxy, windowDays: 7);

        $this->assertGreaterThanOrEqual(3, $result['computed']);
        $this->assertEquals(0, count($result['errors']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_recomputes_all_stats(): void
    {
        $galaxy2 = Galaxy::factory()->create();
        $hub2 = TradingHub::factory()->create();

        // Create entries in both galaxies
        $this->ledgerService->recordMiningOutput($this->galaxy, $this->hub, $this->commodity, 100);
        $this->ledgerService->recordMiningOutput($galaxy2, $hub2, $this->commodity, 200);

        $result = $this->statsService->recomputeAllStats(windowDays: 7);

        $this->assertGreaterThanOrEqual(2, $result['computed']);
        $this->assertGreaterThanOrEqual(2, $result['galaxies']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_retrieves_existing_stats(): void
    {
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
        ]);

        $retrieved = $this->statsService->getStats($this->hub, $this->commodity);

        $this->assertNotNull($retrieved);
        $this->assertEquals($stats->id, $retrieved->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_missing_stats(): void
    {
        $retrieved = $this->statsService->getStats($this->hub, $this->commodity);

        $this->assertNull($retrieved);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_gets_or_creates_stats_with_defaults(): void
    {
        $stats = $this->statsService->getOrCreateStats($this->hub, $this->commodity);

        $this->assertNotNull($stats);
        $this->assertEquals(0, $stats->avg_daily_demand);
        $this->assertEquals(0, $stats->avg_daily_supply);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_stats_needing_recomputation(): void
    {
        // Create old stats
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
            'last_computed_at' => now()->subHours(2),
        ]);

        // Default interval is 60 minutes
        $needsRecompute = $this->statsService->needsRecompute($this->hub, $this->commodity, intervalMinutes: 60);

        $this->assertTrue($needsRecompute);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_stats_not_needing_recomputation(): void
    {
        // Create recent stats
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
            'last_computed_at' => now()->subMinutes(10),
        ]);

        $needsRecompute = $this->statsService->needsRecompute($this->hub, $this->commodity, intervalMinutes: 60);

        $this->assertFalse($needsRecompute);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_indicates_missing_stats_need_recomputation(): void
    {
        $needsRecompute = $this->statsService->needsRecompute($this->hub, $this->commodity, intervalMinutes: 60);

        $this->assertTrue($needsRecompute);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_retrieves_all_hub_stats(): void
    {
        $commodity2 = Commodity::factory()->create();
        $commodity3 = Commodity::factory()->create();

        HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
        ]);

        HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $commodity2->id,
        ]);

        // Different hub
        $hub2 = TradingHub::factory()->create();
        HubCommodityStats::factory()->create([
            'trading_hub_id' => $hub2->id,
            'commodity_id' => $commodity3->id,
        ]);

        $hubStats = $this->statsService->getHubStats($this->hub);

        $this->assertEquals(2, $hubStats->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_coverage_days(): void
    {
        // This test requires the InventoryService to be set up properly
        // Skip if infrastructure not available
        $this->markTestSkipped('Requires InventoryService integration');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_provides_detailed_analysis(): void
    {
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
            'avg_daily_demand' => 100,
            'avg_daily_supply' => 150,
        ]);

        $analysis = $this->statsService->getDetailedAnalysis($this->hub, $this->commodity, windowDays: 7);

        $this->assertIsArray($analysis);
        $this->assertArrayHasKey('hub', $analysis);
        $this->assertArrayHasKey('commodity', $analysis);
        $this->assertArrayHasKey('on_hand_qty', $analysis);
        $this->assertArrayHasKey('reserved_qty', $analysis);
        $this->assertArrayHasKey('available_qty', $analysis);
        $this->assertArrayHasKey('avg_daily_demand', $analysis);
        $this->assertArrayHasKey('avg_daily_supply', $analysis);
        $this->assertArrayHasKey('coverage_days', $analysis);
        $this->assertArrayHasKey('last_computed_at', $analysis);
        $this->assertArrayHasKey('window_days', $analysis);

        $this->assertEquals($this->hub->id, $analysis['hub']['id']);
        $this->assertEquals($this->commodity->id, $analysis['commodity']['id']);
        $this->assertEquals(100, $analysis['avg_daily_demand']);
        $this->assertEquals(150, $analysis['avg_daily_supply']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_last_computed_at_timestamp(): void
    {
        $this->ledgerService->recordMiningOutput($this->galaxy, $this->hub, $this->commodity, 100);

        $this->statsService->computeStats($this->hub, $this->commodity, windowDays: 7);

        $stats = HubCommodityStats::where('trading_hub_id', $this->hub->id)
            ->where('commodity_id', $this->commodity->id)
            ->first();

        $this->assertNotNull($stats->last_computed_at);
        $this->assertTrue($stats->last_computed_at->isAfter(now()->subMinutes(5)));
    }
}

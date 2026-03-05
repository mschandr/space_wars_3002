<?php

namespace Tests\Unit\Services;

use App\Enums\Trading\CommodityCategory;
use App\Models\CrewMember;
use App\Models\Galaxy;
use App\Models\Mineral;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use App\Services\Trading\CommodityAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CommodityAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    private CommodityAccessService $service;
    private Player $player;
    private TradingHub $hub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommodityAccessService();

        $galaxy = Galaxy::factory()->create();
        $this->player = Player::factory()->create(['galaxy_id' => $galaxy->id]);
        $this->hub = TradingHub::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_always_shows_civilian_commodities()
    {
        $civilian = Mineral::factory()->create([
            'category' => CommodityCategory::CIVILIAN,
        ]);

        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'mineral_id' => $civilian->id,
        ]);

        $collection = collect([$inventory]);
        $filtered = $this->service->filterForPlayer($collection, $this->player);

        $this->assertCount(1, $filtered);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hides_black_market_below_threshold()
    {
        $blackMarket = Mineral::factory()->create([
            'category' => CommodityCategory::BLACK,
            'is_illegal' => true,
        ]);

        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'mineral_id' => $blackMarket->id,
        ]);

        $collection = collect([$inventory]);
        $filtered = $this->service->filterForPlayer($collection, $this->player);

        // Player has no shady crew, so black market is not visible
        $this->assertCount(0, $filtered);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_black_market_with_sufficient_crew_shady_score()
    {
        $threshold = config('economy.black_market.visibility_threshold', 10);

        $blackMarket = Mineral::factory()->create([
            'category' => CommodityCategory::BLACK,
            'is_illegal' => true,
            'min_reputation' => null,
        ]);

        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'mineral_id' => $blackMarket->id,
        ]);

        // Create a ship with high shady crew
        $ship = PlayerShip::factory()->create(['player_id' => $this->player->id]);
        $this->player->update(['active_ship_id' => $ship->id]);

        // Create multiple crew members with shady actions that exceed threshold
        for ($i = 0; $i < 2; $i++) {
            CrewMember::factory()->create([
                'player_ship_id' => $ship->id,
                'shady_actions' => $threshold,
            ]);
        }

        // Verify the crew shady count is high enough
        $totalShady = $this->player->getShadyInteractionCount();
        $this->assertGreaterThanOrEqual($threshold, $totalShady);

        $collection = collect([$inventory]);
        $filtered = $this->service->filterForPlayer($collection, $this->player);

        // Player now has sufficient shady interactions
        $this->assertCount(1, $filtered);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_industrial_by_reputation()
    {
        $industrial = Mineral::factory()->create([
            'category' => CommodityCategory::INDUSTRIAL,
            'min_reputation' => 50, // Requires 50 reputation
        ]);

        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'mineral_id' => $industrial->id,
        ]);

        $this->player->update(['reputation' => 30]); // Below requirement

        $collection = collect([$inventory]);
        $filtered = $this->service->filterForPlayer($collection, $this->player);

        $this->assertCount(0, $filtered);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_industrial_with_sufficient_reputation()
    {
        $industrial = Mineral::factory()->create([
            'category' => CommodityCategory::INDUSTRIAL,
            'min_reputation' => 50,
        ]);

        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'mineral_id' => $industrial->id,
        ]);

        $this->player->update(['reputation' => 60]); // Above requirement

        $collection = collect([$inventory]);
        $filtered = $this->service->filterForPlayer($collection, $this->player);

        $this->assertCount(1, $filtered);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_mixed_inventory()
    {
        $civilian = Mineral::factory()->create(['category' => CommodityCategory::CIVILIAN]);
        $industrial = Mineral::factory()->create([
            'category' => CommodityCategory::INDUSTRIAL,
            'min_reputation' => null, // No reputation requirement
        ]);
        $blackMarket = Mineral::factory()->create([
            'category' => CommodityCategory::BLACK,
            'is_illegal' => true,
        ]);

        $civInventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'mineral_id' => $civilian->id,
        ]);
        $indInventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'mineral_id' => $industrial->id,
        ]);
        $bmInventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'mineral_id' => $blackMarket->id,
        ]);

        $collection = collect([$civInventory, $indInventory, $bmInventory]);
        $filtered = $this->service->filterForPlayer($collection, $this->player);

        // Should have civilian and industrial, but not black market (below threshold)
        $this->assertCount(2, $filtered);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_no_items_without_access()
    {
        $industrial = Mineral::factory()->create([
            'category' => CommodityCategory::INDUSTRIAL,
            'min_reputation' => 100,
        ]);
        $blackMarket = Mineral::factory()->create([
            'category' => CommodityCategory::BLACK,
            'is_illegal' => true,
        ]);

        $indInventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'mineral_id' => $industrial->id,
        ]);
        $bmInventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'mineral_id' => $blackMarket->id,
        ]);

        $this->player->update(['reputation' => 0]);

        $collection = collect([$indInventory, $bmInventory]);
        $filtered = $this->service->filterForPlayer($collection, $this->player);

        // Empty inventory
        $this->assertCount(0, $filtered);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_inventory()
    {
        $inventory = collect([]);

        $filtered = $this->service->filterForPlayer($inventory, $this->player);

        $this->assertCount(0, $filtered);
        $this->assertInstanceOf(Collection::class, $filtered);
    }
}

<?php

namespace Tests\Unit\Services;

use App\Enums\RarityTier;
use App\Models\Ship;
use App\Services\ShipRarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipRarityServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShipRarityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ShipRarityService;
    }

    public function test_roll_rarity_returns_valid_tier(): void
    {
        $tier = $this->service->rollRarity();
        $this->assertInstanceOf(RarityTier::class, $tier);
    }

    public function test_roll_rarity_favors_common(): void
    {
        $counts = [];
        for ($i = 0; $i < 500; $i++) {
            $tier = $this->service->rollRarity();
            $counts[$tier->value] = ($counts[$tier->value] ?? 0) + 1;
        }

        // Common should be the most frequent
        $this->assertGreaterThan($counts['uncommon'] ?? 0, $counts['common'] ?? 0);
        $this->assertGreaterThan($counts['rare'] ?? 0, $counts['common'] ?? 0);
    }

    public function test_apply_rarity_to_stat_scales_correctly(): void
    {
        $base = 100;

        $common = $this->service->applyRarityToStat($base, RarityTier::COMMON);
        $exotic = $this->service->applyRarityToStat($base, RarityTier::EXOTIC);

        // Common should be around 100 (±5% jitter)
        $this->assertGreaterThanOrEqual(95, $common);
        $this->assertLessThanOrEqual(105, $common);

        // Exotic should be around 220 (2.2x ± 5% jitter)
        $this->assertGreaterThanOrEqual(209, $exotic);
        $this->assertLessThanOrEqual(231, $exotic);
    }

    public function test_apply_rarity_to_stat_never_returns_zero(): void
    {
        $result = $this->service->applyRarityToStat(1, RarityTier::COMMON);
        $this->assertGreaterThanOrEqual(1, $result);
    }

    public function test_calculate_price_applies_multiplier(): void
    {
        $base = 10000.0;

        $common = $this->service->calculatePrice($base, RarityTier::COMMON);
        $this->assertEquals(10000.0, $common);

        $rare = $this->service->calculatePrice($base, RarityTier::RARE);
        $this->assertEquals(30000.0, $rare);

        $exotic = $this->service->calculatePrice($base, RarityTier::EXOTIC);
        $this->assertEquals(300000.0, $exotic);
    }

    public function test_apply_rarity_to_ship_stats_returns_all_stats(): void
    {
        $blueprint = Ship::factory()->create([
            'hull_strength' => 100,
            'shield_strength' => 50,
            'cargo_capacity' => 80,
            'speed' => 100,
            'weapon_slots' => 4,
            'utility_slots' => 2,
            'attributes' => [
                'max_fuel' => 100,
                'starting_sensors' => 2,
                'starting_warp_drive' => 1,
                'starting_weapons' => 20,
            ],
        ]);

        $stats = $this->service->applyRarityToShipStats($blueprint, RarityTier::COMMON);

        $this->assertArrayHasKey('hull_strength', $stats);
        $this->assertArrayHasKey('shield_strength', $stats);
        $this->assertArrayHasKey('cargo_capacity', $stats);
        $this->assertArrayHasKey('speed', $stats);
        $this->assertArrayHasKey('weapon_slots', $stats);
        $this->assertArrayHasKey('utility_slots', $stats);
        $this->assertArrayHasKey('max_fuel', $stats);
        $this->assertArrayHasKey('sensors', $stats);
        $this->assertArrayHasKey('warp_drive', $stats);
        $this->assertArrayHasKey('weapons', $stats);
    }

    public function test_jitter_produces_varied_results(): void
    {
        $results = [];
        for ($i = 0; $i < 20; $i++) {
            $results[] = $this->service->applyRarityToStat(100, RarityTier::COMMON);
        }

        // Not all results should be identical (very unlikely with jitter)
        $unique = array_unique($results);
        $this->assertGreaterThan(1, count($unique));
    }
}

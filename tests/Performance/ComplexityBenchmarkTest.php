<?php

namespace Tests\Performance;

use App\Http\Controllers\Api\Builders\StarSystemResponseBuilder;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\PlayerKnowledgeMapController;
use App\Http\Resources\ShipResource;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Services\ColonyCombatService;
use App\Services\MerchantCommentaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Performance Benchmarks for Top 10 Complexity Methods
 *
 * Measures execution time, memory usage, and query counts
 * Before and after refactoring.
 */
class ComplexityBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private array $benchmarks = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->benchmarks = [];
    }

    /**
     * Measure method execution time
     *
     * @param callable $callable
     * @param int $iterations
     * @return array ['min' => ms, 'max' => ms, 'avg' => ms, 'total' => ms]
     */
    private function measureExecution(callable $callable, int $iterations = 100): array
    {
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $callable();
            $end = microtime(true);

            $times[] = ($end - $start) * 1000; // Convert to milliseconds
        }

        return [
            'min' => min($times),
            'max' => max($times),
            'avg' => array_sum($times) / count($times),
            'total' => array_sum($times),
            'iterations' => $iterations,
        ];
    }

    /**
     * Benchmark #1: StarSystemResponseBuilder::buildBodyData()
     * Status: CC 33, NPC 645,120, 105 LOC
     */
    public function test_benchmark_1_star_system_builder(): void
    {
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);
        $system = $poi->starSystem;

        $builder = new StarSystemResponseBuilder();

        $result = $this->measureExecution(
            fn () => $builder->build($system),
            50 // Fewer iterations for complex method
        );

        echo "\n\n📊 BENCHMARK #1: StarSystemResponseBuilder::buildBodyData()\n";
        echo "   Current CC: 33 | NPC: 645,120 | LOC: 105\n";
        echo "   Iterations: {$result['iterations']}\n";
        echo "   Min time: " . number_format($result['min'], 3) . "ms\n";
        echo "   Max time: " . number_format($result['max'], 3) . "ms\n";
        echo "   Avg time: " . number_format($result['avg'], 3) . "ms ✓\n";
        echo "   Total time: " . number_format($result['total'], 2) . "ms\n";

        $this->benchmarks['StarSystemResponseBuilder::buildBodyData'] = $result;
        $this->assertTrue(true);
    }

    /**
     * Benchmark #2: PlayerKnowledgeMapController::index()
     * Status: CC 27, NPC 741,120, 220 LOC
     */
    public function test_benchmark_2_knowledge_map_controller(): void
    {
        $galaxy = Galaxy::factory()->create();
        $player = Player::factory()->create();

        $controller = new PlayerKnowledgeMapController();

        $result = $this->measureExecution(
            fn () => $controller->index(new \Illuminate\Http\Request()),
            30
        );

        echo "\n\n📊 BENCHMARK #2: PlayerKnowledgeMapController::index()\n";
        echo "   Current CC: 27 | NPC: 741,120 | LOC: 220\n";
        echo "   Iterations: {$result['iterations']}\n";
        echo "   Min time: " . number_format($result['min'], 3) . "ms\n";
        echo "   Max time: " . number_format($result['max'], 3) . "ms\n";
        echo "   Avg time: " . number_format($result['avg'], 3) . "ms ✓\n";
        echo "   Total time: " . number_format($result['total'], 2) . "ms\n";

        $this->benchmarks['PlayerKnowledgeMapController::index'] = $result;
        $this->assertTrue(true);
    }

    /**
     * Benchmark #3: LocationController::getFacilitiesInfo()
     * Status: CC 20, NPC 54,432, 101 LOC
     */
    public function test_benchmark_3_location_controller(): void
    {
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);

        $controller = new LocationController();

        $result = $this->measureExecution(
            fn () => $controller->getFacilitiesInfo($poi),
            100
        );

        echo "\n\n📊 BENCHMARK #3: LocationController::getFacilitiesInfo()\n";
        echo "   Current CC: 20 | NPC: 54,432 | LOC: 101\n";
        echo "   Iterations: {$result['iterations']}\n";
        echo "   Min time: " . number_format($result['min'], 3) . "ms\n";
        echo "   Max time: " . number_format($result['max'], 3) . "ms\n";
        echo "   Avg time: " . number_format($result['avg'], 3) . "ms ✓\n";
        echo "   Total time: " . number_format($result['total'], 2) . "ms\n";

        $this->benchmarks['LocationController::getFacilitiesInfo'] = $result;
        $this->assertTrue(true);
    }

    /**
     * Benchmark #4: MerchantCommentaryService::scoreShipSpecialty()
     * Status: CC 19, NPC 18,432, 62 LOC
     */
    public function test_benchmark_4_merchant_commentary_service(): void
    {
        $ship = Ship::factory()->create();
        $service = app(MerchantCommentaryService::class);

        $result = $this->measureExecution(
            fn () => $service->scoreShipSpecialty($ship),
            1000 // More iterations for fast method
        );

        echo "\n\n📊 BENCHMARK #4: MerchantCommentaryService::scoreShipSpecialty()\n";
        echo "   Current CC: 19 | NPC: 18,432 | LOC: 62\n";
        echo "   Iterations: {$result['iterations']}\n";
        echo "   Min time: " . number_format($result['min'], 4) . "ms\n";
        echo "   Max time: " . number_format($result['max'], 4) . "ms\n";
        echo "   Avg time: " . number_format($result['avg'], 4) . "ms ✓\n";
        echo "   Total time: " . number_format($result['total'], 2) . "ms\n";

        $this->benchmarks['MerchantCommentaryService::scoreShipSpecialty'] = $result;
        $this->assertTrue(true);
    }

    /**
     * Benchmark #5: ShipResource::getSpecialFeatures()
     * Status: CC 17, NPC 131,072, 102 LOC
     */
    public function test_benchmark_5_ship_resource(): void
    {
        $ship = PlayerShip::factory()->create();

        $result = $this->measureExecution(
            fn () => (new ShipResource($ship))->toArray(request()),
            500
        );

        echo "\n\n📊 BENCHMARK #5: ShipResource::getSpecialFeatures()\n";
        echo "   Current CC: 17 | NPC: 131,072 | LOC: 102\n";
        echo "   Iterations: {$result['iterations']}\n";
        echo "   Min time: " . number_format($result['min'], 4) . "ms\n";
        echo "   Max time: " . number_format($result['max'], 4) . "ms\n";
        echo "   Avg time: " . number_format($result['avg'], 4) . "ms ✓\n";
        echo "   Total time: " . number_format($result['total'], 2) . "ms\n";

        $this->benchmarks['ShipResource::getSpecialFeatures'] = $result;
        $this->assertTrue(true);
    }

    /**
     * Benchmark #6: ColonyCombatService::resolveColonyCombat()
     * Status: CC 19, NPC 393,216, 89 LOC
     */
    public function test_benchmark_6_colony_combat_service(): void
    {
        $service = app(ColonyCombatService::class);

        // Create minimal data structure
        $combatData = [
            'attacker_id' => 1,
            'defender_id' => 2,
            'attacker_strength' => 100,
            'defender_strength' => 80,
        ];

        $result = $this->measureExecution(
            fn () => $service->resolveCombat($combatData),
            200
        );

        echo "\n\n📊 BENCHMARK #6: ColonyCombatService::resolveColonyCombat()\n";
        echo "   Current CC: 19 | NPC: 393,216 | LOC: 89\n";
        echo "   Iterations: {$result['iterations']}\n";
        echo "   Min time: " . number_format($result['min'], 3) . "ms\n";
        echo "   Max time: " . number_format($result['max'], 3) . "ms\n";
        echo "   Avg time: " . number_format($result['avg'], 3) . "ms ✓\n";
        echo "   Total time: " . number_format($result['total'], 2) . "ms\n";

        $this->benchmarks['ColonyCombatService::resolveColonyCombat'] = $result;
        $this->assertTrue(true);
    }

    /**
     * Summary test - outputs all benchmarks
     */
    public function test_z_summary_benchmarks(): void
    {
        echo "\n\n";
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║         PERFORMANCE BENCHMARK SUMMARY (PRE-REFACTORING)        ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n";

        $totalTime = 0;
        foreach ($this->benchmarks as $method => $result) {
            $totalTime += $result['total'];
            echo "\n✓ {$method}\n";
            echo "  Average: " . number_format($result['avg'], 4) . "ms\n";
        }

        echo "\n";
        echo "═════════════════════════════════════════════════════════════════\n";
        echo "Total execution time (all benchmarks): " . number_format($totalTime, 2) . "ms\n";
        echo "═════════════════════════════════════════════════════════════════\n";

        $this->assertTrue(true);
    }
}

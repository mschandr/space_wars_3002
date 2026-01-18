<?php

namespace App\Console\Commands;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\StellarCartographer;
use App\Models\TradingHub;
use App\Models\User;
use App\Models\WarpGate;
use App\Services\StarChartService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BenchmarkNavigationCommand extends Command
{
    protected $signature = 'benchmark:navigation {--systems=50 : Number of nearby systems to create} {--charts=25 : Number of star charts player has}';

    protected $description = 'Benchmark NavigationController and StarChartService performance';

    public function handle(): int
    {
        $systemCount = (int) $this->option('systems');
        $chartCount = (int) $this->option('charts');

        $this->info("Setting up benchmark with {$systemCount} systems and {$chartCount} charts...");

        // Create test data
        $user = User::factory()->create(['email' => 'benchmark-'.Str::random(8).'@test.com']);
        $galaxy = Galaxy::factory()->create(['name' => 'Benchmark Galaxy '.Str::random(4)]);

        $currentLocation = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
            'x' => 500,
            'y' => 500,
        ]);

        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $currentLocation->id,
            'credits' => 1000000,
        ]);

        $shipBlueprint = Ship::first() ?? Ship::factory()->create();

        $ship = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $shipBlueprint->id,
            'is_active' => true,
            'sensors' => 10, // 1000 unit range
        ]);

        // Create nearby systems
        $this->info("Creating {$systemCount} nearby star systems...");
        $nearbySystems = collect();
        for ($i = 0; $i < $systemCount; $i++) {
            $angle = ($i / $systemCount) * 2 * M_PI;
            $distance = 100 + rand(0, 800); // Within sensor range
            $x = max(10, min(990, 500 + cos($angle) * $distance));
            $y = max(10, min(990, 500 + sin($angle) * $distance));

            $poi = PointOfInterest::factory()->create([
                'galaxy_id' => $galaxy->id,
                'type' => PointOfInterestType::STAR,
                'is_inhabited' => rand(0, 1) === 1,
                'x' => $x,
                'y' => $y,
            ]);
            $nearbySystems->push($poi);
        }

        // Create warp gates between systems for BFS testing
        $this->info("Creating warp gate network...");
        $allSystems = $nearbySystems->prepend($currentLocation);
        foreach ($allSystems->take(30) as $index => $source) {
            // Connect to 2-4 random destinations
            $destinations = $allSystems->except($index)->random(min(3, $allSystems->count() - 1));
            foreach ($destinations as $dest) {
                if (!WarpGate::where('source_poi_id', $source->id)->where('destination_poi_id', $dest->id)->exists()) {
                    WarpGate::create([
                        'uuid' => Str::uuid(),
                        'galaxy_id' => $galaxy->id,
                        'source_poi_id' => $source->id,
                        'destination_poi_id' => $dest->id,
                        'fuel_cost' => 10,
                        'distance' => sqrt(pow($source->x - $dest->x, 2) + pow($source->y - $dest->y, 2)),
                        'is_hidden' => false,
                        'status' => 'active',
                    ]);
                }
            }
        }

        // Grant some star charts to player
        $this->info("Granting {$chartCount} star charts to player...");
        $chartSystems = $nearbySystems->random(min($chartCount, $nearbySystems->count()));
        $now = now();
        foreach ($chartSystems as $system) {
            DB::table('player_star_charts')->insert([
                'player_id' => $player->id,
                'revealed_poi_id' => $system->id,
                'purchased_from_poi_id' => $currentLocation->id,
                'price_paid' => 0,
                'purchased_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Create a trading hub with cartographer for StarChartService testing
        $tradingHub = TradingHub::factory()->create([
            'poi_id' => $currentLocation->id,
        ]);

        $cartographer = StellarCartographer::create([
            'poi_id' => $currentLocation->id,
            'name' => 'Benchmark Cartographer',
            'is_active' => true,
            'chart_base_price' => 1000,
            'markup_multiplier' => 1.0,
        ]);

        $this->newLine();
        $this->info('=== BENCHMARK RESULTS ===');
        $this->newLine();

        // Benchmark 1: getNearbySystems simulation
        $this->info('1. NavigationController::getNearbySystems()');
        $this->benchmarkNearbySystems($player, $currentLocation, $systemCount);

        // Benchmark 2: scanLocal simulation
        $this->info('2. NavigationController::scanLocal()');
        $this->benchmarkScanLocal($player, $currentLocation);

        // Benchmark 3: StarChartService::calculateChartPrice
        $this->info('3. StarChartService::calculateChartPrice()');
        $this->benchmarkChartPrice($player, $currentLocation, $cartographer);

        // Benchmark 4: StarChartService::getChartCoverage
        $this->info('4. StarChartService::getChartCoverage()');
        $this->benchmarkChartCoverage($currentLocation);

        // Benchmark 5: StarChartService::getAvailableCharts
        $this->info('5. StarChartService::getAvailableCharts()');
        $this->benchmarkAvailableCharts($player, $cartographer);

        // Cleanup
        $this->newLine();
        $this->info('Cleaning up benchmark data...');
        DB::table('player_star_charts')->where('player_id', $player->id)->delete();
        WarpGate::where('galaxy_id', $galaxy->id)->delete();
        PointOfInterest::where('galaxy_id', $galaxy->id)->delete();
        PlayerShip::where('player_id', $player->id)->delete();
        Player::where('id', $player->id)->delete();
        TradingHub::where('poi_id', $currentLocation->id)->delete();
        StellarCartographer::where('poi_id', $currentLocation->id)->delete();
        Galaxy::where('id', $galaxy->id)->delete();
        User::where('id', $user->id)->delete();

        $this->info('Benchmark complete!');

        return Command::SUCCESS;
    }

    private function benchmarkNearbySystems(Player $player, PointOfInterest $location, int $systemCount): void
    {
        // Reload player with relationships
        $player = Player::with(['currentLocation', 'activeShip'])->find($player->id);

        DB::enableQueryLog();
        $startTime = microtime(true);

        // Simulate the controller logic
        $sensorLevel = $player->activeShip->sensors;
        $sensorRange = $sensorLevel * 100;

        $nearbySystems = PointOfInterest::where('galaxy_id', $location->galaxy_id)
            ->where('id', '!=', $location->id)
            ->where('type', PointOfInterestType::STAR)
            ->selectRaw('*, SQRT(POW(CAST(x AS SIGNED) - ?, 2) + POW(CAST(y AS SIGNED) - ?, 2)) as distance', [$location->x, $location->y])
            ->having('distance', '<=', $sensorRange)
            ->orderBy('distance')
            ->limit(50)
            ->get();

        $systems = $nearbySystems->map(function ($system) use ($player) {
            $hasChart = $player->hasChartFor($system);
            return [
                'uuid' => $system->uuid,
                'name' => $hasChart ? $system->name : 'Unknown System',
                'has_chart' => $hasChart,
            ];
        });

        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->displayResults($endTime - $startTime, count($queries), $systems->count() . ' systems processed');
    }

    private function benchmarkScanLocal(Player $player, PointOfInterest $location): void
    {
        $player = Player::with(['currentLocation', 'activeShip'])->find($player->id);

        DB::enableQueryLog();
        $startTime = microtime(true);

        $sensorLevel = $player->activeShip->sensors;
        $sensorRange = $sensorLevel * 100;

        $nearbyPOIs = PointOfInterest::where('galaxy_id', $location->galaxy_id)
            ->where('id', '!=', $location->id)
            ->selectRaw('*, SQRT(POW(CAST(x AS SIGNED) - ?, 2) + POW(CAST(y AS SIGNED) - ?, 2)) as distance', [$location->x, $location->y])
            ->having('distance', '<=', $sensorRange)
            ->orderBy('distance')
            ->limit(100)
            ->get();

        $groupedByType = $nearbyPOIs->groupBy('type')->map(function ($group) use ($player) {
            return $group->map(function ($poi) use ($player) {
                $hasChart = $player->hasChartFor($poi);
                return [
                    'uuid' => $poi->uuid,
                    'has_chart' => $hasChart,
                ];
            })->values();
        });

        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->displayResults($endTime - $startTime, count($queries), $nearbyPOIs->count() . ' POIs processed');
    }

    private function benchmarkChartPrice(Player $player, PointOfInterest $location, StellarCartographer $cartographer): void
    {
        DB::enableQueryLog();
        $startTime = microtime(true);

        $service = new StarChartService();
        $price = $service->calculateChartPrice($location, $player, $cartographer);

        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->displayResults($endTime - $startTime, count($queries), "Price: {$price} credits");
    }

    private function benchmarkChartCoverage(PointOfInterest $location): void
    {
        DB::enableQueryLog();
        $startTime = microtime(true);

        $service = new StarChartService();
        $coverage = $service->getChartCoverage($location, 2);

        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->displayResults($endTime - $startTime, count($queries), $coverage->count() . ' systems in coverage');
    }

    private function benchmarkAvailableCharts(Player $player, StellarCartographer $cartographer): void
    {
        DB::enableQueryLog();
        $startTime = microtime(true);

        $service = new StarChartService();
        $charts = $service->getAvailableCharts($cartographer, $player);

        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $unknownCount = $charts->first()['unknown_systems'] ?? 0;
        $this->displayResults($endTime - $startTime, count($queries), "{$unknownCount} unknown systems");
    }

    private function displayResults(float $duration, int $queryCount, string $extra = ''): void
    {
        $durationMs = round($duration * 1000, 2);
        $this->line("   Duration: <comment>{$durationMs}ms</comment>");
        $this->line("   Queries:  <comment>{$queryCount}</comment>");
        if ($extra) {
            $this->line("   Result:   <comment>{$extra}</comment>");
        }
        $this->newLine();
    }
}

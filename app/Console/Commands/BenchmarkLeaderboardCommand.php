<?php

namespace App\Console\Commands;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Colony;
use App\Models\CombatParticipant;
use App\Models\CombatSession;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerCargo;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BenchmarkLeaderboardCommand extends Command
{
    protected $signature = 'benchmark:leaderboard {--players=50 : Number of players to create}';

    protected $description = 'Benchmark LeaderboardController performance';

    private Galaxy $galaxy;
    private array $players = [];

    public function handle(): int
    {
        $playerCount = (int) $this->option('players');

        $this->info("Setting up benchmark with {$playerCount} players...");

        // Create test data
        $this->galaxy = Galaxy::factory()->create(['name' => 'Benchmark Galaxy '.Str::random(4)]);

        $location = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
            'x' => 500,
            'y' => 500,
        ]);

        $shipBlueprint = Ship::first() ?? Ship::factory()->create();

        $this->info("Creating {$playerCount} players with ships, colonies, and combat data...");

        for ($i = 0; $i < $playerCount; $i++) {
            $user = User::factory()->create(['email' => "benchmark-{$i}-".Str::random(4).'@test.com']);

            $player = Player::factory()->create([
                'user_id' => $user->id,
                'galaxy_id' => $this->galaxy->id,
                'current_poi_id' => $location->id,
                'credits' => rand(10000, 1000000),
                'level' => rand(1, 50),
                'experience' => rand(100, 50000),
            ]);

            // Create ships for economic leaderboard
            $ship = PlayerShip::factory()->create([
                'player_id' => $player->id,
                'ship_id' => $shipBlueprint->id,
                'is_active' => true,
            ]);

            // Create colonies for colonial leaderboard
            if (rand(0, 1) === 1) {
                $colonyLocation = PointOfInterest::factory()->create([
                    'galaxy_id' => $this->galaxy->id,
                    'type' => PointOfInterestType::PLANET,
                    'x' => rand(100, 900),
                    'y' => rand(100, 900),
                ]);

                Colony::factory()->create([
                    'player_id' => $player->id,
                    'poi_id' => $colonyLocation->id,
                    'population' => rand(1000, 100000),
                    'development_level' => rand(1, 10),
                ]);
            }

            // Create combat sessions for combat leaderboard
            if (rand(0, 2) > 0) {
                for ($c = 0; $c < rand(1, 5); $c++) {
                    $session = CombatSession::create([
                        'uuid' => Str::uuid(),
                        'combat_type' => rand(0, 1) ? 'pvp' : 'pirate',
                        'status' => 'completed',
                        'current_round' => rand(1, 5),
                        'poi_id' => $location->id,
                        'victor_type' => rand(0, 1) ? 'attacker' : 'defender',
                        'started_at' => now()->subDays(rand(1, 30)),
                        'ended_at' => now()->subDays(rand(0, 29)),
                    ]);

                    CombatParticipant::create([
                        'combat_session_id' => $session->id,
                        'player_id' => $player->id,
                        'player_ship_id' => $ship->id,
                        'side' => rand(0, 1) ? 'attacker' : 'defender',
                        'starting_hull' => 100,
                        'current_hull' => rand(0, 100),
                        'damage_dealt' => rand(50, 500),
                        'damage_taken' => rand(20, 200),
                        'survived' => rand(0, 1) === 1,
                        'result' => rand(0, 1) ? 'victory' : 'defeat',
                        'xp_earned' => rand(50, 500),
                        'credits_earned' => rand(100, 5000),
                    ]);
                }
            }

            $this->players[] = $player;
        }

        $this->newLine();
        $this->info('=== BENCHMARK RESULTS ===');
        $this->newLine();

        // Benchmark 1: Overall leaderboard
        $this->info('1. LeaderboardController::overall()');
        $this->benchmarkOverall();

        // Benchmark 2: Combat leaderboard
        $this->info('2. LeaderboardController::combat()');
        $this->benchmarkCombat();

        // Benchmark 3: Economic leaderboard
        $this->info('3. LeaderboardController::economic()');
        $this->benchmarkEconomic();

        // Benchmark 4: Colonial leaderboard
        $this->info('4. LeaderboardController::colonial()');
        $this->benchmarkColonial();

        // Cleanup
        $this->newLine();
        $this->info('Cleaning up benchmark data...');
        $this->cleanup();

        $this->info('Benchmark complete!');

        return Command::SUCCESS;
    }

    private function benchmarkOverall(): void
    {
        DB::enableQueryLog();
        $startTime = microtime(true);

        $players = Player::where('galaxy_id', $this->galaxy->id)
            ->where('status', 'active')
            ->with(['activeShip:id,player_id,name,hull', 'user:id,name'])
            ->select(['id', 'uuid', 'user_id', 'call_sign', 'level', 'experience', 'credits'])
            ->orderByDesc('level')
            ->orderByDesc('experience')
            ->orderByDesc('credits')
            ->limit(100)
            ->get();

        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->displayResults($endTime - $startTime, count($queries), $players->count() . ' players');
    }

    private function benchmarkCombat(): void
    {
        DB::enableQueryLog();
        $startTime = microtime(true);

        // OPTIMIZED: Pre-aggregate combat stats in a single query
        $combatStats = DB::table('combat_participants as cp')
            ->join('combat_sessions as cs', 'cp.combat_session_id', '=', 'cs.id')
            ->join('players as p', 'cp.player_id', '=', 'p.id')
            ->where('p.galaxy_id', $this->galaxy->id)
            ->where('p.status', 'active')
            ->where('cs.status', 'completed')
            ->select('cp.player_id')
            ->selectRaw("SUM(CASE WHEN cs.combat_type = 'pvp' AND cp.side IN ('attacker', 'ally_attacker') AND cs.victor_type = 'attacker' THEN 1 ELSE 0 END) as pvp_wins")
            ->selectRaw("SUM(CASE WHEN cs.combat_type = 'pvp' AND cp.side IN ('defender', 'ally_defender') AND cs.victor_type = 'attacker' THEN 1 ELSE 0 END) as pvp_losses")
            ->selectRaw("SUM(CASE WHEN cs.combat_type = 'pirate' AND cs.victor_type IN ('player', 'attacker') THEN 1 ELSE 0 END) as pirate_kills")
            ->groupBy('cp.player_id')
            ->get()
            ->keyBy('player_id');

        $players = Player::where('galaxy_id', $this->galaxy->id)
            ->where('status', 'active')
            ->with(['user:id,name'])
            ->get()
            ->map(function ($player) use ($combatStats) {
                $stats = $combatStats->get($player->id);
                $pvpWins = (int) ($stats->pvp_wins ?? 0);
                $pirateKills = (int) ($stats->pirate_kills ?? 0);

                return [
                    'player' => $player,
                    'pvp_wins' => $pvpWins,
                    'pirate_kills' => $pirateKills,
                    'combat_score' => ($pvpWins * 10) + ($pirateKills * 5),
                ];
            })
            ->sortByDesc('combat_score')
            ->take(100);

        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->displayResults($endTime - $startTime, count($queries), $players->count() . ' players');
    }

    private function benchmarkEconomic(): void
    {
        DB::enableQueryLog();
        $startTime = microtime(true);

        // OPTIMIZED: Pre-aggregate values in separate queries
        $shipValues = DB::table('player_ships')
            ->join('ships', 'player_ships.ship_id', '=', 'ships.id')
            ->join('players', 'player_ships.player_id', '=', 'players.id')
            ->where('players.galaxy_id', $this->galaxy->id)
            ->where('players.status', 'active')
            ->select('player_ships.player_id')
            ->selectRaw('SUM(ships.base_price) as total_ship_value')
            ->groupBy('player_ships.player_id')
            ->get()
            ->keyBy('player_id');

        $colonyValues = DB::table('colonies')
            ->join('players', 'colonies.player_id', '=', 'players.id')
            ->where('players.galaxy_id', $this->galaxy->id)
            ->where('players.status', 'active')
            ->select('colonies.player_id')
            ->selectRaw('COUNT(*) as colony_count')
            ->selectRaw('SUM(colonies.development_level * 10000) as total_colony_value')
            ->groupBy('colonies.player_id')
            ->get()
            ->keyBy('player_id');

        $players = Player::where('galaxy_id', $this->galaxy->id)
            ->where('status', 'active')
            ->with(['user:id,name'])
            ->get()
            ->map(function ($player) use ($shipValues, $colonyValues) {
                $shipValue = (float) ($shipValues->get($player->id)->total_ship_value ?? 0);
                $colonyData = $colonyValues->get($player->id);
                $colonyValue = (float) ($colonyData->total_colony_value ?? 0);
                $netWorth = $player->credits + $shipValue + $colonyValue;

                return [
                    'player' => $player,
                    'net_worth' => $netWorth,
                ];
            })
            ->sortByDesc('net_worth')
            ->take(100);

        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->displayResults($endTime - $startTime, count($queries), $players->count() . ' players');
    }

    private function benchmarkColonial(): void
    {
        DB::enableQueryLog();
        $startTime = microtime(true);

        // OPTIMIZED: Calculate galaxy population ONCE
        $galaxyPopulation = Colony::whereHas('player', function ($q) {
            $q->where('galaxy_id', $this->galaxy->id);
        })->sum('population');

        // Pre-aggregate colony stats in a single query
        $colonyStats = DB::table('colonies')
            ->join('players', 'colonies.player_id', '=', 'players.id')
            ->where('players.galaxy_id', $this->galaxy->id)
            ->where('players.status', 'active')
            ->select('colonies.player_id')
            ->selectRaw('COUNT(*) as colony_count')
            ->selectRaw('SUM(colonies.population) as total_population')
            ->selectRaw('AVG(colonies.development_level) as avg_development')
            ->groupBy('colonies.player_id')
            ->get()
            ->keyBy('player_id');

        $players = Player::where('galaxy_id', $this->galaxy->id)
            ->where('status', 'active')
            ->with(['user:id,name'])
            ->get()
            ->map(function ($player) use ($colonyStats, $galaxyPopulation) {
                $stats = $colonyStats->get($player->id);
                $colonyCount = (int) ($stats->colony_count ?? 0);
                $totalPopulation = (int) ($stats->total_population ?? 0);
                $avgDevelopment = (float) ($stats->avg_development ?? 0);

                return [
                    'player' => $player,
                    'total_population' => $totalPopulation,
                    'avg_development' => $avgDevelopment,
                    'population_share' => $galaxyPopulation > 0 ? ($totalPopulation / $galaxyPopulation) * 100 : 0,
                    'colonial_score' => ($colonyCount * 100) + ($totalPopulation / 10),
                ];
            })
            ->sortByDesc('colonial_score')
            ->take(100);

        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->displayResults($endTime - $startTime, count($queries), $players->count() . ' players');
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

    private function cleanup(): void
    {
        $playerIds = collect($this->players)->pluck('id')->toArray();
        $userIds = collect($this->players)->pluck('user_id')->toArray();

        CombatParticipant::whereIn('player_id', $playerIds)->delete();
        CombatSession::where('poi_id', '!=', null)
            ->whereHas('poi', fn ($q) => $q->where('galaxy_id', $this->galaxy->id))
            ->delete();
        Colony::whereIn('player_id', $playerIds)->delete();
        PlayerCargo::whereHas('playerShip', fn ($q) => $q->whereIn('player_id', $playerIds))->delete();
        PlayerShip::whereIn('player_id', $playerIds)->delete();
        Player::whereIn('id', $playerIds)->delete();
        PointOfInterest::where('galaxy_id', $this->galaxy->id)->delete();
        Galaxy::where('id', $this->galaxy->id)->delete();
        User::whereIn('id', $userIds)->delete();
    }
}

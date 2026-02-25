<?php

namespace App\Http\Controllers\Api;

use App\Models\Colony;
use App\Models\Galaxy;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends BaseApiController
{
    /**
     * Get overall leaderboard (level, XP, credits)
     */
    public function overall(string $galaxyUuid, Request $request): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $galaxyUuid)->firstOrFail();
        $limit = min($request->get('limit', 100), 500);

        $players = Player::where('galaxy_id', $galaxy->id)
            ->where('status', 'active')
            ->with(['activeShip:id,player_id,name,hull', 'user:id,name'])
            ->select(['id', 'uuid', 'user_id', 'call_sign', 'level', 'experience', 'credits'])
            ->orderByDesc('level')
            ->orderByDesc('experience')
            ->orderByDesc('credits')
            ->limit($limit)
            ->get()
            ->map(function ($player, $index) {
                return [
                    'rank' => $index + 1,
                    'player' => [
                        'uuid' => $player->uuid,
                        'call_sign' => $player->call_sign,
                        'user_name' => $player->user->name ?? 'Unknown',
                    ],
                    'stats' => [
                        'level' => $player->level,
                        'experience' => $player->experience,
                        'credits' => $player->credits,
                    ],
                    'ship' => $player->activeShip ? [
                        'name' => $player->activeShip->name,
                        'hull' => $player->activeShip->hull,
                    ] : null,
                ];
            });

        return $this->success([
            'galaxy' => [
                'uuid' => $galaxy->uuid,
                'name' => $galaxy->name,
            ],
            'leaderboard_type' => 'overall',
            'total_players' => Player::where('galaxy_id', $galaxy->id)->where('status', 'active')->count(),
            'leaders' => $players,
        ], 'Overall leaderboard retrieved successfully');
    }

    /**
     * Get combat leaderboard (PvP wins, pirate kills, K/D ratio)
     * Optimized: Uses single aggregation query instead of N+1
     */
    public function combat(string $galaxyUuid, Request $request): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $galaxyUuid)->firstOrFail();
        $limit = min($request->get('limit', 100), 500);

        // Pre-aggregate combat stats in a single query using subqueries
        $combatStats = DB::table('combat_participants as cp')
            ->join('combat_sessions as cs', 'cp.combat_session_id', '=', 'cs.id')
            ->join('players as p', 'cp.player_id', '=', 'p.id')
            ->where('p.galaxy_id', $galaxy->id)
            ->where('p.status', 'active')
            ->where('cs.status', 'completed')
            ->select('cp.player_id')
            ->selectRaw("
                SUM(CASE
                    WHEN cs.combat_type = 'pvp'
                    AND cp.side IN ('attacker', 'ally_attacker')
                    AND cs.victor_type = 'attacker'
                    THEN 1 ELSE 0 END) as pvp_wins
            ")
            ->selectRaw("
                SUM(CASE
                    WHEN cs.combat_type = 'pvp'
                    AND cp.side IN ('defender', 'ally_defender')
                    AND cs.victor_type = 'attacker'
                    THEN 1 ELSE 0 END) as pvp_losses
            ")
            ->selectRaw("
                SUM(CASE
                    WHEN cs.combat_type = 'pirate'
                    AND cs.victor_type IN ('player', 'attacker')
                    THEN 1 ELSE 0 END) as pirate_kills
            ")
            ->groupBy('cp.player_id')
            ->get()
            ->keyBy('player_id');

        // Get players with their combat stats
        $players = Player::where('galaxy_id', $galaxy->id)
            ->where('status', 'active')
            ->with(['user:id,name'])
            ->get()
            ->map(function ($player) use ($combatStats) {
                $stats = $combatStats->get($player->id);
                $pvpWins = (int) ($stats->pvp_wins ?? 0);
                $pvpLosses = (int) ($stats->pvp_losses ?? 0);
                $pirateKills = (int) ($stats->pirate_kills ?? 0);
                $totalKills = $pvpWins + $pirateKills;
                $kdRatio = $pvpLosses > 0 ? round($totalKills / $pvpLosses, 2) : (float) $totalKills;

                return [
                    'player' => $player,
                    'combat_stats' => [
                        'pvp_wins' => $pvpWins,
                        'pvp_losses' => $pvpLosses,
                        'pirate_kills' => $pirateKills,
                        'total_kills' => $totalKills,
                        'kd_ratio' => $kdRatio,
                    ],
                    'combat_score' => ($pvpWins * 10) + ($pirateKills * 5) + ($kdRatio * 2),
                ];
            })
            ->sortByDesc('combat_score')
            ->take($limit)
            ->values()
            ->map(function ($entry, $index) {
                return [
                    'rank' => $index + 1,
                    'player' => [
                        'uuid' => $entry['player']->uuid,
                        'call_sign' => $entry['player']->call_sign,
                        'user_name' => $entry['player']->user->name ?? 'Unknown',
                    ],
                    'combat_stats' => $entry['combat_stats'],
                    'combat_score' => round($entry['combat_score'], 2),
                ];
            });

        return $this->success([
            'galaxy' => [
                'uuid' => $galaxy->uuid,
                'name' => $galaxy->name,
            ],
            'leaderboard_type' => 'combat',
            'leaders' => $players,
        ], 'Combat leaderboard retrieved successfully');
    }

    /**
     * Get economic leaderboard (net worth, trade volume, colonies owned)
     * Optimized: Pre-aggregates ship, cargo, and colony values in separate queries
     */
    public function economic(string $galaxyUuid, Request $request): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $galaxyUuid)->firstOrFail();
        $limit = min($request->get('limit', 100), 500);

        // Pre-aggregate ship values per player (using ship blueprint base_price)
        $shipValues = DB::table('player_ships')
            ->join('ships', 'player_ships.ship_id', '=', 'ships.id')
            ->join('players', 'player_ships.player_id', '=', 'players.id')
            ->where('players.galaxy_id', $galaxy->id)
            ->where('players.status', 'active')
            ->select('player_ships.player_id')
            ->selectRaw('SUM(ships.base_price) as total_ship_value')
            ->groupBy('player_ships.player_id')
            ->get()
            ->keyBy('player_id');

        // Pre-aggregate cargo values per player
        $cargoValues = DB::table('player_cargos')
            ->join('player_ships', 'player_cargos.player_ship_id', '=', 'player_ships.id')
            ->join('minerals', 'player_cargos.mineral_id', '=', 'minerals.id')
            ->join('players', 'player_ships.player_id', '=', 'players.id')
            ->where('players.galaxy_id', $galaxy->id)
            ->where('players.status', 'active')
            ->select('player_ships.player_id')
            ->selectRaw('SUM(player_cargos.quantity * minerals.base_value) as total_cargo_value')
            ->groupBy('player_ships.player_id')
            ->get()
            ->keyBy('player_id');

        // Pre-aggregate colony values per player
        $colonyValues = DB::table('colonies')
            ->join('players', 'colonies.player_id', '=', 'players.id')
            ->where('players.galaxy_id', $galaxy->id)
            ->where('players.status', 'active')
            ->select('colonies.player_id')
            ->selectRaw('COUNT(*) as colony_count')
            ->selectRaw('SUM(colonies.development_level * 10000) as total_colony_value')
            ->groupBy('colonies.player_id')
            ->get()
            ->keyBy('player_id');

        $players = Player::where('galaxy_id', $galaxy->id)
            ->where('status', 'active')
            ->with(['user:id,name'])
            ->get()
            ->map(function ($player) use ($shipValues, $cargoValues, $colonyValues) {
                $shipValue = (float) ($shipValues->get($player->id)->total_ship_value ?? 0);
                $cargoValue = (float) ($cargoValues->get($player->id)->total_cargo_value ?? 0);
                $colonyData = $colonyValues->get($player->id);
                $colonyCount = (int) ($colonyData->colony_count ?? 0);
                $colonyValue = (float) ($colonyData->total_colony_value ?? 0);

                $netWorth = $player->credits + $shipValue + $cargoValue + $colonyValue;

                return [
                    'player' => $player,
                    'economic_stats' => [
                        'net_worth' => round($netWorth, 2),
                        'liquid_credits' => $player->credits,
                        'ship_value' => round($shipValue, 2),
                        'cargo_value' => round($cargoValue, 2),
                        'colony_count' => $colonyCount,
                        'colony_value' => round($colonyValue, 2),
                    ],
                ];
            })
            ->sortByDesc('economic_stats.net_worth')
            ->take($limit)
            ->values()
            ->map(function ($entry, $index) {
                return [
                    'rank' => $index + 1,
                    'player' => [
                        'uuid' => $entry['player']->uuid,
                        'call_sign' => $entry['player']->call_sign,
                        'user_name' => $entry['player']->user->name ?? 'Unknown',
                    ],
                    'economic_stats' => $entry['economic_stats'],
                ];
            });

        return $this->success([
            'galaxy' => [
                'uuid' => $galaxy->uuid,
                'name' => $galaxy->name,
            ],
            'leaderboard_type' => 'economic',
            'leaders' => $players,
        ], 'Economic leaderboard retrieved successfully');
    }

    /**
     * Get colonial leaderboard (colonies owned, total population, development)
     * Optimized: Pre-calculates galaxy population once, uses aggregation subquery
     */
    public function colonial(string $galaxyUuid, Request $request): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $galaxyUuid)->firstOrFail();
        $limit = min($request->get('limit', 100), 500);

        // Calculate galaxy total population ONCE (was repeated for every player before)
        $galaxyPopulation = Colony::whereHas('player', function ($q) use ($galaxy) {
            $q->where('galaxy_id', $galaxy->id);
        })->sum('population');

        // Pre-aggregate colony stats per player in a single query
        $colonyStats = DB::table('colonies')
            ->join('players', 'colonies.player_id', '=', 'players.id')
            ->where('players.galaxy_id', $galaxy->id)
            ->where('players.status', 'active')
            ->select('colonies.player_id')
            ->selectRaw('COUNT(*) as colony_count')
            ->selectRaw('SUM(colonies.population) as total_population')
            ->selectRaw('AVG(colonies.development_level) as avg_development')
            ->groupBy('colonies.player_id')
            ->get()
            ->keyBy('player_id');

        $players = Player::where('galaxy_id', $galaxy->id)
            ->where('status', 'active')
            ->with(['user:id,name'])
            ->get()
            ->map(function ($player) use ($colonyStats, $galaxyPopulation) {
                $stats = $colonyStats->get($player->id);
                $colonyCount = (int) ($stats->colony_count ?? 0);
                $totalPopulation = (int) ($stats->total_population ?? 0);
                $avgDevelopment = (float) ($stats->avg_development ?? 0);
                $populationShare = $galaxyPopulation > 0 ? ($totalPopulation / $galaxyPopulation) * 100 : 0;

                return [
                    'player' => $player,
                    'colonial_stats' => [
                        'colony_count' => $colonyCount,
                        'total_population' => $totalPopulation,
                        'avg_development' => round($avgDevelopment, 2),
                        'population_share' => round($populationShare, 2),
                    ],
                    'colonial_score' => ($colonyCount * 100) + ($totalPopulation / 10) + ($avgDevelopment * 50),
                ];
            })
            ->sortByDesc('colonial_score')
            ->take($limit)
            ->values()
            ->map(function ($entry, $index) {
                return [
                    'rank' => $index + 1,
                    'player' => [
                        'uuid' => $entry['player']->uuid,
                        'call_sign' => $entry['player']->call_sign,
                        'user_name' => $entry['player']->user->name ?? 'Unknown',
                    ],
                    'colonial_stats' => $entry['colonial_stats'],
                    'colonial_score' => round($entry['colonial_score'], 2),
                ];
            });

        return $this->success([
            'galaxy' => [
                'uuid' => $galaxy->uuid,
                'name' => $galaxy->name,
            ],
            'leaderboard_type' => 'colonial',
            'leaders' => $players,
        ], 'Colonial leaderboard retrieved successfully');
    }

    /**
     * Get player's rankings across all leaderboards
     * Optimized: Uses COUNT with conditions instead of loading all players
     */
    public function playerRanking(string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->with('galaxy')->firstOrFail();

        // Calculate overall rank
        $overallRank = Player::where('galaxy_id', $player->galaxy_id)
            ->where('status', 'active')
            ->where(function ($q) use ($player) {
                $q->where('level', '>', $player->level)
                    ->orWhere(function ($q2) use ($player) {
                        $q2->where('level', $player->level)
                            ->where('experience', '>', $player->experience);
                    })
                    ->orWhere(function ($q3) use ($player) {
                        $q3->where('level', $player->level)
                            ->where('experience', $player->experience)
                            ->where('credits', '>', $player->credits);
                    });
            })
            ->count() + 1;

        // Calculate economic rank (simplified)
        $economicRank = Player::where('galaxy_id', $player->galaxy_id)
            ->where('status', 'active')
            ->where('credits', '>', $player->credits)
            ->count() + 1;

        // Calculate colonial rank - optimized to use subquery instead of loading all players
        $playerColonyCount = $player->colonies()->count();
        $colonialRank = DB::table('players')
            ->where('galaxy_id', $player->galaxy_id)
            ->where('status', 'active')
            ->whereRaw('(SELECT COUNT(*) FROM colonies WHERE colonies.player_id = players.id) > ?', [$playerColonyCount])
            ->count() + 1;

        // Get total players count
        $totalPlayers = Player::where('galaxy_id', $player->galaxy_id)
            ->where('status', 'active')
            ->count();

        return $this->success([
            'player' => [
                'uuid' => $player->uuid,
                'call_sign' => $player->call_sign,
            ],
            'galaxy' => [
                'uuid' => $player->galaxy->uuid,
                'name' => $player->galaxy->name,
            ],
            'rankings' => [
                'overall' => $overallRank,
                'economic' => $economicRank,
                'colonial' => $colonialRank,
            ],
            'total_players' => $totalPlayers,
        ], 'Player rankings retrieved successfully');
    }

    /**
     * Get detailed player statistics
     * Optimized: Uses aggregate queries instead of loading all records
     */
    public function playerStatistics(string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)
            ->with(['galaxy', 'activeShip', 'currentPoi'])
            ->firstOrFail();

        // Combat statistics - use aggregation queries
        $combatAggregates = DB::table('combat_participants as cp')
            ->join('combat_sessions as cs', 'cp.combat_session_id', '=', 'cs.id')
            ->where('cp.player_id', $player->id)
            ->where('cs.status', 'completed')
            ->selectRaw('COUNT(*) as total_battles')
            ->selectRaw("SUM(CASE WHEN cp.result = 'victory' THEN 1 ELSE 0 END) as victories")
            ->selectRaw("SUM(CASE WHEN cp.result = 'defeat' THEN 1 ELSE 0 END) as defeats")
            ->selectRaw('SUM(cp.damage_dealt) as total_damage_dealt')
            ->selectRaw('SUM(cp.damage_taken) as total_damage_taken')
            ->first();

        $combatStats = [
            'total_battles' => (int) ($combatAggregates->total_battles ?? 0),
            'victories' => (int) ($combatAggregates->victories ?? 0),
            'defeats' => (int) ($combatAggregates->defeats ?? 0),
            'total_damage_dealt' => (int) ($combatAggregates->total_damage_dealt ?? 0),
            'total_damage_taken' => (int) ($combatAggregates->total_damage_taken ?? 0),
        ];

        // Economic statistics - use aggregate queries
        $cargoValue = DB::table('player_cargos')
            ->join('player_ships', 'player_cargos.player_ship_id', '=', 'player_ships.id')
            ->join('minerals', 'player_cargos.mineral_id', '=', 'minerals.id')
            ->where('player_ships.player_id', $player->id)
            ->selectRaw('SUM(player_cargos.quantity * minerals.base_value) as total_value')
            ->value('total_value') ?? 0;

        $economicStats = [
            'current_credits' => $player->credits,
            'cargo_value' => round((float) $cargoValue, 2),
            'total_colonies' => Colony::where('player_id', $player->id)->count(),
            'total_ships' => $player->ships()->count(),
        ];

        // Exploration statistics
        $explorationStats = [
            'systems_visited' => $player->starCharts()->count(),
            'current_location' => $player->currentPoi ? [
                'name' => $player->currentPoi->name,
                'type' => $player->currentPoi->type,
            ] : null,
        ];

        return $this->success([
            'player' => [
                'uuid' => $player->uuid,
                'call_sign' => $player->call_sign,
                'level' => $player->level,
                'experience' => $player->experience,
            ],
            'galaxy' => [
                'uuid' => $player->galaxy->uuid,
                'name' => $player->galaxy->name,
            ],
            'statistics' => [
                'combat' => $combatStats,
                'economic' => $economicStats,
                'exploration' => $explorationStats,
            ],
        ], 'Player statistics retrieved successfully');
    }
}

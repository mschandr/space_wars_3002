<?php

namespace App\Http\Controllers\Api;

use App\Models\Colony;
use App\Models\CombatSession;
use App\Models\Galaxy;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     */
    public function combat(string $galaxyUuid, Request $request): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $galaxyUuid)->firstOrFail();
        $limit = min($request->get('limit', 100), 500);

        $players = Player::where('galaxy_id', $galaxy->id)
            ->where('status', 'active')
            ->with(['user:id,name'])
            ->get()
            ->map(function ($player) {
                // Calculate combat statistics
                $pvpWins = CombatSession::where('combat_type', 'pvp')
                    ->where('status', 'completed')
                    ->whereHas('participants', function ($q) use ($player) {
                        $q->where('player_id', $player->id)->where('side', 'like', '%attacker%');
                    })
                    ->whereJsonContains('result->victor', 'attackers')
                    ->count();

                $pvpLosses = CombatSession::where('combat_type', 'pvp')
                    ->where('status', 'completed')
                    ->whereHas('participants', function ($q) use ($player) {
                        $q->where('player_id', $player->id)->where('side', 'like', '%defender%');
                    })
                    ->whereJsonContains('result->victor', 'attackers')
                    ->count();

                $pirateKills = CombatSession::where('combat_type', 'pirate')
                    ->where('status', 'completed')
                    ->whereHas('participants', function ($q) use ($player) {
                        $q->where('player_id', $player->id);
                    })
                    ->whereJsonContains('result->victor', 'player')
                    ->count();

                $totalKills = $pvpWins + $pirateKills;
                $kdRatio = $pvpLosses > 0 ? round($totalKills / $pvpLosses, 2) : $totalKills;

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
     */
    public function economic(string $galaxyUuid, Request $request): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $galaxyUuid)->firstOrFail();
        $limit = min($request->get('limit', 100), 500);

        $players = Player::where('galaxy_id', $galaxy->id)
            ->where('status', 'active')
            ->with(['user:id,name', 'colonies', 'ships', 'cargos.mineral'])
            ->get()
            ->map(function ($player) {
                // Calculate net worth
                $shipValue = $player->ships->sum(function ($ship) {
                    return $ship->calculateValue();
                });

                $cargoValue = $player->cargos->sum(function ($cargo) {
                    return $cargo->quantity * ($cargo->mineral->base_price ?? 0);
                });

                $colonyCount = $player->colonies->count();
                $colonyValue = $player->colonies->sum(function ($colony) {
                    return $colony->development_level * 10000;
                });

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
     */
    public function colonial(string $galaxyUuid, Request $request): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $galaxyUuid)->firstOrFail();
        $limit = min($request->get('limit', 100), 500);

        $players = Player::where('galaxy_id', $galaxy->id)
            ->where('status', 'active')
            ->with(['user:id,name'])
            ->withCount('colonies as colony_count')
            ->get()
            ->map(function ($player) use ($galaxy) {
                $totalPopulation = Colony::where('player_id', $player->id)->sum('population');
                $avgDevelopment = Colony::where('player_id', $player->id)->avg('development_level');
                $galaxyPopulation = Colony::whereHas('player', function ($q) use ($galaxy) {
                    $q->where('galaxy_id', $galaxy->id);
                })->sum('population');
                $populationShare = $galaxyPopulation > 0 ? ($totalPopulation / $galaxyPopulation) * 100 : 0;

                return [
                    'player' => $player,
                    'colonial_stats' => [
                        'colony_count' => $player->colony_count,
                        'total_population' => $totalPopulation,
                        'avg_development' => round($avgDevelopment ?? 0, 2),
                        'population_share' => round($populationShare, 2),
                    ],
                    'colonial_score' => ($player->colony_count * 100) + ($totalPopulation / 10) + (($avgDevelopment ?? 0) * 50),
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

        // Calculate colonial rank
        $playerColonyCount = $player->colonies()->count();
        $colonialRank = Player::where('galaxy_id', $player->galaxy_id)
            ->where('status', 'active')
            ->withCount('colonies')
            ->get()
            ->filter(fn ($p) => $p->colonies_count > $playerColonyCount)
            ->count() + 1;

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
            'total_players' => Player::where('galaxy_id', $player->galaxy_id)->where('status', 'active')->count(),
        ], 'Player rankings retrieved successfully');
    }

    /**
     * Get detailed player statistics
     */
    public function playerStatistics(string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)
            ->with(['galaxy', 'activeShip', 'colonies', 'ships'])
            ->firstOrFail();

        // Combat statistics
        $combatSessions = CombatSession::whereHas('participants', function ($q) use ($player) {
            $q->where('player_id', $player->id);
        })->where('status', 'completed')->get();

        $combatStats = [
            'total_battles' => $combatSessions->count(),
            'victories' => $combatSessions->filter(fn ($s) => $s->result['victor'] ?? null === 'player')->count(),
            'defeats' => $combatSessions->filter(fn ($s) => $s->result['victor'] ?? null !== 'player')->count(),
            'total_damage_dealt' => $player->combatParticipations()->sum('damage_dealt'),
            'total_damage_taken' => $player->combatParticipations()->sum('damage_taken'),
            'ships_destroyed' => $player->combatParticipations()->sum('ships_destroyed'),
        ];

        // Economic statistics
        $totalCargoValue = $player->cargos()->with('mineral')->get()->sum(function ($cargo) {
            return $cargo->quantity * ($cargo->mineral->base_price ?? 0);
        });

        $economicStats = [
            'current_credits' => $player->credits,
            'cargo_value' => round($totalCargoValue, 2),
            'total_colonies' => $player->colonies()->count(),
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

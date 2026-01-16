<?php

namespace App\Http\Controllers\Api;

use App\Models\Colony;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PointOfInterest;
use Illuminate\Http\JsonResponse;

class VictoryController extends BaseApiController
{
    /**
     * Get victory conditions for a galaxy
     */
    public function conditions(string $galaxyUuid): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $galaxyUuid)->firstOrFail();

        $victoryConditions = config('game_config.victory', [
            'merchant_credits' => 1_000_000_000,
            'colonization_share' => 0.5,
            'conquest_share' => 0.6,
            'pirate_power' => 0.7,
        ]);

        $conditions = [
            'merchant_empire' => [
                'name' => 'Merchant Empire',
                'description' => 'Accumulate vast wealth through trading and commerce',
                'requirement' => [
                    'credits' => $victoryConditions['merchant_credits'],
                ],
                'formatted_requirement' => number_format($victoryConditions['merchant_credits']).' credits',
            ],
            'colonization' => [
                'name' => 'Colonization Victory',
                'description' => 'Control the majority of the galaxy\'s population',
                'requirement' => [
                    'population_share' => $victoryConditions['colonization_share'] * 100,
                ],
                'formatted_requirement' => ($victoryConditions['colonization_share'] * 100).'% of galactic population',
            ],
            'conquest' => [
                'name' => 'Conquest Victory',
                'description' => 'Dominate the galaxy through military might',
                'requirement' => [
                    'systems_controlled_share' => $victoryConditions['conquest_share'] * 100,
                ],
                'formatted_requirement' => ($victoryConditions['conquest_share'] * 100).'% of star systems',
            ],
            'pirate_king' => [
                'name' => 'Pirate King',
                'description' => 'Seize control of the outlaw network',
                'requirement' => [
                    'pirate_power_share' => $victoryConditions['pirate_power'] * 100,
                ],
                'formatted_requirement' => ($victoryConditions['pirate_power'] * 100).'% of pirate network',
            ],
        ];

        return $this->success([
            'galaxy' => [
                'uuid' => $galaxy->uuid,
                'name' => $galaxy->name,
            ],
            'victory_conditions' => $conditions,
        ], 'Victory conditions retrieved successfully');
    }

    /**
     * Get player's progress toward all victory conditions
     */
    public function playerProgress(string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)
            ->with('galaxy')
            ->firstOrFail();

        $galaxy = $player->galaxy;

        $victoryConditions = config('game_config.victory', [
            'merchant_credits' => 1_000_000_000,
            'colonization_share' => 0.5,
            'conquest_share' => 0.6,
            'pirate_power' => 0.7,
        ]);

        // Calculate merchant progress
        $merchantProgress = min(($player->credits / $victoryConditions['merchant_credits']) * 100, 100);
        $merchantAchieved = $player->credits >= $victoryConditions['merchant_credits'];

        // Calculate colonization progress
        $galaxyTotalPopulation = Colony::whereHas('player', function ($q) use ($galaxy) {
            $q->where('galaxy_id', $galaxy->id);
        })->sum('population');
        $playerPopulation = Colony::where('player_id', $player->id)->sum('population');
        $populationShare = $galaxyTotalPopulation > 0 ? ($playerPopulation / $galaxyTotalPopulation) : 0;
        $colonizationProgress = min(($populationShare / $victoryConditions['colonization_share']) * 100, 100);
        $colonizationAchieved = $populationShare >= $victoryConditions['colonization_share'];

        // Calculate conquest progress
        $totalSystems = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('is_inhabited', true)
            ->count();
        $playerSystems = Colony::where('player_id', $player->id)
            ->whereHas('poi', function ($q) {
                $q->where('is_inhabited', true);
            })
            ->count();
        $systemsShare = $totalSystems > 0 ? ($playerSystems / $totalSystems) : 0;
        $conquestProgress = min(($systemsShare / $victoryConditions['conquest_share']) * 100, 100);
        $conquestAchieved = $systemsShare >= $victoryConditions['conquest_share'];

        // Calculate pirate king progress (placeholder - would need pirate faction implementation)
        $pirateProgress = 0;
        $pirateAchieved = false;

        $progress = [
            'player' => [
                'uuid' => $player->uuid,
                'call_sign' => $player->call_sign,
            ],
            'galaxy' => [
                'uuid' => $galaxy->uuid,
                'name' => $galaxy->name,
            ],
            'victory_paths' => [
                'merchant_empire' => [
                    'progress_percent' => round($merchantProgress, 2),
                    'achieved' => $merchantAchieved,
                    'current' => (int) $player->credits,
                    'required' => $victoryConditions['merchant_credits'],
                    'remaining' => (int) max(0, $victoryConditions['merchant_credits'] - $player->credits),
                ],
                'colonization' => [
                    'progress_percent' => round($colonizationProgress, 2),
                    'achieved' => $colonizationAchieved,
                    'current_population' => (int) $playerPopulation,
                    'galaxy_population' => (int) $galaxyTotalPopulation,
                    'population_share_percent' => round($populationShare * 100, 2),
                    'required_share_percent' => $victoryConditions['colonization_share'] * 100,
                ],
                'conquest' => [
                    'progress_percent' => round($conquestProgress, 2),
                    'achieved' => $conquestAchieved,
                    'current_systems' => $playerSystems,
                    'total_systems' => $totalSystems,
                    'systems_share_percent' => round($systemsShare * 100, 2),
                    'required_share_percent' => $victoryConditions['conquest_share'] * 100,
                ],
                'pirate_king' => [
                    'progress_percent' => round($pirateProgress, 2),
                    'achieved' => $pirateAchieved,
                    'note' => 'Pirate faction takeover not yet implemented',
                ],
            ],
            'closest_to_victory' => $this->getClosestVictoryPath([
                'merchant' => $merchantProgress,
                'colonization' => $colonizationProgress,
                'conquest' => $conquestProgress,
                'pirate' => $pirateProgress,
            ]),
        ];

        return $this->success($progress, 'Victory progress retrieved successfully');
    }

    /**
     * Get players closest to victory in a galaxy
     */
    public function victoryLeaders(string $galaxyUuid): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $galaxyUuid)->firstOrFail();

        $victoryConditions = config('game_config.victory', [
            'merchant_credits' => 1_000_000_000,
            'colonization_share' => 0.5,
            'conquest_share' => 0.6,
            'pirate_power' => 0.7,
        ]);

        $galaxyTotalPopulation = Colony::whereHas('player', function ($q) use ($galaxy) {
            $q->where('galaxy_id', $galaxy->id);
        })->sum('population');
        $totalSystems = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('is_inhabited', true)
            ->count();

        // Get top players for each victory path
        $players = Player::where('galaxy_id', $galaxy->id)
            ->where('status', 'active')
            ->with('user:id,name')
            ->get();

        // Merchant leaders
        $merchantLeaders = $players->sortByDesc('credits')
            ->take(5)
            ->map(function ($player) use ($victoryConditions) {
                return [
                    'uuid' => $player->uuid,
                    'call_sign' => $player->call_sign,
                    'user_name' => $player->user->name ?? 'Unknown',
                    'credits' => $player->credits,
                    'progress_percent' => min(($player->credits / $victoryConditions['merchant_credits']) * 100, 100),
                ];
            })
            ->values();

        // Colonization leaders
        $colonizationLeaders = $players->map(function ($player) use ($galaxyTotalPopulation, $victoryConditions) {
            $playerPopulation = Colony::where('player_id', $player->id)->sum('population');
            $share = $galaxyTotalPopulation > 0 ? ($playerPopulation / $galaxyTotalPopulation) : 0;

            return [
                'uuid' => $player->uuid,
                'call_sign' => $player->call_sign,
                'user_name' => $player->user->name ?? 'Unknown',
                'population' => $playerPopulation,
                'population_share_percent' => round($share * 100, 2),
                'progress_percent' => min(($share / $victoryConditions['colonization_share']) * 100, 100),
            ];
        })
            ->sortByDesc('population_share_percent')
            ->take(5)
            ->values();

        // Conquest leaders
        $conquestLeaders = $players->map(function ($player) use ($totalSystems, $victoryConditions) {
            $playerSystems = Colony::where('player_id', $player->id)
                ->whereHas('poi', function ($q) {
                    $q->where('is_inhabited', true);
                })
                ->count();
            $share = $totalSystems > 0 ? ($playerSystems / $totalSystems) : 0;

            return [
                'uuid' => $player->uuid,
                'call_sign' => $player->call_sign,
                'user_name' => $player->user->name ?? 'Unknown',
                'systems_controlled' => $playerSystems,
                'systems_share_percent' => round($share * 100, 2),
                'progress_percent' => min(($share / $victoryConditions['conquest_share']) * 100, 100),
            ];
        })
            ->sortByDesc('systems_share_percent')
            ->take(5)
            ->values();

        return $this->success([
            'galaxy' => [
                'uuid' => $galaxy->uuid,
                'name' => $galaxy->name,
            ],
            'victory_leaders' => [
                'merchant_empire' => $merchantLeaders,
                'colonization' => $colonizationLeaders,
                'conquest' => $conquestLeaders,
            ],
        ], 'Victory leaders retrieved successfully');
    }

    /**
     * Helper to determine closest victory path
     */
    private function getClosestVictoryPath(array $progressPercentages): array
    {
        $maxProgress = max($progressPercentages);
        $pathKey = array_search($maxProgress, $progressPercentages);

        $pathNames = [
            'merchant' => 'Merchant Empire',
            'colonization' => 'Colonization',
            'conquest' => 'Conquest',
            'pirate' => 'Pirate King',
        ];

        return [
            'path' => $pathKey,
            'name' => $pathNames[$pathKey] ?? 'Unknown',
            'progress_percent' => round($maxProgress, 2),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Models\Galaxy;
use App\Models\PirateCaptain;
use App\Models\PirateFaction;
use App\Models\Player;
use Illuminate\Http\JsonResponse;

class PirateFactionController extends BaseApiController
{
    /**
     * List all pirate factions in a galaxy
     */
    public function index(string $galaxyUuid): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $galaxyUuid)->firstOrFail();

        $factions = PirateFaction::where('galaxy_id', $galaxy->id)
            ->withCount(['captains', 'fleets'])
            ->get();

        $formattedFactions = $factions->map(function ($faction) {
            return [
                'uuid' => $faction->uuid,
                'name' => $faction->name,
                'ideology' => $faction->ideology ?? 'Unknown',
                'strength' => $faction->strength ?? 0,
                'territory_control' => $faction->territory_control ?? 0,
                'statistics' => [
                    'total_captains' => $faction->captains_count,
                    'total_fleets' => $faction->fleets_count,
                ],
                'description' => $faction->description ?? 'A notorious pirate faction operating in the galaxy.',
            ];
        });

        return $this->success([
            'galaxy' => [
                'uuid' => $galaxy->uuid,
                'name' => $galaxy->name,
            ],
            'total_factions' => $factions->count(),
            'factions' => $formattedFactions,
        ], 'Pirate factions retrieved successfully');
    }

    /**
     * Get specific pirate faction details
     */
    public function show(string $factionUuid): JsonResponse
    {
        $faction = PirateFaction::where('uuid', $factionUuid)
            ->with(['galaxy', 'captains'])
            ->withCount(['captains', 'fleets'])
            ->firstOrFail();

        $factionData = [
            'uuid' => $faction->uuid,
            'name' => $faction->name,
            'ideology' => $faction->ideology ?? 'Unknown',
            'galaxy' => [
                'uuid' => $faction->galaxy->uuid,
                'name' => $faction->galaxy->name,
            ],
            'statistics' => [
                'strength' => $faction->strength ?? 0,
                'territory_control' => $faction->territory_control ?? 0,
                'total_captains' => $faction->captains_count,
                'total_fleets' => $faction->fleets_count,
            ],
            'description' => $faction->description ?? 'A notorious pirate faction operating in the galaxy.',
            'notable_captains' => $faction->captains->take(5)->map(function ($captain) {
                return [
                    'uuid' => $captain->uuid,
                    'name' => $captain->name,
                    'reputation' => $captain->reputation ?? 0,
                ];
            }),
        ];

        return $this->success($factionData, 'Pirate faction details retrieved successfully');
    }

    /**
     * Get player's reputation with pirate factions
     */
    public function playerReputation(string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)
            ->with('galaxy')
            ->firstOrFail();

        $factions = PirateFaction::where('galaxy_id', $player->galaxy_id)->get();

        // Calculate reputation based on combat history
        $reputations = $factions->map(function ($faction) use ($player) {
            // Count pirate kills for this faction
            $pirateKills = \App\Models\CombatSession::where('combat_type', 'pirate')
                ->where('status', 'completed')
                ->whereHas('participants', function ($q) use ($player) {
                    $q->where('player_id', $player->id);
                })
                ->whereJsonContains('result->victor', 'player')
                ->count();

            // Base reputation starts neutral (0)
            // Each kill reduces reputation by -10
            $reputation = 0 - ($pirateKills * 10);

            $standing = match (true) {
                $reputation >= 100 => 'Allied',
                $reputation >= 50 => 'Friendly',
                $reputation >= 0 => 'Neutral',
                $reputation >= -50 => 'Unfriendly',
                $reputation >= -100 => 'Hostile',
                default => 'Hated',
            };

            return [
                'faction' => [
                    'uuid' => $faction->uuid,
                    'name' => $faction->name,
                ],
                'reputation' => $reputation,
                'standing' => $standing,
                'effects' => $this->getReputationEffects($standing),
            ];
        });

        return $this->success([
            'player' => [
                'uuid' => $player->uuid,
                'call_sign' => $player->call_sign,
            ],
            'galaxy' => [
                'uuid' => $player->galaxy->uuid,
                'name' => $player->galaxy->name,
            ],
            'faction_reputations' => $reputations,
        ], 'Pirate faction reputations retrieved successfully');
    }

    /**
     * List captains in a pirate faction
     */
    public function factionCaptains(string $factionUuid): JsonResponse
    {
        $faction = PirateFaction::where('uuid', $factionUuid)->firstOrFail();

        $captains = PirateCaptain::where('faction_id', $faction->id)
            ->withCount('fleets')
            ->get();

        $formattedCaptains = $captains->map(function ($captain) {
            return [
                'uuid' => $captain->uuid,
                'name' => $captain->name,
                'reputation' => $captain->reputation ?? 0,
                'bounty' => $captain->bounty ?? 0,
                'rank' => $captain->rank ?? 'Captain',
                'fleet_count' => $captain->fleets_count,
                'status' => $captain->is_alive ? 'Active' : 'Deceased',
            ];
        });

        return $this->success([
            'faction' => [
                'uuid' => $faction->uuid,
                'name' => $faction->name,
            ],
            'total_captains' => $captains->count(),
            'captains' => $formattedCaptains,
        ], 'Faction captains retrieved successfully');
    }

    /**
     * Get reputation effects for a given standing
     */
    private function getReputationEffects(string $standing): array
    {
        return match ($standing) {
            'Allied' => [
                'description' => 'Pirates will not attack you',
                'benefits' => ['Safe passage', 'Trade opportunities', 'Intelligence sharing'],
            ],
            'Friendly' => [
                'description' => 'Pirates are less likely to attack',
                'benefits' => ['Reduced encounter rate', 'Better trading prices'],
            ],
            'Neutral' => [
                'description' => 'Standard pirate behavior',
                'benefits' => [],
            ],
            'Unfriendly' => [
                'description' => 'Pirates are more aggressive',
                'drawbacks' => ['Increased encounter rate', 'Higher combat difficulty'],
            ],
            'Hostile' => [
                'description' => 'Pirates will actively hunt you',
                'drawbacks' => ['Frequent ambushes', 'Reinforcements called', 'No quarter given'],
            ],
            'Hated' => [
                'description' => 'You are a priority target',
                'drawbacks' => ['Elite fleets dispatched', 'Bounty on your head', 'No escape possible'],
            ],
            default => [
                'description' => 'Unknown standing',
                'benefits' => [],
            ],
        };
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\GalaxyResource;
use App\Models\Colony;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\Sector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GalaxyController extends BaseApiController
{
    /**
     * List all available galaxies (full details)
     */
    public function index(): JsonResponse
    {
        $galaxies = Galaxy::withCount(['players as active_player_count' => function ($query) {
            $query->where('status', 'active');
        }])->get();

        return $this->success(
            GalaxyResource::collection($galaxies),
            'Galaxies retrieved successfully'
        );
    }

    /**
     * Dehydrated galaxy list for game selection UI.
     * Returns minimal data optimized for fast loading.
     * Cached for 60 seconds.
     */
    public function list(): JsonResponse
    {
        $galaxies = cache()->remember('galaxies:list', 60, function () {
            return Galaxy::query()
                ->select(['uuid', 'name', 'width', 'height', 'status', 'game_mode', 'size_tier', 'created_at'])
                ->withCount(['players as player_count' => fn ($q) => $q->where('status', 'active')])
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn ($g) => [
                    'uuid' => $g->uuid,
                    'name' => $g->name,
                    'size' => $g->size_tier?->value ?? $this->inferSize($g->width),
                    'players' => $g->player_count,
                    'mode' => $g->game_mode ?? 'multiplayer',
                ]);
        });

        return response()->json([
            'galaxies' => $galaxies,
            'cached_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Infer galaxy size from dimensions for legacy galaxies.
     */
    private function inferSize(int $width): string
    {
        return match (true) {
            $width <= 500 => 'small',
            $width <= 1500 => 'medium',
            default => 'large',
        };
    }

    /**
     * Get galaxy details
     */
    public function show(string $uuid): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $uuid)
            ->with(['players' => function ($query) {
                $query->where('status', 'active')->limit(10);
            }])
            ->withCount(['players as total_players', 'pointsOfInterest as total_systems'])
            ->firstOrFail();

        return $this->success(
            new GalaxyResource($galaxy),
            'Galaxy details retrieved successfully'
        );
    }

    /**
     * Get galaxy-wide statistics
     */
    public function statistics(string $uuid): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $uuid)->firstOrFail();

        // Aggregate statistics
        $stats = [
            'galaxy' => [
                'uuid' => $galaxy->uuid,
                'name' => $galaxy->name,
                'dimensions' => [
                    'width' => $galaxy->width,
                    'height' => $galaxy->height,
                ],
                'total_systems' => $galaxy->pointsOfInterest()->count(),
                'inhabited_systems' => $galaxy->pointsOfInterest()->where('is_inhabited', true)->count(),
            ],
            'players' => [
                'total' => $galaxy->players()->count(),
                'active' => $galaxy->players()->where('status', 'active')->count(),
                'destroyed' => $galaxy->players()->where('status', 'destroyed')->count(),
            ],
            'economy' => [
                'total_credits_in_circulation' => $galaxy->players()->sum('credits'),
                'average_player_credits' => $galaxy->players()->avg('credits'),
                'trading_hubs' => $galaxy->tradingHubs()->count(),
            ],
            'colonies' => [
                'total' => Colony::whereHas('player', function ($q) use ($galaxy) {
                    $q->where('galaxy_id', $galaxy->id);
                })->count(),
                'total_population' => Colony::whereHas('player', function ($q) use ($galaxy) {
                    $q->where('galaxy_id', $galaxy->id);
                })->sum('population'),
                'average_development' => Colony::whereHas('player', function ($q) use ($galaxy) {
                    $q->where('galaxy_id', $galaxy->id);
                })->avg('development_level'),
            ],
            'combat' => [
                'total_pvp_challenges' => \App\Models\PvPChallenge::whereHas('challenger', function ($q) use ($galaxy) {
                    $q->where('galaxy_id', $galaxy->id);
                })->count(),
                'completed_battles' => \App\Models\CombatSession::whereHas('participants', function ($q) use ($galaxy) {
                    $q->whereHas('player', function ($q2) use ($galaxy) {
                        $q2->where('galaxy_id', $galaxy->id);
                    });
                })->where('status', 'completed')->count(),
            ],
            'infrastructure' => [
                'warp_gates' => $galaxy->warpGates()->count(),
                'sectors' => $galaxy->sectors()->count(),
                'pirate_fleets' => \App\Models\WarpLanePirate::whereHas('warpGate', function ($q) use ($galaxy) {
                    $q->where('galaxy_id', $galaxy->id);
                })->count(),
            ],
        ];

        return $this->success($stats, 'Galaxy statistics retrieved successfully');
    }

    /**
     * Get galaxy map data (optimized for rendering)
     */
    public function map(string $uuid, Request $request): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $uuid)->firstOrFail();

        // Get player if authenticated
        $player = $request->user()
            ? Player::where('user_id', $request->user()->id)
                ->where('galaxy_id', $galaxy->id)
                ->first()
            : null;

        // Get revealed systems if player has star charts
        $revealedSystemIds = $player
            ? $player->starCharts()->pluck('points_of_interest.id')->toArray()
            : [];

        // Get POIs based on visibility
        $pointsQuery = $galaxy->pointsOfInterest()
            ->select(['id', 'uuid', 'name', 'type', 'x', 'y', 'is_inhabited']);

        // If player exists and has star charts, only show revealed systems
        // Otherwise show all inhabited systems
        if ($player && count($revealedSystemIds) > 0) {
            $pointsQuery->whereIn('id', $revealedSystemIds);
        } else {
            $pointsQuery->where('is_inhabited', true);
        }

        $points = $pointsQuery->get();

        // Get warp gates
        $warpGates = $galaxy->warpGates()
            ->with(['fromPoi:id,uuid,x,y', 'toPoi:id,uuid,x,y'])
            ->where('is_hidden', false)
            ->get()
            ->map(function ($gate) {
                return [
                    'uuid' => $gate->uuid,
                    'from' => ['x' => $gate->fromPoi->x, 'y' => $gate->fromPoi->y],
                    'to' => ['x' => $gate->toPoi->x, 'y' => $gate->toPoi->y],
                    'is_mirror' => $gate->is_mirror_gate,
                ];
            });

        // Get sectors
        $sectors = $galaxy->sectors()
            ->select(['uuid', 'name', 'x_min', 'x_max', 'y_min', 'y_max', 'danger_level'])
            ->get();

        $mapData = [
            'galaxy' => [
                'uuid' => $galaxy->uuid,
                'name' => $galaxy->name,
                'width' => $galaxy->width,
                'height' => $galaxy->height,
            ],
            'systems' => $points->map(function ($poi) use ($player) {
                return [
                    'uuid' => $poi->uuid,
                    'name' => $poi->name,
                    'type' => $poi->type,
                    'x' => $poi->x,
                    'y' => $poi->y,
                    'is_inhabited' => $poi->is_inhabited,
                    'is_current_location' => $player && $player->current_poi_id === $poi->id,
                ];
            }),
            'warp_gates' => $warpGates,
            'sectors' => $sectors,
            'player_location' => $player ? [
                'x' => $player->currentPoi->x ?? null,
                'y' => $player->currentPoi->y ?? null,
            ] : null,
        ];

        return $this->success($mapData, 'Galaxy map retrieved successfully');
    }

    /**
     * Get sector information
     */
    public function showSector(string $uuid): JsonResponse
    {
        $sector = Sector::where('uuid', $uuid)
            ->with(['galaxy:id,uuid,name'])
            ->firstOrFail();

        // Get POIs in this sector
        $pois = $sector->pointsOfInterest()
            ->select(['uuid', 'name', 'type', 'x', 'y', 'is_inhabited'])
            ->get();

        // Count players in sector
        $playersInSector = Player::whereHas('currentPoi', function ($q) use ($sector) {
            $q->whereBetween('x', [$sector->x_min, $sector->x_max])
                ->whereBetween('y', [$sector->y_min, $sector->y_max]);
        })->where('status', 'active')->count();

        $sectorData = [
            'uuid' => $sector->uuid,
            'name' => $sector->name,
            'galaxy' => [
                'uuid' => $sector->galaxy->uuid,
                'name' => $sector->galaxy->name,
            ],
            'bounds' => [
                'x_min' => $sector->x_min,
                'x_max' => $sector->x_max,
                'y_min' => $sector->y_min,
                'y_max' => $sector->y_max,
            ],
            'danger_level' => $sector->danger_level,
            'statistics' => [
                'total_systems' => $pois->count(),
                'inhabited_systems' => $pois->where('is_inhabited', true)->count(),
                'active_players' => $playersInSector,
                'pirate_fleets' => \App\Models\WarpLanePirate::whereHas('warpGate.fromPoi', function ($q) use ($sector) {
                    $q->whereBetween('x', [$sector->x_min, $sector->x_max])
                        ->whereBetween('y', [$sector->y_min, $sector->y_max]);
                })->count(),
            ],
            'systems' => $pois,
        ];

        return $this->success($sectorData, 'Sector information retrieved successfully');
    }
}

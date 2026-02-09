<?php

namespace App\Http\Controllers\Api;

use App\Enums\Galaxy\GalaxyStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Http\Controllers\Api\Builders\SystemNameGenerator;
use App\Http\Resources\GalaxyDehydratedResource;
use App\Http\Resources\GalaxyResource;
use App\Http\Resources\PlayerResource;
use App\Models\Colony;
use App\Models\CombatSession;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PvPChallenge;
use App\Models\Sector;
use App\Models\Ship;
use App\Models\WarpLanePirate;
use App\Services\LaneKnowledgeService;
use App\Services\StarChartService;
use App\Services\SystemScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GalaxyController extends BaseApiController
{
    /**
     * Default max players when galaxy doesn't specify a limit.
     */
    private const DEFAULT_MAX_PLAYERS = 100;

    /**
     * List galaxies for authenticated user.
     *
     * Returns two sections:
     * 1. my_games: Galaxies the user is part of (has player) OR owns, ordered by last access (descending)
     * 2. open_games: Active galaxies open for registration (no owner or multiplayer/mixed), ordered by player count (ascending)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('Authentication required', 401);
        }

        // Get IDs of galaxies where user has a player (any status)
        $playerGalaxyIds = Player::where('user_id', $user->id)
            ->pluck('galaxy_id')
            ->toArray();

        // Get IDs of galaxies where user is owner
        $ownedGalaxyIds = Galaxy::where('owner_user_id', $user->id)
            ->pluck('id')
            ->toArray();

        // Combine both sets of galaxy IDs
        $myGalaxyIds = collect(array_unique(array_merge($playerGalaxyIds, $ownedGalaxyIds)));

        // Get user's galaxies with player's last_accessed_at for ordering
        // Exclude mirror universes - they are accessed via gates, not direct selection
        $myGames = Galaxy::query()
            ->excludeMirrors()
            ->select(['galaxies.id', 'galaxies.uuid', 'galaxies.name', 'galaxies.width', 'galaxies.height', 'galaxies.status', 'galaxies.game_mode', 'galaxies.size_tier', 'galaxies.max_players'])
            ->whereIn('galaxies.id', $myGalaxyIds)
            ->leftJoin('players', function ($join) use ($user) {
                $join->on('galaxies.id', '=', 'players.galaxy_id')
                    ->where('players.user_id', '=', $user->id);
            })
            ->withCount(['players as player_count' => fn ($q) => $q->where('status', 'active')])
            ->orderByRaw('COALESCE(players.last_accessed_at, galaxies.created_at) DESC')
            ->get();

        // Get open galaxies: active, under player cap, not already joined, and either:
        // - owner_user_id IS NULL (public game), or
        // - game_mode is multiplayer/mixed
        // Exclude mirror universes - they are accessed via gates, not direct selection
        $openGames = Galaxy::query()
            ->excludeMirrors()
            ->select(['id', 'uuid', 'name', 'width', 'height', 'status', 'game_mode', 'size_tier', 'max_players'])
            ->where('status', GalaxyStatus::ACTIVE)
            ->whereNotIn('id', $myGalaxyIds)
            ->where(function ($query) {
                $query->whereNull('owner_user_id')
                    ->orWhere('game_mode', 'multiplayer')
                    ->orWhere('game_mode', 'mixed');
            })
            ->withCount(['players as player_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('player_count', 'asc')
            ->get()
            ->filter(function ($galaxy) {
                $maxPlayers = $galaxy->max_players ?? self::DEFAULT_MAX_PLAYERS;

                return $galaxy->player_count < $maxPlayers;
            })
            ->values();

        return $this->success([
            'my_games' => GalaxyDehydratedResource::collection($myGames),
            'open_games' => GalaxyDehydratedResource::collection($openGames),
        ], 'Galaxies retrieved successfully');
    }

    /**
     * Cached galaxy list for game selection UI.
     *
     * Returns two sections:
     * 1. my_games: Galaxies the user is part of (has player) OR owns, ordered by last access (descending)
     * 2. open_games: Active galaxies open for registration (cached), ordered by player count (ascending)
     */
    public function list(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('Authentication required', 401);
        }

        // Get IDs of galaxies where user has a player (any status)
        $playerGalaxyIds = Player::where('user_id', $user->id)
            ->pluck('galaxy_id')
            ->toArray();

        // Get IDs of galaxies where user is owner
        $ownedGalaxyIds = Galaxy::where('owner_user_id', $user->id)
            ->pluck('id')
            ->toArray();

        // Combine both sets of galaxy IDs
        $myGalaxyIds = collect(array_unique(array_merge($playerGalaxyIds, $ownedGalaxyIds)));

        // Get user's galaxies with player's last_accessed_at for ordering
        // Exclude mirror universes - they are accessed via gates, not direct selection
        $myGames = Galaxy::query()
            ->excludeMirrors()
            ->select(['galaxies.id', 'galaxies.uuid', 'galaxies.name', 'galaxies.width', 'galaxies.height', 'galaxies.status', 'galaxies.game_mode', 'galaxies.size_tier', 'galaxies.max_players'])
            ->whereIn('galaxies.id', $myGalaxyIds)
            ->leftJoin('players', function ($join) use ($user) {
                $join->on('galaxies.id', '=', 'players.galaxy_id')
                    ->where('players.user_id', '=', $user->id);
            })
            ->withCount(['players as player_count' => fn ($q) => $q->where('status', 'active')])
            ->orderByRaw('COALESCE(players.last_accessed_at, galaxies.created_at) DESC')
            ->get();

        // Open games are cached (5 minutes) - includes games with no owner or multiplayer/mixed mode
        // Exclude mirror universes - they are accessed via gates, not direct selection
        $openGames = cache()->remember('galaxies:open_games', 300, function () {
            return Galaxy::query()
                ->excludeMirrors()
                ->select(['id', 'uuid', 'name', 'width', 'height', 'status', 'game_mode', 'size_tier', 'max_players', 'owner_user_id'])
                ->where('status', GalaxyStatus::ACTIVE)
                ->where(function ($query) {
                    $query->whereNull('owner_user_id')
                        ->orWhere('game_mode', 'multiplayer')
                        ->orWhere('game_mode', 'mixed');
                })
                ->withCount(['players as player_count' => fn ($q) => $q->where('status', 'active')])
                ->orderBy('player_count', 'asc')
                ->get();
        });

        // Filter out user's galaxies and those at capacity from cached list
        $filteredOpenGames = $openGames->filter(function ($galaxy) use ($myGalaxyIds) {
            $maxPlayers = $galaxy->max_players ?? self::DEFAULT_MAX_PLAYERS;

            return ! $myGalaxyIds->contains($galaxy->id)
                && $galaxy->player_count < $maxPlayers;
        })->values();

        return $this->success([
            'my_games' => GalaxyDehydratedResource::collection($myGames),
            'open_games' => GalaxyDehydratedResource::collection($filteredOpenGames),
        ], 'Galaxies retrieved successfully');
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
            ->select(['id', 'uuid', 'name', 'type', 'x', 'y', 'is_inhabited', 'region']);

        // If player exists and has star charts, only show revealed systems
        // Otherwise show all inhabited systems
        if ($player && count($revealedSystemIds) > 0) {
            $pointsQuery->whereIn('id', $revealedSystemIds);
        } else {
            $pointsQuery->where('is_inhabited', true);
        }

        $points = $pointsQuery->get();

        // Get scan levels for all POIs if player exists
        $scanLevels = [];
        if ($player) {
            $scanService = app(SystemScanService::class);
            $scanLevels = $scanService->getBulkScanLevels($player, $points->pluck('id')->toArray());
        }

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
            'systems' => $points->map(function ($poi) use ($player, $scanLevels) {
                $scanLevel = $scanLevels[$poi->id] ?? $poi->getBaselineScanLevel();
                $scanLevelEnum = \App\Enums\Exploration\ScanLevel::fromSensorLevel($scanLevel);

                return [
                    'uuid' => $poi->uuid,
                    'name' => $poi->name,
                    'type' => $poi->type,
                    'x' => $poi->x,
                    'y' => $poi->y,
                    'is_inhabited' => $poi->is_inhabited,
                    'is_current_location' => $player && $player->current_poi_id === $poi->id,
                    'scan' => [
                        'level' => $scanLevel,
                        'label' => $scanLevelEnum->label(),
                        'color' => $scanLevelEnum->color(),
                        'opacity' => $scanLevelEnum->opacity(),
                    ],
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
     * Get galaxy details (full hydrated resource).
     */
    public function show(string $uuid): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $uuid)
            ->with([
                'players' => fn ($q) => $q->where('status', 'active')->limit(20),
                'owner:id,name',
            ])
            ->withCount([
                'players as total_players',
                'players as active_player_count' => fn ($q) => $q->where('status', 'active'),
                'pointsOfInterest as total_systems',
                'sectors',
                'warpGates',
                'tradingHubs',
            ])
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
                'total_pvp_challenges' => PvPChallenge::whereHas('challenger', function ($q) use ($galaxy) {
                    $q->where('galaxy_id', $galaxy->id);
                })->count(),
                'completed_battles' => CombatSession::whereHas('participants', function ($q) use ($galaxy) {
                    $q->whereHas('player', function ($q2) use ($galaxy) {
                        $q2->where('galaxy_id', $galaxy->id);
                    });
                })->where('status', 'completed')->count(),
            ],
            'infrastructure' => [
                'warp_gates' => $galaxy->warpGates()->count(),
                'sectors' => $galaxy->sectors()->count(),
                'pirate_fleets' => WarpLanePirate::whereHas('warpGate', function ($q) use ($galaxy) {
                    $q->where('galaxy_id', $galaxy->id);
                })->count(),
            ],
        ];

        return $this->success($stats, 'Galaxy statistics retrieved successfully');
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
                'pirate_fleets' => WarpLanePirate::whereHas('warpGate.fromPoi', function ($q) use ($sector) {
                    $q->whereBetween('x', [$sector->x_min, $sector->x_max])
                        ->whereBetween('y', [$sector->y_min, $sector->y_max]);
                })->count(),
            ],
            'systems' => $pois,
        ];

        return $this->success($sectorData, 'Sector information retrieved successfully');
    }

    /**
     * Get the authenticated user's player in this galaxy.
     *
     * GET /api/galaxies/{uuid}/my-player
     *
     * Returns the player if exists, 404 otherwise.
     * Use this to check if user has a player in a specific galaxy.
     */
    public function getMyPlayer(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Authentication required', 'UNAUTHENTICATED', null, 401);
        }

        $galaxy = Galaxy::where('uuid', $uuid)->first();
        if (! $galaxy) {
            return $this->notFound('Galaxy not found');
        }

        $player = Player::where('user_id', $user->id)
            ->where('galaxy_id', $galaxy->id)
            ->with(['galaxy', 'currentLocation.sector', 'activeShip.ship'])
            ->first();

        if (! $player) {
            return $this->error(
                'You do not have a player in this galaxy',
                'NO_PLAYER_IN_GALAXY',
                ['galaxy_uuid' => $uuid],
                404
            );
        }

        $currentSector = $player->currentLocation?->sector;
        $totalSectors = $galaxy->sectors()->count();

        return $this->success([
            'player' => new PlayerResource($player),
            'sector' => $currentSector ? [
                'uuid' => $currentSector->uuid,
                'name' => $currentSector->name,
                'grid' => ['x' => $currentSector->grid_x, 'y' => $currentSector->grid_y],
            ] : null,
            'total_sectors' => $totalSectors,
        ], 'Player found');
    }

    /**
     * Join a galaxy - get existing player or create new one.
     *
     * POST /api/galaxies/{uuid}/join
     *
     * This is an idempotent operation:
     * - If user already has a player in this galaxy, returns it
     * - If not, creates a new player with the provided call_sign
     *
     * Request body (only required if creating):
     * - call_sign: string (required for new players)
     */
    public function join(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Authentication required', 'UNAUTHENTICATED', null, 401);
        }

        $galaxy = Galaxy::where('uuid', $uuid)->first();
        if (! $galaxy) {
            return $this->notFound('Galaxy not found');
        }

        // Check if user already has a player in this galaxy
        $existingPlayer = Player::where('user_id', $user->id)
            ->where('galaxy_id', $galaxy->id)
            ->with(['galaxy', 'currentLocation.sector', 'activeShip.ship'])
            ->first();

        if ($existingPlayer) {
            $currentSector = $existingPlayer->currentLocation?->sector;
            $totalSectors = $galaxy->sectors()->count();

            return $this->success([
                'player' => new PlayerResource($existingPlayer),
                'created' => false,
                'sector' => $currentSector ? [
                    'uuid' => $currentSector->uuid,
                    'name' => $currentSector->name,
                    'grid' => ['x' => $currentSector->grid_x, 'y' => $currentSector->grid_y],
                ] : null,
                'total_sectors' => $totalSectors,
            ], 'Player already exists in this galaxy');
        }

        // Validate request for new player creation
        try {
            $validated = $request->validate([
                'call_sign' => ['required', 'string', 'max:50'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        }

        // Check if galaxy is accepting new players
        if ($galaxy->status !== GalaxyStatus::ACTIVE) {
            return $this->error(
                'Galaxy is not accepting new players',
                'GALAXY_NOT_ACTIVE',
                ['status' => $galaxy->status->value],
                400
            );
        }

        // Check player cap
        $maxPlayers = $galaxy->max_players ?? self::DEFAULT_MAX_PLAYERS;
        $currentPlayerCount = $galaxy->players()->where('status', 'active')->count();
        if ($currentPlayerCount >= $maxPlayers) {
            return $this->error(
                'Galaxy has reached maximum player capacity',
                'GALAXY_FULL',
                ['max_players' => $maxPlayers, 'current_players' => $currentPlayerCount],
                400
            );
        }

        // Check game mode restrictions
        if ($galaxy->game_mode === 'single_player' && $galaxy->owner_user_id !== $user->id) {
            return $this->error(
                'This is a single-player galaxy',
                'SINGLE_PLAYER_GALAXY',
                null,
                403
            );
        }

        // Check if call sign is unique within this galaxy
        $existingCallSign = Player::where('galaxy_id', $galaxy->id)
            ->where('call_sign', $validated['call_sign'])
            ->first();

        if ($existingCallSign) {
            return $this->error(
                'Call sign already exists in this galaxy',
                'DUPLICATE_CALL_SIGN',
                null,
                422
            );
        }

        DB::beginTransaction();
        try {
            // Find a random inhabited starting location
            $startingLocation = $galaxy->pointsOfInterest()
                ->where('type', PointOfInterestType::STAR)
                ->where('is_inhabited', true)
                ->inRandomOrder()
                ->first();

            if (! $startingLocation) {
                DB::rollBack();

                return $this->error(
                    'No suitable starting location found in galaxy',
                    'NO_STARTING_LOCATION'
                );
            }

            // Get starting credits from config
            $startingCredits = config('game_config.ships.starting_credits', 10000);

            // Create player
            $player = Player::create([
                'user_id' => $user->id,
                'galaxy_id' => $galaxy->id,
                'call_sign' => $validated['call_sign'],
                'credits' => $startingCredits,
                'experience' => 0,
                'level' => 1,
                'current_poi_id' => $startingLocation->id,
                'status' => 'active',
            ]);

            // Give player a starting ship (Starter class - Sparrow Light Freighter)
            $starterShip = Ship::where('class', 'starter')->first();
            if (! $starterShip) {
                // TODO: TECH DEBT - Remove runtime seeder fallback
                //       Issue: Running seeders in request handlers is an anti-pattern
                //       Current behavior: Seeds ships if missing (unsafe for production)
                //       Desired fix: Return 500 error if starter ship missing,
                //                    enforce seeding during deployment
                //       Priority: Medium (works but risky)
                (new \Database\Seeders\ShipTypesSeeder)->run();
                $starterShip = Ship::where('class', 'starter')->first();
            }

            if ($starterShip) {
                // Get ship attributes for starting stats
                $attrs = $starterShip->attributes ?? [];

                PlayerShip::create([
                    'player_id' => $player->id,
                    'ship_id' => $starterShip->id,
                    'name' => "{$player->call_sign}'s Sparrow",
                    'current_fuel' => $attrs['max_fuel'] ?? 100,
                    'max_fuel' => $attrs['max_fuel'] ?? 100,
                    'hull' => $starterShip->hull_strength ?? 80,
                    'max_hull' => $starterShip->hull_strength ?? 80,
                    'weapons' => $attrs['starting_weapons'] ?? 15,
                    'cargo_hold' => $starterShip->cargo_capacity ?? 50,
                    'sensors' => $attrs['starting_sensors'] ?? 1,
                    'warp_drive' => $attrs['starting_warp_drive'] ?? 1,
                    'shields' => $starterShip->shield_strength ?? 40,
                    'max_shields' => $starterShip->shield_strength ?? 40,
                    'is_active' => true,
                    'status' => 'operational',
                ]);
            }

            // === SPAWN DISCOVERY ===

            // Ensure spawn system has a name
            SystemNameGenerator::ensureName($startingLocation);

            // Create minimum scan for spawn location (level 1)
            $scanService = app(SystemScanService::class);
            $scanService->ensureMinimumScan($player, $startingLocation, 1);

            // Discover outgoing lanes from spawn
            $laneKnowledgeService = app(LaneKnowledgeService::class);
            $laneKnowledgeService->discoverOutgoingGates($player, $startingLocation->id, 'spawn');

            // Grant spawn location star chart (free)
            DB::table('player_star_charts')->insert([
                'player_id' => $player->id,
                'revealed_poi_id' => $startingLocation->id,
                'purchased_from_poi_id' => $startingLocation->id,
                'price_paid' => 0.00,
                'purchased_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Grant starting charts for nearby inhabited systems
            $starChartService = app(StarChartService::class);
            $starChartService->grantStartingCharts($player);

            DB::commit();

            $player->load(['galaxy', 'currentLocation.sector', 'activeShip.ship']);

            $currentSector = $player->currentLocation?->sector;
            $totalSectors = $galaxy->sectors()->count();

            return $this->success([
                'player' => new PlayerResource($player),
                'created' => true,
                'sector' => $currentSector ? [
                    'uuid' => $currentSector->uuid,
                    'name' => $currentSector->name,
                    'grid' => ['x' => $currentSector->grid_x, 'y' => $currentSector->grid_y],
                ] : null,
                'total_sectors' => $totalSectors,
            ], 'Successfully joined galaxy', 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error(
                'Failed to join galaxy: '.$e->getMessage(),
                'JOIN_FAILED'
            );
        }
    }
}

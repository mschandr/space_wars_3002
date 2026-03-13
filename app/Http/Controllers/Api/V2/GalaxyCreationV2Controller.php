<?php

namespace App\Http\Controllers\Api\V2;

use App\Enums\Galaxy\GalaxySizeTier;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\Galaxy;
use App\Services\GalaxyGenerationV2Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Galaxy Creation V2 API Controller
 *
 * Provides discrete, versioned endpoints for galaxy generation using the V2 service.
 * Supports resumable, phase-based galaxy creation with fine-grained control.
 *
 * Versioned endpoints:
 * - POST /api/v2/galaxies/create - Create empty galaxy structure
 * - POST /api/v2/galaxies/{uuid}/populate - Populate specific phase
 */
class GalaxyCreationV2Controller extends BaseApiController
{
    public function __construct(
        private GalaxyGenerationV2Service $service,
    ) {}

    /**
     * Create empty galaxy structure (Phase 1).
     *
     * POST /api/v2/galaxies/create
     *
     * Only admins can specify custom size tiers. Default is 'large'.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'tier' => [
                'sometimes',
                Rule::in(['small', 'medium', 'large', 'massive']),
            ],
        ]);

        try {
            // Admin-only: size tier selection
            $tier = $request->input('tier', 'large');
            if ($tier !== 'large' && !auth()->user()?->is_admin) {
                return $this->error(
                    'Only administrators can specify custom galaxy sizes',
                    'UNAUTHORIZED_SIZE_TIER',
                    ['allowed_tier' => 'large'],
                    403
                );
            }

            $sizeTier = GalaxySizeTier::from($tier);
            $name = $request->input('name', 'Galaxy-' . now()->format('YmdHis'));

            // Phase 1: Create empty galaxy
            $galaxy = $this->service->createGalaxyOnly($sizeTier, $name);

            return $this->success([
                'galaxy' => [
                    'id' => $galaxy->id,
                    'name' => $galaxy->name,
                    'width' => $galaxy->width,
                    'height' => $galaxy->height,
                    'status' => $galaxy->status->value,
                    'tier' => $tier,
                    'next_phase' => 'stars',
                ],
                'progress' => [
                    'phase' => 1,
                    'total_phases' => 6,
                    'description' => 'Galaxy structure created. Next: populate stars.',
                ],
                'next_steps' => [
                    'endpoint' => "POST /api/v2/galaxies/{$galaxy->id}/populate",
                    'body' => [
                        'phase' => 'stars',
                    ],
                ],
            ], 'Galaxy structure created', 201);
        } catch (\ValueError $e) {
            return $this->error(
                'Invalid galaxy tier',
                'INVALID_TIER',
                ['valid_tiers' => ['small', 'medium', 'large', 'massive']],
                422
            );
        } catch (\Exception $e) {
            return $this->error(
                "Failed to create galaxy: {$e->getMessage()}",
                'CREATION_FAILED',
                null,
                500
            );
        }
    }

    /**
     * Populate galaxy with specific phase.
     *
     * POST /api/v2/galaxies/{uuid}/populate
     *
     * Phases: stars, gates, player, supernode, systems
     *
     * @param  string  $galaxyId
     * @param  Request  $request
     * @return JsonResponse
     */
    public function populate(string $galaxyId, Request $request): JsonResponse
    {
        $request->validate([
            'phase' => [
                'required',
                Rule::in(['stars', 'gates', 'player', 'supernode', 'systems', 'all']),
            ],
            'user_id' => 'sometimes|uuid|exists:users,id',
        ]);

        try {
            $galaxy = Galaxy::findOrFail($galaxyId);
            $phase = $request->input('phase');
            $userId = $request->input('user_id');

            // Execute requested phase(s)
            if ($phase === 'all') {
                return $this->populateAll($galaxy, $userId);
            }

            return $this->populatePhase($galaxy, $phase, $userId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error(
                'Galaxy not found',
                'GALAXY_NOT_FOUND',
                null,
                404
            );
        } catch (\Exception $e) {
            return $this->error(
                "Population failed: {$e->getMessage()}",
                'POPULATION_FAILED',
                null,
                500
            );
        }
    }

    /**
     * Get galaxy creation status.
     *
     * GET /api/v2/galaxies/{uuid}/status
     *
     * @param  string  $galaxyId
     * @return JsonResponse
     */
    public function status(string $galaxyId): JsonResponse
    {
        try {
            $galaxy = Galaxy::findOrFail($galaxyId);

            $poiCount = $galaxy->pointsOfInterest()->count();
            $gateCount = $galaxy->warpGates()->count();
            $playerCount = $galaxy->players()->count();
            $sectorCount = $galaxy->sectors()->count();

            return $this->success([
                'galaxy' => [
                    'id' => $galaxy->id,
                    'name' => $galaxy->name,
                    'status' => $galaxy->status->value,
                    'dimensions' => [
                        'width' => $galaxy->width,
                        'height' => $galaxy->height,
                    ],
                ],
                'population' => [
                    'points_of_interest' => $poiCount,
                    'warp_gates' => $gateCount,
                    'sectors' => $sectorCount,
                    'players' => $playerCount,
                ],
                'progress' => [
                    'structure_created' => $sectorCount > 0,
                    'stars_populated' => $poiCount > 0,
                    'gates_created' => $gateCount > 0,
                    'players_created' => $playerCount > 0,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error(
                'Galaxy not found',
                'GALAXY_NOT_FOUND',
                null,
                404
            );
        }
    }

    /**
     * Get size tiers information.
     *
     * GET /api/v2/galaxies/tiers
     *
     * @return JsonResponse
     */
    public function sizeTiers(): JsonResponse
    {
        $tiers = [];
        foreach (GalaxySizeTier::cases() as $tier) {
            $tiers[] = [
                'name' => $tier->value,
                'width' => $tier->getWidth(),
                'height' => $tier->getHeight(),
                'core_stars' => $tier->getCoreStars(),
                'outer_stars' => $tier->getOuterStars(),
                'total_stars' => $tier->getCoreStars() + $tier->getOuterStars(),
                'estimated_time_seconds' => match ($tier->value) {
                    'small' => 5,
                    'medium' => 10,
                    'large' => 15,
                    'massive' => 25,
                },
            ];
        }

        return $this->success([
            'default_tier' => 'large',
            'admin_only_custom_tiers' => true,
            'tiers' => $tiers,
        ]);
    }

    /**
     * Populate single phase.
     */
    private function populatePhase(Galaxy $galaxy, string $phase, ?string $userId): JsonResponse
    {
        $startTime = microtime(true);

        $result = match ($phase) {
            'stars' => [
                'count' => $this->service->createStars($galaxy),
                'description' => 'Stars generated (core + outer regions)',
            ],
            'gates' => [
                'count' => $this->service->createWarpLanes($galaxy),
                'description' => 'Warp gates created with distance-based distribution',
            ],
            'player' => $this->createPlayerPhase($galaxy, $userId),
            'supernode' => $this->createSupernodePhase($galaxy),
            'systems' => [
                'count' => $this->service->populateConnectedSystems($galaxy),
                'description' => 'Connected systems populated',
            ],
        };

        $elapsed = round((microtime(true) - $startTime) * 1000);

        $nextPhase = match ($phase) {
            'stars' => 'gates',
            'gates' => 'player',
            'player' => 'supernode',
            'supernode' => 'systems',
            'systems' => null,
        };

        return $this->success([
            'phase' => $phase,
            'result' => $result,
            'timing' => [
                'elapsed_ms' => $elapsed,
                'estimated_total_ms' => 15000,
            ],
            'progress' => [
                'current_phase' => $phase,
                'next_phase' => $nextPhase,
            ],
            'next_step' => $nextPhase ? [
                'endpoint' => "POST /api/v2/galaxies/{$galaxy->id}/populate",
                'body' => ['phase' => $nextPhase],
            ] : null,
        ]);
    }

    /**
     * Populate all phases sequentially.
     */
    private function populateAll(Galaxy $galaxy, ?string $userId): JsonResponse
    {
        $startTime = microtime(true);
        $results = [];

        // Phase 2: Stars
        $results['stars'] = $this->service->createStars($galaxy);

        // Phase 3: Warp lanes
        $results['gates'] = $this->service->createWarpLanes($galaxy);

        // Phase 4-5: Player + Supernode (if user provided)
        if ($userId) {
            $user = \App\Models\User::findOrFail($userId);
            $player = $this->service->createPlayer($galaxy, $user);
            $results['player'] = 1;

            $supernode = $this->service->createSupernodeForPlayer($player);
            $results['supernode'] = [
                'location' => $supernode->name,
                'coordinates' => [
                    'x' => $supernode->coordinate_x,
                    'y' => $supernode->coordinate_y,
                ],
            ];
        }

        // Phase 6: System population
        $results['systems'] = $this->service->populateConnectedSystems($galaxy);

        $elapsed = round((microtime(true) - $startTime) * 1000);

        return $this->success([
            'galaxy' => [
                'id' => $galaxy->id,
                'name' => $galaxy->name,
            ],
            'results' => $results,
            'timing' => [
                'total_ms' => $elapsed,
                'estimated_ms' => 15000,
            ],
            'status' => 'Galaxy population complete',
        ]);
    }

    /**
     * Create player phase.
     */
    private function createPlayerPhase(Galaxy $galaxy, ?string $userId): array
    {
        if (!$userId) {
            throw new \Exception('user_id required for player creation');
        }

        $user = \App\Models\User::findOrFail($userId);
        $player = $this->service->createPlayer($galaxy, $user);

        return [
            'player_id' => $player->id,
            'user_email' => $user->email,
            'starting_credits' => $player->credits,
            'description' => 'Player created in galaxy',
        ];
    }

    /**
     * Create supernode phase.
     */
    private function createSupernodePhase(Galaxy $galaxy): array
    {
        $player = $galaxy->players()->first();
        if (!$player) {
            throw new \Exception('No player in galaxy. Run player phase first.');
        }

        $supernode = $this->service->createSupernodeForPlayer($player);

        return [
            'name' => $supernode->name,
            'coordinates' => [
                'x' => $supernode->coordinate_x,
                'y' => $supernode->coordinate_y,
            ],
            'type' => $supernode->poi_type,
            'description' => 'Player starting supernode created',
        ];
    }
}

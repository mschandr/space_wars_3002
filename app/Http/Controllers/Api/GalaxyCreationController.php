<?php

namespace App\Http\Controllers\Api;

use App\Enums\Galaxy\GalaxySizeTier;
use App\Http\Requests\Api\AddNpcsRequest;
use App\Http\Requests\Api\CreateGalaxyRequest;
use App\Models\Galaxy;
use App\Models\Npc;
use App\Services\GalaxyCreationService;
use App\Services\NpcGenerationService;
use App\Services\TieredGalaxyCreationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GalaxyCreationController extends BaseApiController
{
    private GalaxyCreationService $galaxyCreationService;

    private TieredGalaxyCreationService $tieredGalaxyCreationService;

    private NpcGenerationService $npcGenerationService;

    /**
     * Initialize the controller with required services for galaxy and NPC management.
     *
     * @param GalaxyCreationService $galaxyCreationService Service responsible for creating and modifying galaxies.
     * @param TieredGalaxyCreationService $tieredGalaxyCreationService Service responsible for creating tiered galaxies.
     * @param NpcGenerationService $npcGenerationService Service responsible for NPC generation and statistics.
     */
    public function __construct(
        GalaxyCreationService $galaxyCreationService,
        TieredGalaxyCreationService $tieredGalaxyCreationService,
        NpcGenerationService $npcGenerationService
    ) {
        $this->galaxyCreationService = $galaxyCreationService;
        $this->tieredGalaxyCreationService = $tieredGalaxyCreationService;
        $this->npcGenerationService = $npcGenerationService;
    }

    /**
     * Create a new playable galaxy with stars, POIs, warp network, trading hubs, pirates, an optional mirror universe, and NPCs.
     *
     * Assigns the requesting user as owner for single-player or mixed game modes. Honors the `async` option and will auto-enable asynchronous creation when galaxy complexity (width * height * stars) exceeds a large threshold. Returns 201 on synchronous creation success or 202 when creation is started for background processing. On failure returns error responses with codes `GALAXY_CREATION_FAILED` or `GALAXY_CREATION_ERROR`.
     *
     * @param CreateGalaxyRequest $request The validated request containing galaxy options.
     * @return \Illuminate\Http\JsonResponse JSON response containing creation result or error information.
     */
    public function create(CreateGalaxyRequest $request): JsonResponse
    {
        // Galaxy creation can take a while - extend execution time to 2 minutes
        set_time_limit(120);

        try {
            $options = $request->validatedWithDefaults();

            // Add owner user ID for single player galaxies
            if (in_array($options['game_mode'], ['single_player', 'mixed'])) {
                $options['owner_user_id'] = $request->user()->id;
            }

            // Use async method if requested (recommended for large galaxies)
            $useAsync = $options['async'] ?? false;

            // Auto-enable async for large galaxies (width*height*stars > threshold)
            $complexity = $options['width'] * $options['height'] * $options['stars'];
            $asyncThreshold = 100_000_000; // ~500x500x400 or equivalent
            if ($complexity > $asyncThreshold) {
                $useAsync = true;
            }

            if ($useAsync) {
                $result = $this->galaxyCreationService->createGalaxyAsync($options);

                return $this->success(
                    $result,
                    'Galaxy creation started. Heavy operations processing in background.',
                    202 // Accepted - processing will continue async
                );
            }

            $result = $this->galaxyCreationService->createGalaxy($options);

            return $this->success(
                $result,
                'Galaxy created successfully',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->error(
                $e->getMessage(),
                'GALAXY_CREATION_FAILED',
                null,
                500
            );
        } catch (\Exception $e) {
            return $this->error(
                'An unexpected error occurred during galaxy creation: '.$e->getMessage(),
                'GALAXY_CREATION_ERROR',
                null,
                500
            );
        }
    }

    /**
     * Create a tiered galaxy composed of a civilized core and a frontier outer region.
     *
     * The request must include a `size_tier`. For `single_player` or `mixed` game modes the
     * requesting user will be set as owner. The operation may run asynchronously when the
     * `async` option is true or automatically for very large size tiers.
     *
     * @param CreateGalaxyRequest $request Validated creation options (including `size_tier` and optional `async`).
     * @return JsonResponse JSON response containing the creation result or an error payload;
     *                     returns 201 when created synchronously, 202 when queued for background processing,
     *                     and 4xx/5xx on validation or server errors.
     */
    public function createTiered(CreateGalaxyRequest $request): JsonResponse
    {
        set_time_limit(600);  // 10 minutes for large galaxies

        try {
            $options = $request->validatedWithDefaults();
            $sizeTier = $request->getSizeTier();

            if (! $sizeTier) {
                return $this->error(
                    'size_tier is required for tiered galaxy creation',
                    'MISSING_SIZE_TIER',
                    ['valid_tiers' => GalaxySizeTier::toOptionsArray()],
                    422
                );
            }

            // Add owner user ID for single player galaxies
            if (in_array($options['game_mode'], ['single_player', 'mixed'])) {
                $options['owner_user_id'] = $request->user()->id;
            }

            // Use async method if requested
            $useAsync = $options['async'] ?? false;

            // Auto-enable async for large galaxies
            if ($sizeTier === GalaxySizeTier::LARGE) {
                $useAsync = true;
            }

            if ($useAsync) {
                $result = $this->tieredGalaxyCreationService->createTieredGalaxyAsync($sizeTier, $options);

                return $this->success(
                    $result,
                    'Tiered galaxy creation started. Heavy operations processing in background.',
                    202
                );
            }

            $result = $this->tieredGalaxyCreationService->createTieredGalaxy($sizeTier, $options);

            return $this->success(
                $result,
                'Tiered galaxy created successfully',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->error(
                $e->getMessage(),
                'TIERED_GALAXY_CREATION_FAILED',
                null,
                500
            );
        } catch (\Exception $e) {
            return $this->error(
                'An unexpected error occurred during tiered galaxy creation: '.$e->getMessage(),
                'TIERED_GALAXY_CREATION_ERROR',
                null,
                500
            );
        }
    }

    /**
         * Get the current creation progress and status for a galaxy identified by UUID.
         *
         * @param string $uuid The galaxy UUID.
         * @return JsonResponse JSON object with:
         *  - galaxy_id: integer galaxy primary key,
         *  - galaxy_uuid: string galaxy UUID,
         *  - galaxy_name: string|null galaxy name,
         *  - status: string current generation status,
         *  - size_tier: string|null size tier name,
         *  - current_progress: int current progress percentage (0-100),
         *  - is_complete: bool `true` if generation is complete, `false` otherwise,
         *  - generation_started_at: string|null ISO 8601 start timestamp,
         *  - generation_completed_at: string|null ISO 8601 completion timestamp,
         *  - steps: array progress steps/details.
         */
    public function creationStatus(string $uuid): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $uuid)->first();

        if (! $galaxy) {
            return $this->notFound('Galaxy not found');
        }

        $progress = $galaxy->progress_status ?? [];
        $currentProgress = $galaxy->getCurrentProgress();

        return $this->success([
            'galaxy_id' => $galaxy->id,
            'galaxy_uuid' => $galaxy->uuid,
            'galaxy_name' => $galaxy->name,
            'status' => $galaxy->status->value ?? $galaxy->status,
            'size_tier' => $galaxy->size_tier?->value,
            'current_progress' => $currentProgress,
            'is_complete' => $currentProgress >= 100 || $galaxy->status->isPlayable(),
            'generation_started_at' => $galaxy->generation_started_at?->toIso8601String(),
            'generation_completed_at' => $galaxy->generation_completed_at?->toIso8601String(),
            'steps' => $progress,
        ]);
    }

    /**
     * Provides available galaxy size tiers and their configuration options.
     *
     * @return \Illuminate\Http\JsonResponse JSON response with a `tiers` key containing an array of size tier option objects.
     */
    public function getSizeTiers(): JsonResponse
    {
        return $this->success([
            'tiers' => GalaxySizeTier::toOptionsArray(),
        ]);
    }

    /**
     * Add NPCs to a galaxy that permits NPCs.
     *
     * Accepts request inputs `count` (number of NPCs), `difficulty` (e.g., "easy", "medium", "hard"), and an archetype distribution.
     *
     * @param AddNpcsRequest $request Request containing `count`, optional `difficulty`, and archetype distribution.
     * @param string $uuid The UUID of the target galaxy.
     * @return JsonResponse JSON success response with creation details (includes `npcs_created`) on success, or an error response on failure.
     */
    public function addNpcs(AddNpcsRequest $request, string $uuid): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $uuid)->first();

        if (! $galaxy) {
            return $this->notFound('Galaxy not found');
        }

        // Check if galaxy allows NPCs
        if (! $galaxy->allowsNpcs()) {
            return $this->error(
                'This galaxy does not allow NPCs. Game mode must be single_player or mixed.',
                'NPC_NOT_ALLOWED',
                ['game_mode' => $galaxy->game_mode],
                422
            );
        }

        // Check ownership for single_player galaxies
        if ($galaxy->isSinglePlayer() && $galaxy->owner_user_id !== $request->user()->id) {
            return $this->forbidden('You do not own this galaxy');
        }

        try {
            $result = $this->galaxyCreationService->addNpcsToGalaxy(
                $galaxy,
                $request->input('count'),
                $request->input('difficulty', 'medium'),
                $request->getArchetypeDistribution()
            );

            return $this->success(
                $result,
                "Successfully created {$result['npcs_created']} NPCs"
            );
        } catch (\RuntimeException $e) {
            return $this->error(
                $e->getMessage(),
                'NPC_CREATION_FAILED',
                null,
                500
            );
        }
    }

    /**
     * Retrieve a list of NPCs in the specified galaxy.
     *
     * Supports optional query filters: `archetype`, `status`, and `difficulty`. Each filter, if present,
     * restricts the result set to NPCs matching the provided value.
     *
     * @param \Illuminate\Http\Request $request Request containing optional query filters.
     * @param string $uuid UUID of the galaxy to list NPCs for.
     * @return \Illuminate\Http\JsonResponse JSON object with keys:
     *         - `npcs`: array of NPC objects (uuid, call_sign, archetype, difficulty, level, credits, status, current_activity, location, ship),
     *         - `total`: integer count of returned NPCs,
     *         - `statistics`: aggregated NPC statistics for the galaxy.
     */
    public function listNpcs(Request $request, string $uuid): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $uuid)->first();

        if (! $galaxy) {
            return $this->notFound('Galaxy not found');
        }

        $query = $galaxy->npcs()
            ->with(['currentLocation', 'activeShip.ship']);

        // Filter by archetype
        if ($request->has('archetype')) {
            $query->where('archetype', $request->input('archetype'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by difficulty
        if ($request->has('difficulty')) {
            $query->where('difficulty', $request->input('difficulty'));
        }

        $npcs = $query->get();

        $data = $npcs->map(fn (Npc $npc) => [
            'uuid' => $npc->uuid,
            'call_sign' => $npc->call_sign,
            'archetype' => $npc->archetype,
            'difficulty' => $npc->difficulty,
            'level' => $npc->level,
            'credits' => (float) $npc->credits,
            'status' => $npc->status,
            'current_activity' => $npc->current_activity,
            'location' => $npc->currentLocation ? [
                'id' => $npc->currentLocation->id,
                'name' => $npc->currentLocation->name,
                'x' => $npc->currentLocation->x,
                'y' => $npc->currentLocation->y,
            ] : null,
            'ship' => $npc->activeShip ? [
                'uuid' => $npc->activeShip->uuid,
                'name' => $npc->activeShip->name,
                'class' => $npc->activeShip->ship?->class,
                'hull' => $npc->activeShip->hull,
                'max_hull' => $npc->activeShip->max_hull,
            ] : null,
        ]);

        return $this->success([
            'npcs' => $data,
            'total' => $npcs->count(),
            'statistics' => $this->npcGenerationService->getNpcStatistics($galaxy),
        ]);
    }

    /**
     * Retrieve detailed information for an NPC identified by UUID.
     *
     * @param string $uuid The UUID of the NPC to fetch.
     * @return \Illuminate\Http\JsonResponse A JSON response containing the NPC's identifiers, archetype and description, difficulty, level, experience, credits, status, current activity, personality, combat and economy statistics, related galaxy info, current location (or null), active ship and its stats and cargo (or null), and `last_action_at`. Returns a 404 response if the NPC is not found.
     */
    public function showNpc(string $uuid): JsonResponse
    {
        $npc = Npc::where('uuid', $uuid)
            ->with(['galaxy', 'currentLocation', 'activeShip.ship', 'activeShip.cargo.mineral'])
            ->first();

        if (! $npc) {
            return $this->notFound('NPC not found');
        }

        return $this->success([
            'uuid' => $npc->uuid,
            'call_sign' => $npc->call_sign,
            'archetype' => $npc->archetype,
            'archetype_description' => Npc::ARCHETYPES[$npc->archetype]['description'] ?? null,
            'difficulty' => $npc->difficulty,
            'level' => $npc->level,
            'experience' => $npc->experience,
            'credits' => (float) $npc->credits,
            'status' => $npc->status,
            'current_activity' => $npc->current_activity,
            'personality' => [
                'aggression' => $npc->aggression,
                'risk_tolerance' => $npc->risk_tolerance,
                'trade_focus' => $npc->trade_focus,
            ],
            'combat_stats' => [
                'ships_destroyed' => $npc->ships_destroyed,
                'combats_won' => $npc->combats_won,
                'combats_lost' => $npc->combats_lost,
            ],
            'economy_stats' => [
                'total_trade_volume' => (float) $npc->total_trade_volume,
            ],
            'galaxy' => [
                'id' => $npc->galaxy->id,
                'uuid' => $npc->galaxy->uuid,
                'name' => $npc->galaxy->name,
            ],
            'location' => $npc->currentLocation ? [
                'id' => $npc->currentLocation->id,
                'name' => $npc->currentLocation->name,
                'x' => $npc->currentLocation->x,
                'y' => $npc->currentLocation->y,
                'is_inhabited' => $npc->currentLocation->is_inhabited,
            ] : null,
            'ship' => $npc->activeShip ? [
                'uuid' => $npc->activeShip->uuid,
                'name' => $npc->activeShip->name,
                'class' => $npc->activeShip->ship?->class,
                'status' => $npc->activeShip->status,
                'stats' => [
                    'hull' => $npc->activeShip->hull,
                    'max_hull' => $npc->activeShip->max_hull,
                    'weapons' => $npc->activeShip->weapons,
                    'cargo_hold' => $npc->activeShip->cargo_hold,
                    'current_cargo' => $npc->activeShip->current_cargo,
                    'sensors' => $npc->activeShip->sensors,
                    'warp_drive' => $npc->activeShip->warp_drive,
                    'current_fuel' => $npc->activeShip->current_fuel,
                    'max_fuel' => $npc->activeShip->max_fuel,
                ],
                'cargo' => $npc->activeShip->cargo->map(fn ($cargo) => [
                    'mineral' => $cargo->mineral?->name,
                    'quantity' => $cargo->quantity,
                ])->toArray(),
            ] : null,
            'last_action_at' => $npc->last_action_at?->toIso8601String(),
        ]);
    }

    /**
     * Delete an NPC identified by UUID.
     *
     * Checks ownership for single-player galaxies and removes the NPC if permitted.
     *
     * @param string $uuid The NPC's UUID.
     * @return JsonResponse JSON success response containing a `deleted` key with the NPC call sign and a message on success; returns a 404 response if the NPC is not found; returns a 403 response if the current user does not own the single-player galaxy containing the NPC.
     */
    public function destroyNpc(Request $request, string $uuid): JsonResponse
    {
        $npc = Npc::where('uuid', $uuid)->with('galaxy')->first();

        if (! $npc) {
            return $this->notFound('NPC not found');
        }

        // Check ownership for single_player galaxies
        if ($npc->galaxy->isSinglePlayer() && $npc->galaxy->owner_user_id !== $request->user()->id) {
            return $this->forbidden('You do not own this galaxy');
        }

        $callSign = $npc->call_sign;
        $npc->delete();

        return $this->success(
            ['deleted' => $callSign],
            "NPC '{$callSign}' has been deleted"
        );
    }

    /**
         * Return available NPC archetypes and difficulty presets.
         *
         * @return \Illuminate\Http\JsonResponse JSON with keys:
         *  - `archetypes`: array of objects each containing `name`, `description`, `default_aggression`, `default_risk_tolerance`, `default_trade_focus`
         *  - `difficulties`: array of objects each containing `name`, `credits_multiplier`, `combat_skill_multiplier`, `decision_quality`
         */
    public function getArchetypes(): JsonResponse
    {
        return $this->success([
            'archetypes' => collect(Npc::ARCHETYPES)->map(fn ($config, $name) => [
                'name' => $name,
                'description' => $config['description'],
                'default_aggression' => $config['aggression'],
                'default_risk_tolerance' => $config['risk_tolerance'],
                'default_trade_focus' => $config['trade_focus'],
            ])->values(),
            'difficulties' => collect(Npc::DIFFICULTY_MULTIPLIERS)->map(fn ($config, $name) => [
                'name' => $name,
                'credits_multiplier' => $config['credits'],
                'combat_skill_multiplier' => $config['combat_skill'],
                'decision_quality' => $config['decision_quality'],
            ])->values(),
        ]);
    }
}
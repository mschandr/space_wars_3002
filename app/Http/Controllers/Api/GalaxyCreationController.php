<?php

namespace App\Http\Controllers\Api;

use App\Enums\Galaxy\GalaxySizeTier;
use App\Http\Requests\Api\AddNpcsRequest;
use App\Http\Requests\Api\CreateGalaxyRequest;
use App\Models\Galaxy;
use App\Models\Npc;
use App\Services\GalaxyCreationService;
use App\Services\GalaxyGeneration\GalaxyGenerationOrchestrator;
use App\Services\NpcGenerationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class GalaxyCreationController extends BaseApiController
{
    private GalaxyCreationService $galaxyCreationService;

    private NpcGenerationService $npcGenerationService;

    private GalaxyGenerationOrchestrator $orchestrator;

    public function __construct(
        GalaxyCreationService        $galaxyCreationService,
        NpcGenerationService         $npcGenerationService,
        GalaxyGenerationOrchestrator $orchestrator
    )
    {
        $this->galaxyCreationService = $galaxyCreationService;
        $this->npcGenerationService  = $npcGenerationService;
        $this->orchestrator          = $orchestrator;
    }

    /**
     * Create a galaxy using the optimized orchestrator pipeline.
     *
     * POST /api/galaxies/create
     *
     * Uses the high-performance generator pipeline with:
     * - Spatial indexing for O(1) neighbor lookups
     * - Bulk database operations
     * - Per-generator metrics
     * - Tiered structure: civilized core + frontier outer region
     */
    public function createOptimized(CreateGalaxyRequest $request): JsonResponse
    {
        set_time_limit(300);  // 5 minutes max

        try {
            $sizeTier = $request->getSizeTier();

            if (!$sizeTier) {
                return $this->error(
                    'size_tier is required for optimized galaxy creation',
                    'MISSING_SIZE_TIER',
                    ['valid_tiers' => GalaxySizeTier::toOptionsArray()],
                    422
                );
            }

            $options = $request->validatedWithDefaults();

            // Add owner user ID for single player galaxies
            if (in_array($options['game_mode'], ['single_player', 'mixed'])) {
                $options['owner_user_id'] = $request->user()->id;
            }

            $result = $this->orchestrator->generate($sizeTier, $options);

            if (!$result['success']) {
                return $this->error(
                    $result['error'] ?? 'Galaxy generation failed',
                    'OPTIMIZED_GALAXY_CREATION_FAILED',
                    ['metrics' => $result['metrics'] ?? null],
                    500
                );
            }

            return $this->success(
                [
                    'galaxy'     => $result['galaxy'],
                    'statistics' => $result['statistics'],
                    'metrics'    => $result['metrics'],
                    'config'     => $result['config'],
                ],
                'Galaxy created successfully using optimized pipeline',
                201
            );
        } catch (RuntimeException $e) {
            return $this->error(
                $e->getMessage(),
                'OPTIMIZED_GALAXY_CREATION_FAILED',
                null,
                500
            );
        } catch (Exception $e) {
            return $this->error(
                'An unexpected error occurred: ' . $e->getMessage(),
                'OPTIMIZED_GALAXY_CREATION_ERROR',
                null,
                500
            );
        }
    }

    /**
     * Get galaxy creation status
     *
     * GET /api/galaxies/{uuid}/creation-status
     *
     * Returns progress status for galaxy creation (useful for async creation).
     */
    public function creationStatus(string $uuid): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $uuid)->first();

        if (!$galaxy) {
            return $this->notFound('Galaxy not found');
        }

        $progress        = $galaxy->progress_status ?? [];
        $currentProgress = $galaxy->getCurrentProgress();

        return $this->success([
            'galaxy_id'               => $galaxy->id,
            'galaxy_uuid'             => $galaxy->uuid,
            'galaxy_name'             => $galaxy->name,
            'status'                  => $galaxy->status->value ?? $galaxy->status,
            'size_tier'               => $galaxy->size_tier?->value,
            'current_progress'        => $currentProgress,
            'is_complete'             => $currentProgress >= 100 || $galaxy->status->isPlayable(),
            'generation_started_at'   => $galaxy->generation_started_at?->toIso8601String(),
            'generation_completed_at' => $galaxy->generation_completed_at?->toIso8601String(),
            'steps'                   => $progress,
        ]);
    }

    /**
     * Get available size tiers and their configurations
     *
     * GET /api/galaxies/size-tiers
     */
    public function getSizeTiers(): JsonResponse
    {
        return $this->success([
            'tiers' => GalaxySizeTier::toOptionsArray(),
        ]);
    }

    /**
     * Add NPCs to an existing galaxy
     *
     * POST /api/galaxies/{uuid}/npcs
     *
     * Adds NPC players to a galaxy that allows NPCs (single_player or mixed mode)
     */
    public function addNpcs(AddNpcsRequest $request, string $uuid): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $uuid)->first();

        if (!$galaxy) {
            return $this->notFound('Galaxy not found');
        }

        // Check if galaxy allows NPCs
        if (!$galaxy->allowsNpcs()) {
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
        } catch (RuntimeException $e) {
            return $this->error(
                $e->getMessage(),
                'NPC_CREATION_FAILED',
                null,
                500
            );
        }
    }

    /**
     * List NPCs in a galaxy
     *
     * GET /api/galaxies/{uuid}/npcs
     */
    public function listNpcs(Request $request, string $uuid): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $uuid)->first();

        if (!$galaxy) {
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

        $data = $npcs->map(fn(Npc $npc) => [
            'uuid'             => $npc->uuid,
            'call_sign'        => $npc->call_sign,
            'archetype'        => $npc->archetype,
            'difficulty'       => $npc->difficulty,
            'level'            => $npc->level,
            'credits'          => (float)$npc->credits,
            'status'           => $npc->status,
            'current_activity' => $npc->current_activity,
            'location'         => $npc->currentLocation ? [
                'id'   => $npc->currentLocation->id,
                'name' => $npc->currentLocation->name,
                'x'    => $npc->currentLocation->x,
                'y'    => $npc->currentLocation->y,
            ] : null,
            'ship'             => $npc->activeShip ? [
                'uuid'     => $npc->activeShip->uuid,
                'name'     => $npc->activeShip->name,
                'class'    => $npc->activeShip->ship?->class,
                'hull'     => $npc->activeShip->hull,
                'max_hull' => $npc->activeShip->max_hull,
            ] : null,
        ]);

        return $this->success([
            'npcs'       => $data,
            'total'      => $npcs->count(),
            'statistics' => $this->npcGenerationService->getNpcStatistics($galaxy),
        ]);
    }

    /**
     * Get details of a specific NPC
     *
     * GET /api/npcs/{uuid}
     */
    public function showNpc(string $uuid): JsonResponse
    {
        $npc = Npc::where('uuid', $uuid)
            ->with(['galaxy', 'currentLocation', 'activeShip.ship', 'activeShip.cargo.mineral'])
            ->first();

        if (!$npc) {
            return $this->notFound('NPC not found');
        }

        return $this->success([
            'uuid'                  => $npc->uuid,
            'call_sign'             => $npc->call_sign,
            'archetype'             => $npc->archetype,
            'archetype_description' => Npc::ARCHETYPES[$npc->archetype]['description'] ?? null,
            'difficulty'            => $npc->difficulty,
            'level'                 => $npc->level,
            'experience'            => $npc->experience,
            'credits'               => (float)$npc->credits,
            'status'                => $npc->status,
            'current_activity'      => $npc->current_activity,
            'personality'           => [
                'aggression'     => $npc->aggression,
                'risk_tolerance' => $npc->risk_tolerance,
                'trade_focus'    => $npc->trade_focus,
            ],
            'combat_stats'          => [
                'ships_destroyed' => $npc->ships_destroyed,
                'combats_won'     => $npc->combats_won,
                'combats_lost'    => $npc->combats_lost,
            ],
            'economy_stats'         => [
                'total_trade_volume' => (float)$npc->total_trade_volume,
            ],
            'galaxy'                => [
                'id'   => $npc->galaxy->id,
                'uuid' => $npc->galaxy->uuid,
                'name' => $npc->galaxy->name,
            ],
            'location'              => $npc->currentLocation ? [
                'id'           => $npc->currentLocation->id,
                'name'         => $npc->currentLocation->name,
                'x'            => $npc->currentLocation->x,
                'y'            => $npc->currentLocation->y,
                'is_inhabited' => $npc->currentLocation->is_inhabited,
            ] : null,
            'ship'                  => $npc->activeShip ? [
                'uuid'   => $npc->activeShip->uuid,
                'name'   => $npc->activeShip->name,
                'class'  => $npc->activeShip->ship?->class,
                'status' => $npc->activeShip->status,
                'stats'  => [
                    'hull'          => $npc->activeShip->hull,
                    'max_hull'      => $npc->activeShip->max_hull,
                    'weapons'       => $npc->activeShip->weapons,
                    'cargo_hold'    => $npc->activeShip->cargo_hold,
                    'current_cargo' => $npc->activeShip->current_cargo,
                    'sensors'       => $npc->activeShip->sensors,
                    'warp_drive'    => $npc->activeShip->warp_drive,
                    'current_fuel'  => $npc->activeShip->current_fuel,
                    'max_fuel'      => $npc->activeShip->max_fuel,
                ],
                'cargo'  => $npc->activeShip->cargo->map(fn($cargo) => [
                    'mineral'  => $cargo->mineral?->name,
                    'quantity' => $cargo->quantity,
                ])->toArray(),
            ] : null,
            'last_action_at'        => $npc->last_action_at?->toIso8601String(),
        ]);
    }

    /**
     * Delete an NPC
     *
     * DELETE /api/npcs/{uuid}
     */
    public function destroyNpc(Request $request, string $uuid): JsonResponse
    {
        $npc = Npc::where('uuid', $uuid)->with('galaxy')->first();

        if (!$npc) {
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
     * Get available archetypes and their configurations
     *
     * GET /api/npcs/archetypes
     */
    public function getArchetypes(): JsonResponse
    {
        return $this->success([
            'archetypes'   => collect(Npc::ARCHETYPES)->map(fn($config, $name) => [
                'name'                   => $name,
                'description'            => $config['description'],
                'default_aggression'     => $config['aggression'],
                'default_risk_tolerance' => $config['risk_tolerance'],
                'default_trade_focus'    => $config['trade_focus'],
            ])->values(),
            'difficulties' => collect(Npc::DIFFICULTY_MULTIPLIERS)->map(fn($config, $name) => [
                'name'                    => $name,
                'credits_multiplier'      => $config['credits'],
                'combat_skill_multiplier' => $config['combat_skill'],
                'decision_quality'        => $config['decision_quality'],
            ])->values(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PirateEncounterResource;
use App\Models\Player;
use App\Models\WarpGate;
use App\Models\WarpLanePirate;
use App\Services\Combat\CombatService;
use App\Services\PirateEncounterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CombatController extends BaseApiController
{
    public function __construct(
        private readonly PirateEncounterService $pirateService,
        private readonly CombatService $combatService
    ) {}

    /**
     * Check if a warp gate has pirate presence
     *
     * GET /api/warp-gates/{warpGateUuid}/pirates
     */
    public function checkPiratePresence(string $warpGateUuid): JsonResponse
    {
        $gate = WarpGate::where('uuid', $warpGateUuid)->firstOrFail();

        $hasPirates = $this->pirateService->hasPiratePresence($gate);
        $encounter = null;

        if ($hasPirates) {
            $pirateEncounter = $this->pirateService->getEncounter($gate);
            if ($pirateEncounter) {
                $fleet = $this->pirateService->generateFleet($pirateEncounter);
                $encounter = new PirateEncounterResource($pirateEncounter, $fleet);
            }
        }

        return $this->success([
            'has_pirates' => $hasPirates,
            'encounter' => $encounter,
        ]);
    }

    /**
     * Get detailed information about a pirate encounter
     *
     * GET /api/pirate-encounters/{encounterUuid}
     */
    public function getEncounterDetails(string $encounterUuid): JsonResponse
    {
        $encounter = WarpLanePirate::where('uuid', $encounterUuid)
            ->with(['captain.faction', 'warpGate'])
            ->firstOrFail();

        if (! $encounter->is_active) {
            return $this->error('This pirate encounter is no longer active', 'NOT_FOUND', null, 404);
        }

        $fleet = $this->pirateService->generateFleet($encounter);
        $details = $this->pirateService->getEncounterDetails($encounter, $fleet);

        return $this->success([
            'encounter' => new PirateEncounterResource($encounter, $fleet),
            'details' => $details,
        ]);
    }

    /**
     * Get combat preview (estimated win chance, difficulty)
     * Supports both single-ship and flotilla combat
     *
     * GET /api/players/{uuid}/combat/preview
     */
    public function getCombatPreview(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $ship = $player->activeShip;
        if (! $ship) {
            return $this->error('No active ship', 'ERROR', null, 400);
        }

        $validated = $request->validate([
            'encounter_uuid' => 'required|string|exists:warp_lane_pirates,uuid',
        ]);

        $encounter = WarpLanePirate::where('uuid', $validated['encounter_uuid'])->firstOrFail();
        $fleet = $this->pirateService->generateFleet($encounter);

        // Get combat readiness (shows if player will engage as flotilla)
        $readiness = $this->combatService->getCombatReadiness($player);

        // Use appropriate combat preview based on flotilla status
        if ($this->combatService->canCombatAsFlotilla($player)) {
            // Flotilla combat preview
            $preview = $this->combatService->prepareCombatPreview($player, $encounter);
            return $this->success([
                'combat_readiness' => $readiness,
                'combat_preview' => $preview,
                'note' => 'You will engage with your entire flotilla. All ships fire together.',
            ]);
        } else {
            // Single-ship combat preview (existing system)
            $preview = $this->pirateService->getCombatPreview($ship, $fleet);
            $escapeAnalysis = $this->pirateService->getEscapeAnalysis($ship, $fleet);

            return $this->success([
                'combat_readiness' => $readiness,
                'combat_preview' => $preview,
                'escape_analysis' => $escapeAnalysis,
            ]);
        }
    }

    /**
     * Attempt to escape from pirates
     * Supports both single-ship and flotilla escape
     *
     * POST /api/players/{uuid}/combat/escape
     */
    public function attemptEscape(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $ship = $player->activeShip;
        if (! $ship) {
            return $this->error('No active ship', 'ERROR', null, 400);
        }

        $validated = $request->validate([
            'encounter_uuid' => 'required|string|exists:warp_lane_pirates,uuid',
        ]);

        $encounter = WarpLanePirate::where('uuid', $validated['encounter_uuid'])->firstOrFail();
        $fleet = $this->pirateService->generateFleet($encounter);

        // Check if escaping as flotilla
        if ($this->combatService->canCombatAsFlotilla($player)) {
            $result = $this->combatService->attemptFlotillaEscape(
                $this->combatService->getPlayerFlotilla($player),
                $fleet
            );

            // Record encounter
            $this->pirateService->recordEncounter($encounter);

            return $this->success([
                'escape_type' => 'flotilla',
                'escaped' => $result['success'],
                'escape_chance' => $result['escape_chance'],
                'message' => $result['message'],
            ], $result['success'] ? 'Flotilla escaped!' : 'Flotilla escape failed');
        } else {
            // Single-ship escape (existing system)
            $result = $this->pirateService->attemptEscape($ship, $fleet);

            // Record encounter
            $this->pirateService->recordEncounter($encounter);

            if ($result['success']) {
                return $this->success([
                    'escape_type' => 'single_ship',
                    'escaped' => true,
                    'message' => $result['message'],
                ], 'Successfully escaped from pirates');
            }

            return $this->success([
                'escape_type' => 'single_ship',
                'escaped' => false,
                'message' => $result['message'],
                'interceptor' => [
                    'name' => $result['interceptor']->ship_name,
                    'speed' => $result['interceptor']->speed,
                    'warp_drive' => $result['interceptor']->warp_drive,
                ],
            ], 'Escape failed - pirates intercepted');
        }
    }

    /**
     * Surrender to pirates
     * Supports both single-ship and flotilla surrender
     *
     * POST /api/players/{uuid}/combat/surrender
     */
    public function surrender(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $ship = $player->activeShip;
        if (! $ship) {
            return $this->error('No active ship', 'ERROR', null, 400);
        }

        $validated = $request->validate([
            'encounter_uuid' => 'required|string|exists:warp_lane_pirates,uuid',
        ]);

        $encounter = WarpLanePirate::where('uuid', $validated['encounter_uuid'])->firstOrFail();
        $fleet = $this->pirateService->generateFleet($encounter);

        // Check if surrendering as flotilla
        if ($this->combatService->canCombatAsFlotilla($player)) {
            // Flotilla surrender
            $flotilla = $this->combatService->getPlayerFlotilla($player);
            $result = $this->combatService->handleFlotillaSurrender($player, $flotilla);

            // Record encounter
            $this->pirateService->recordEncounter($encounter);

            // Reload player and flotilla data
            $player->refresh();

            return $this->success([
                'surrender_type' => 'flotilla',
                'surrendered' => $result['surrendered'],
                'total_cargo_lost' => $result['total_cargo_lost'],
                'message' => $result['message'],
            ], 'Flotilla surrender processed');
        } else {
            // Single-ship surrender (existing system)
            $result = $this->pirateService->processSurrender($player, $ship, $fleet);

            // Record encounter
            $this->pirateService->recordEncounter($encounter);

            // Reload player and ship data
            $player->refresh();
            $ship->refresh();
            $ship->load('cargo.mineral');

            return $this->success([
                'surrender_type' => 'single_ship',
                'cargo_lost' => $result['cargo_lost'],
                'plans_stolen' => $result['plans_stolen'],
                'components_downgraded' => $result['components_downgraded'],
                'upgrades_stolen' => $result['upgrades_stolen'],
                'message' => $result['message'],
            ], 'Surrender processed');
        }
    }

    /**
     * Engage in combat with pirates
     * Automatically routes to flotilla or single-ship combat
     *
     * POST /api/players/{uuid}/combat/engage
     */
    public function engageCombat(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $ship = $player->activeShip;
        if (! $ship) {
            return $this->error('No active ship', 'ERROR', null, 400);
        }

        $validated = $request->validate([
            'encounter_uuid' => 'required|string|exists:warp_lane_pirates,uuid',
        ]);

        $encounter = WarpLanePirate::where('uuid', $validated['encounter_uuid'])->firstOrFail();
        $fleet = $this->pirateService->generateFleet($encounter);

        // Check if player will engage as flotilla
        if ($this->combatService->canCombatAsFlotilla($player)) {
            // FLOTILLA COMBAT
            $combatResult = $this->combatService->resolveFlotillaCombat($player, $encounter, $fleet);

            // Record encounter
            $this->pirateService->recordEncounter($encounter);

            // Reload player data
            $player->refresh();

            $response = [
                'combat_type' => 'flotilla',
                'victory' => $combatResult['victory'],
                'combat_log' => $combatResult['log'],
                'rounds' => $combatResult['rounds'],
                'xp_earned' => $combatResult['xp_earned'],
                'player_hull_remaining' => $combatResult['player_hull_remaining'],
            ];

            if ($combatResult['victory']) {
                // Add salvage info for flotilla
                $response['salvage'] = $combatResult['salvage'] ?? null;
                $response['message'] = 'Victory! All pirate ships destroyed. Your flotilla emerges victorious.';
            } else {
                // Flotilla destroyed
                $response['death'] = $combatResult['death'] ?? null;
                $response['message'] = 'Defeat - Your flotilla was destroyed in combat.';
            }

            return $this->success($response, $response['message']);
        } else {
            // SINGLE-SHIP COMBAT (existing system)
            $combatResult = $this->pirateService->initiateCombat($player, $ship, $fleet);

            // Record encounter
            $this->pirateService->recordEncounter($encounter);

            // Reload player data
            $player->refresh();

            $response = [
                'combat_type' => 'single_ship',
                'victory' => $combatResult['victory'],
                'combat_log' => $combatResult['log'],
                'rounds' => $combatResult['rounds'],
                'xp_earned' => $combatResult['xp_earned'],
                'player_hull_remaining' => $combatResult['player_hull_remaining'],
            ];

            if ($combatResult['victory']) {
                // Add salvage info
                $response['salvage'] = $combatResult['salvage'] ?? null;
                $response['message'] = 'Victory! Pirates defeated.';
            } else {
                // Player died
                $response['death'] = $combatResult['death'] ?? null;
                $response['death_message'] = $combatResult['death_message'] ?? null;
                $response['message'] = 'Defeat - Your ship was destroyed.';
            }

            return $this->success($response, $response['message']);
        }
    }

    /**
     * Collect salvage after combat victory
     *
     * POST /api/players/{uuid}/combat/salvage
     */
    public function collectSalvage(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $ship = $player->activeShip;
        if (! $ship) {
            return $this->error('No active ship', 'ERROR', null, 400);
        }

        $validated = $request->validate([
            'minerals' => 'sometimes|array',
            'minerals.*.mineral_id' => 'required|exists:minerals,id',
            'minerals.*.quantity' => 'required|integer|min:1',
            'plan_ids' => 'sometimes|array',
            'plan_ids.*' => 'required|exists:upgrade_plans,id',
        ]);

        // Validate cargo space
        $validation = $this->pirateService->validateSalvageSelection(
            $ship,
            $validated['minerals'] ?? []
        );

        if (! $validation['valid']) {
            return $this->error($validation['message'], 'ERROR', null, 400);
        }

        // Transfer salvage
        $result = $this->pirateService->transferSalvage(
            $player,
            $ship,
            $validated['minerals'] ?? [],
            $validated['plan_ids'] ?? []
        );

        // Reload ship data
        $ship->refresh();
        $ship->load('cargo.mineral');

        return $this->success([
            'minerals_collected' => $result['minerals_added'],
            'plans_collected' => $result['plans_added'],
            'cargo_used' => $ship->current_cargo,
            'cargo_remaining' => $ship->getEffectiveCargoHold() - $ship->current_cargo,
        ], 'Salvage collected successfully');
    }
}

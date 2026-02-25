<?php

namespace App\Http\Controllers\Api;

use App\Models\Colony;
use App\Models\Player;
use App\Services\ColonyCombatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ColonyCombatController extends BaseApiController
{
    public function __construct(
        private readonly ColonyCombatService $colonyCombatService
    ) {}

    /**
     * Get colony defense information
     *
     * GET /api/colonies/{uuid}/defenses
     */
    public function getDefenses(string $uuid): JsonResponse
    {
        $colony = Colony::where('uuid', $uuid)
            ->with(['player', 'buildings'])
            ->firstOrFail();

        // Calculate defense strength
        $defensiveBuildings = $colony->buildings()
            ->whereIn('building_type', ['defense_turret', 'shield_generator', 'garrison'])
            ->where('status', 'operational')
            ->count();

        return $this->success([
            'colony' => [
                'uuid' => $colony->uuid,
                'name' => $colony->name,
                'owner' => [
                    'uuid' => $colony->player->uuid,
                    'call_sign' => $colony->player->call_sign,
                ],
                'development_level' => $colony->development_level,
                'population' => $colony->population,
                'defense_rating' => $colony->defense_rating,
                'garrison_strength' => $colony->garrison_strength,
                'defensive_buildings' => $defensiveBuildings,
                'last_attacked_at' => $colony->last_attacked_at,
            ],
        ]);
    }

    /**
     * Attack a colony
     *
     * POST /api/players/{uuid}/attack-colony/{colonyUuid}
     */
    public function attackColony(Request $request, string $uuid, string $colonyUuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $colony = Colony::where('uuid', $colonyUuid)
            ->with(['player'])
            ->firstOrFail();

        // Check for ally UUIDs (optional)
        $validated = $request->validate([
            'ally_uuids' => 'sometimes|array',
            'ally_uuids.*' => 'exists:players,uuid',
        ]);

        $allies = [];
        if (! empty($validated['ally_uuids'])) {
            $allies = Player::whereIn('uuid', $validated['ally_uuids'])->get()->all();
        }

        $result = $this->colonyCombatService->initiateColonyAttack($colony, $player, $allies);

        if (! $result['success']) {
            return $this->error($result['message'], 'ATTACK_FAILED', null, 400);
        }

        // Handle instant capture
        if (isset($result['instant_capture']) && $result['instant_capture']) {
            return $this->success([
                'instant_capture' => true,
                'colony' => [
                    'uuid' => $colony->uuid,
                    'name' => $colony->name,
                    'old_owner' => [
                        'uuid' => $result['old_owner']->uuid,
                        'call_sign' => $result['old_owner']->call_sign,
                    ],
                    'new_owner' => [
                        'uuid' => $result['new_owner']->uuid,
                        'call_sign' => $result['new_owner']->call_sign,
                    ],
                ],
            ], $result['message']);
        }

        // Handle combat result
        return $this->success([
            'combat_session' => [
                'uuid' => $result['combat_session']->uuid,
            ],
            'result' => [
                'victor' => $result['result']['victor'],
                'rounds' => $result['result']['rounds'],
                'attackers_survived' => $result['result']['attackers_survived'],
                'colony_captured' => $result['result']['colony_captured'],
                'buildings_damaged' => $result['result']['buildings_damaged'],
                'combat_log' => $result['result']['combat_log'],
            ],
        ], 'Colony siege completed');
    }

    /**
     * Fortify a colony (increase defenses)
     *
     * POST /api/colonies/{uuid}/fortify
     */
    public function fortifyColony(Request $request, string $uuid): JsonResponse
    {
        $colony = Colony::where('uuid', $uuid)->firstOrFail();

        // Verify ownership
        $player = $colony->player;
        $this->authorizePlayer($player, $request->user());

        $validated = $request->validate([
            'credits' => 'required|integer|min:1000|max:100000',
        ]);

        $credits = $validated['credits'];

        // Check if player has credits
        if ($player->credits < $credits) {
            return $this->error('Insufficient credits', 'INSUFFICIENT_CREDITS', null, 400);
        }

        // Deduct credits
        $player->deductCredits($credits);

        // Increase defenses based on investment
        $defenseIncrease = (int) ($credits / 100);
        $garrisonIncrease = (int) ($credits / 50);

        $colony->defense_rating += $defenseIncrease;
        $colony->garrison_strength += $garrisonIncrease;
        $colony->save();

        return $this->success([
            'colony' => [
                'uuid' => $colony->uuid,
                'name' => $colony->name,
                'defense_rating' => $colony->defense_rating,
                'garrison_strength' => $colony->garrison_strength,
            ],
            'defense_increase' => $defenseIncrease,
            'garrison_increase' => $garrisonIncrease,
        ], 'Colony fortified successfully');
    }
}

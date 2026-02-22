<?php

namespace App\Http\Controllers\Api;

use App\Models\Player;
use App\Models\PvPChallenge;
use App\Services\PvPCombatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PvPCombatController extends BaseApiController
{
    public function __construct(
        private readonly PvPCombatService $pvpService
    ) {}

    /**
     * Issue a PvP challenge to another player
     *
     * POST /api/players/{uuid}/pvp/challenge
     */
    public function issueChallenge(Request $request, string $uuid): JsonResponse
    {
        $challenger = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($challenger, $request->user());

        $validated = $request->validate([
            'target_player_uuid' => 'required|exists:players,uuid',
            'message' => 'sometimes|string|max:500',
            'wager_credits' => 'sometimes|numeric|min:0|max:1000000',
            'max_team_size' => 'sometimes|integer|min:1|max:10',
        ]);

        $target = Player::where('uuid', $validated['target_player_uuid'])->firstOrFail();

        $result = $this->pvpService->createChallenge(
            $challenger,
            $target,
            $validated['message'] ?? null,
            $validated['wager_credits'] ?? 0,
            $validated['max_team_size'] ?? 1
        );

        if (! $result['success']) {
            return $this->error($result['message'], 'CHALLENGE_FAILED', null, 400);
        }

        return $this->success([
            'challenge' => [
                'uuid' => $result['challenge']->uuid,
                'target' => [
                    'uuid' => $target->uuid,
                    'call_sign' => $target->call_sign,
                ],
                'message' => $result['challenge']->message,
                'wager_credits' => $result['challenge']->wager_credits,
                'expires_at' => $result['challenge']->expires_at,
            ],
        ], $result['message'], 201);
    }

    /**
     * Get pending challenges for a player
     *
     * GET /api/players/{uuid}/pvp/challenges
     */
    public function listChallenges(string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();

        // Get challenges where player is the target
        $incomingChallenges = PvPChallenge::where('target_id', $player->id)
            ->where('status', 'pending')
            ->with(['challenger'])
            ->get()
            ->filter(fn ($c) => ! $c->isExpired())
            ->map(fn ($c) => [
                'uuid' => $c->uuid,
                'type' => 'incoming',
                'challenger' => [
                    'uuid' => $c->challenger->uuid,
                    'call_sign' => $c->challenger->call_sign,
                ],
                'message' => $c->message,
                'wager_credits' => $c->wager_credits,
                'expires_at' => $c->expires_at,
            ]);

        // Get challenges issued by player
        $outgoingChallenges = PvPChallenge::where('challenger_id', $player->id)
            ->where('status', 'pending')
            ->with(['target'])
            ->get()
            ->filter(fn ($c) => ! $c->isExpired())
            ->map(fn ($c) => [
                'uuid' => $c->uuid,
                'type' => 'outgoing',
                'target' => [
                    'uuid' => $c->target->uuid,
                    'call_sign' => $c->target->call_sign,
                ],
                'message' => $c->message,
                'wager_credits' => $c->wager_credits,
                'expires_at' => $c->expires_at,
            ]);

        return $this->success([
            'incoming_challenges' => $incomingChallenges,
            'outgoing_challenges' => $outgoingChallenges,
            'total_incoming' => $incomingChallenges->count(),
            'total_outgoing' => $outgoingChallenges->count(),
        ]);
    }

    /**
     * Accept a PvP challenge
     *
     * POST /api/players/{uuid}/pvp/challenge/{challengeUuid}/accept
     */
    public function acceptChallenge(Request $request, string $uuid, string $challengeUuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $challenge = PvPChallenge::where('uuid', $challengeUuid)->firstOrFail();

        $result = $this->pvpService->acceptChallenge($challenge, $player);

        if (! $result['success']) {
            return $this->error($result['message'], 'ACCEPT_FAILED', null, 400);
        }

        return $this->success([
            'combat_session' => [
                'uuid' => $result['combat_session']->uuid,
            ],
            'result' => [
                'victor' => [
                    'uuid' => $result['result']['victor']->uuid,
                    'call_sign' => $result['result']['victor']->call_sign,
                ],
                'victor_hull_remaining' => $result['result']['victor_hull_remaining'],
                'loser' => [
                    'uuid' => $result['result']['loser']->uuid,
                    'call_sign' => $result['result']['loser']->call_sign,
                ],
                'rounds' => $result['result']['rounds'],
                'xp_earned' => $result['result']['xp_earned'],
                'credits_earned' => $result['result']['credits_earned'],
                'combat_log' => $result['result']['combat_log'],
                'death_result' => $this->transformDeathResult($result['result']['death_result']),
            ],
        ], 'Combat completed');
    }

    /**
     * Decline a PvP challenge
     *
     * POST /api/players/{uuid}/pvp/challenge/{challengeUuid}/decline
     */
    public function declineChallenge(Request $request, string $uuid, string $challengeUuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $challenge = PvPChallenge::where('uuid', $challengeUuid)->firstOrFail();

        $result = $this->pvpService->declineChallenge($challenge, $player);

        if (! $result['success']) {
            return $this->error($result['message'], 'DECLINE_FAILED', null, 400);
        }

        return $this->success([], $result['message']);
    }

    /**
     * Cancel an outgoing challenge
     *
     * DELETE /api/players/{uuid}/pvp/challenge/{challengeUuid}
     */
    public function cancelChallenge(Request $request, string $uuid, string $challengeUuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $challenge = PvPChallenge::where('uuid', $challengeUuid)->firstOrFail();

        if ($challenge->challenger_id !== $player->id) {
            return $this->error('You can only cancel your own challenges', 'UNAUTHORIZED', null, 403);
        }

        if ($challenge->status !== 'pending') {
            return $this->error('Only pending challenges can be cancelled', 'INVALID_STATUS', null, 400);
        }

        $challenge->update(['status' => 'cancelled']);

        return $this->success([], 'Challenge cancelled');
    }

    /**
     * Get combat session details
     *
     * GET /api/combat-sessions/{uuid}
     */
    public function getCombatSession(string $uuid): JsonResponse
    {
        $session = \App\Models\CombatSession::where('uuid', $uuid)
            ->with(['participants.player', 'participants.playerShip'])
            ->firstOrFail();

        return $this->success([
            'combat_session' => [
                'uuid' => $session->uuid,
                'combat_type' => $session->combat_type,
                'status' => $session->status,
                'current_round' => $session->current_round,
                'victor_type' => $session->victor_type,
                'started_at' => $session->started_at,
                'ended_at' => $session->ended_at,
                'participants' => $session->participants->map(fn ($p) => [
                    'player' => [
                        'uuid' => $p->player->uuid,
                        'call_sign' => $p->player->call_sign,
                    ],
                    'side' => $p->side,
                    'starting_hull' => $p->starting_hull,
                    'current_hull' => $p->current_hull,
                    'damage_dealt' => $p->damage_dealt,
                    'damage_taken' => $p->damage_taken,
                    'survived' => $p->survived,
                    'xp_earned' => $p->xp_earned,
                    'credits_earned' => $p->credits_earned,
                ]),
                'combat_log' => $session->combat_log,
            ],
        ]);
    }

    /**
     * Transform death result for API response, converting respawn_location model to flat fields.
     */
    private function transformDeathResult(?array $deathResult): ?array
    {
        if (! $deathResult) {
            return null;
        }

        $respawn = $deathResult['respawn_location'] ?? null;
        $deathResult['respawn_location'] = $respawn ? [
            'uuid' => $respawn->uuid,
            'name' => $respawn->name,
            'x' => (float) $respawn->x,
            'y' => (float) $respawn->y,
        ] : null;

        return $deathResult;
    }
}

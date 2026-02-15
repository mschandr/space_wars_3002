<?php

namespace App\Http\Controllers\Api;

use App\Models\Player;
use App\Models\PvPChallenge;
use App\Models\PvPTeamInvitation;
use App\Services\TeamCombatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamCombatController extends BaseApiController
{
    public function __construct(
        private readonly TeamCombatService $teamCombatService
    ) {}

    /**
     * Invite an ally to join a challenge
     *
     * POST /api/players/{uuid}/pvp/challenge/{challengeUuid}/invite
     */
    public function inviteAlly(Request $request, string $uuid, string $challengeUuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $validated = $request->validate([
            'invitee_uuid' => 'required|exists:players,uuid',
            'side' => 'required|in:attacker,defender',
        ]);

        $challenge = PvPChallenge::where('uuid', $challengeUuid)->firstOrFail();
        $invitee = Player::where('uuid', $validated['invitee_uuid'])->firstOrFail();

        $result = $this->teamCombatService->inviteAlly(
            $challenge,
            $player,
            $invitee,
            $validated['side']
        );

        if (! $result['success']) {
            return $this->error($result['message'], 'INVITE_FAILED', null, 400);
        }

        return $this->success([
            'invitation' => [
                'id' => $result['invitation']->id,
                'invitee' => [
                    'uuid' => $invitee->uuid,
                    'call_sign' => $invitee->call_sign,
                ],
                'side' => $result['invitation']->side,
                'status' => $result['invitation']->status,
            ],
        ], $result['message'], 201);
    }

    /**
     * Get team invitations for a player
     *
     * GET /api/players/{uuid}/team-invitations
     */
    public function listInvitations(string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();

        $invitations = PvPTeamInvitation::where('invited_player_id', $player->id)
            ->where('status', 'pending')
            ->with(['pvpChallenge.challenger', 'pvpChallenge.target', 'invitedByPlayer'])
            ->get()
            ->filter(fn ($inv) => ! $inv->pvpChallenge->isExpired())
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'challenge' => [
                    'uuid' => $inv->pvpChallenge->uuid,
                    'challenger' => [
                        'uuid' => $inv->pvpChallenge->challenger->uuid,
                        'call_sign' => $inv->pvpChallenge->challenger->call_sign,
                    ],
                    'target' => [
                        'uuid' => $inv->pvpChallenge->target->uuid,
                        'call_sign' => $inv->pvpChallenge->target->call_sign,
                    ],
                    'wager_credits' => $inv->pvpChallenge->wager_credits,
                    'expires_at' => $inv->pvpChallenge->expires_at,
                ],
                'invited_by' => [
                    'uuid' => $inv->invitedByPlayer->uuid,
                    'call_sign' => $inv->invitedByPlayer->call_sign,
                ],
                'side' => $inv->side,
            ]);

        return $this->success([
            'invitations' => $invitations,
            'total' => $invitations->count(),
        ]);
    }

    /**
     * Accept a team invitation
     *
     * POST /api/players/{uuid}/team-invitations/{invitationId}/accept
     */
    public function acceptInvitation(Request $request, string $uuid, int $invitationId): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $invitation = PvPTeamInvitation::findOrFail($invitationId);

        $result = $this->teamCombatService->acceptInvitation($invitation, $player);

        if (! $result['success']) {
            return $this->error($result['message'], 'ACCEPT_FAILED', null, 400);
        }

        return $this->success([], $result['message']);
    }

    /**
     * Decline a team invitation
     *
     * POST /api/players/{uuid}/team-invitations/{invitationId}/decline
     */
    public function declineInvitation(Request $request, string $uuid, int $invitationId): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $invitation = PvPTeamInvitation::findOrFail($invitationId);

        $result = $this->teamCombatService->declineInvitation($invitation, $player);

        if (! $result['success']) {
            return $this->error($result['message'], 'DECLINE_FAILED', null, 400);
        }

        return $this->success([], $result['message']);
    }

    /**
     * Get team composition for a challenge
     *
     * GET /api/pvp/challenge/{challengeUuid}/teams
     */
    public function getTeamComposition(string $challengeUuid): JsonResponse
    {
        $challenge = PvPChallenge::where('uuid', $challengeUuid)
            ->with(['challenger', 'target', 'acceptedInvitations.invitedPlayer'])
            ->firstOrFail();

        $attackersTeam = $challenge->getAttackersTeam();
        $defendersTeam = $challenge->getDefendersTeam();

        return $this->success([
            'challenge' => [
                'uuid' => $challenge->uuid,
                'status' => $challenge->status,
                'max_team_size' => $challenge->max_team_size,
                'wager_credits' => $challenge->wager_credits,
                'expires_at' => $challenge->expires_at,
            ],
            'attackers' => $attackersTeam->map(fn ($p) => [
                'uuid' => $p->uuid,
                'call_sign' => $p->call_sign,
                'ship' => $p->activeShip ? [
                    'name' => $p->activeShip->name,
                    'hull' => $p->activeShip->hull,
                    'weapons' => $p->activeShip->weapons,
                ] : null,
            ])->values(),
            'defenders' => $defendersTeam->map(fn ($p) => [
                'uuid' => $p->uuid,
                'call_sign' => $p->call_sign,
                'ship' => $p->activeShip ? [
                    'name' => $p->activeShip->name,
                    'hull' => $p->activeShip->hull,
                    'weapons' => $p->activeShip->weapons,
                ] : null,
            ])->values(),
            'attackers_count' => $attackersTeam->count(),
            'defenders_count' => $defendersTeam->count(),
        ]);
    }

    /**
     * Accept a team challenge and start combat
     *
     * POST /api/players/{uuid}/pvp/challenge/{challengeUuid}/accept-team
     */
    public function acceptTeamChallenge(Request $request, string $uuid, string $challengeUuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $challenge = PvPChallenge::where('uuid', $challengeUuid)->firstOrFail();

        $result = $this->teamCombatService->acceptTeamChallenge($challenge, $player);

        if (! $result['success']) {
            return $this->error($result['message'], 'ACCEPT_FAILED', null, 400);
        }

        return $this->success([
            'combat_session' => [
                'uuid' => $result['combat_session']->uuid,
            ],
            'result' => [
                'victor_team' => $result['result']['victor_team'],
                'victors' => $result['result']['victors'],
                'losers' => $result['result']['losers'],
                'rounds' => $result['result']['rounds'],
                'combat_log' => $result['result']['combat_log'],
                'death_results' => $result['result']['death_results'],
            ],
        ], 'Team combat completed');
    }
}

<?php

namespace App\Services;

use App\Models\CombatParticipant;
use App\Models\CombatSession;
use App\Models\Player;
use App\Models\PvPChallenge;
use App\Models\PvPTeamInvitation;

class TeamCombatService
{
    public function __construct(
        private readonly PlayerDeathService $deathService
    ) {}

    /**
     * Invite an ally to join a challenge
     */
    public function inviteAlly(
        PvPChallenge $challenge,
        Player $inviter,
        Player $invitee,
        string $side
    ): array {
        // Validation
        if ($challenge->status !== 'pending') {
            return ['success' => false, 'message' => 'Challenge is no longer pending'];
        }

        if ($challenge->isExpired()) {
            return ['success' => false, 'message' => 'Challenge has expired'];
        }

        // Verify inviter is part of the challenge
        $isChallenger = $challenge->challenger_id === $inviter->id;
        $isTarget = $challenge->target_id === $inviter->id;

        if (! $isChallenger && ! $isTarget) {
            return ['success' => false, 'message' => 'You are not part of this challenge'];
        }

        // Verify inviter can invite on this side
        if ($isChallenger && $side !== 'attacker') {
            return ['success' => false, 'message' => 'Challenger can only invite attackers'];
        }

        if ($isTarget && $side !== 'defender') {
            return ['success' => false, 'message' => 'Target can only invite defenders'];
        }

        // Check if invitee is already part of challenge
        if ($challenge->challenger_id === $invitee->id || $challenge->target_id === $invitee->id) {
            return ['success' => false, 'message' => 'Player is already part of this challenge'];
        }

        // Check if already invited
        $existingInvite = PvPTeamInvitation::where('pvp_challenge_id', $challenge->id)
            ->where('invited_player_id', $invitee->id)
            ->first();

        if ($existingInvite) {
            return ['success' => false, 'message' => 'Player has already been invited'];
        }

        // Check team size limits
        $currentTeamSize = $this->getTeamSize($challenge, $side);
        if ($currentTeamSize >= $challenge->max_team_size) {
            return ['success' => false, 'message' => 'Team is full'];
        }

        // Verify invitee is at same location
        if ($invitee->current_poi_id !== $challenge->challenge_poi_id) {
            return ['success' => false, 'message' => 'Invited player must be at the challenge location'];
        }

        // Verify invitee has active ship
        if (! $invitee->activeShip) {
            return ['success' => false, 'message' => 'Invited player does not have an active ship'];
        }

        // Create invitation
        $invitation = PvPTeamInvitation::create([
            'pvp_challenge_id' => $challenge->id,
            'invited_player_id' => $invitee->id,
            'invited_by_player_id' => $inviter->id,
            'side' => $side,
        ]);

        return [
            'success' => true,
            'invitation' => $invitation,
            'message' => 'Ally invited successfully',
        ];
    }

    /**
     * Accept a team invitation
     */
    public function acceptInvitation(PvPTeamInvitation $invitation, Player $player): array
    {
        if ($invitation->invited_player_id !== $player->id) {
            return ['success' => false, 'message' => 'This invitation is not for you'];
        }

        if ($invitation->status !== 'pending') {
            return ['success' => false, 'message' => 'This invitation is no longer available'];
        }

        $challenge = $invitation->pvpChallenge;

        if ($challenge->status !== 'pending') {
            return ['success' => false, 'message' => 'Challenge is no longer pending'];
        }

        if ($challenge->isExpired()) {
            return ['success' => false, 'message' => 'Challenge has expired'];
        }

        // Verify player is still at location
        if ($player->current_poi_id !== $challenge->challenge_poi_id) {
            return ['success' => false, 'message' => 'You are no longer at the challenge location'];
        }

        // Verify player still has active ship
        if (! $player->activeShip) {
            return ['success' => false, 'message' => 'You need an active ship to join this challenge'];
        }

        // Check team size
        $currentTeamSize = $this->getTeamSize($challenge, $invitation->side);
        if ($currentTeamSize >= $challenge->max_team_size) {
            return ['success' => false, 'message' => 'Team is full'];
        }

        $invitation->accept();

        return [
            'success' => true,
            'message' => 'Joined team successfully',
        ];
    }

    /**
     * Decline a team invitation
     */
    public function declineInvitation(PvPTeamInvitation $invitation, Player $player): array
    {
        if ($invitation->invited_player_id !== $player->id) {
            return ['success' => false, 'message' => 'This invitation is not for you'];
        }

        if ($invitation->status !== 'pending') {
            return ['success' => false, 'message' => 'This invitation is no longer available'];
        }

        $invitation->decline();

        return [
            'success' => true,
            'message' => 'Invitation declined',
        ];
    }

    /**
     * Accept a challenge and initiate team combat
     */
    public function acceptTeamChallenge(PvPChallenge $challenge, Player $acceptingPlayer): array
    {
        // Validation (similar to PvPCombatService)
        if ($challenge->target_id !== $acceptingPlayer->id) {
            return ['success' => false, 'message' => 'You are not the target of this challenge'];
        }

        if ($challenge->status !== 'pending') {
            return ['success' => false, 'message' => 'This challenge is no longer available'];
        }

        if ($challenge->isExpired()) {
            $challenge->expire();

            return ['success' => false, 'message' => 'This challenge has expired'];
        }

        // Get teams
        $attackers = $challenge->getAttackersTeam();
        $defenders = $challenge->getDefendersTeam();

        // Verify all players at same location and have ships
        foreach ($attackers->merge($defenders) as $player) {
            if ($player->current_poi_id !== $challenge->challenge_poi_id) {
                return ['success' => false, 'message' => "{$player->call_sign} is no longer at the challenge location"];
            }

            if (! $player->activeShip) {
                return ['success' => false, 'message' => "{$player->call_sign} no longer has an active ship"];
            }
        }

        // Deduct wagers if applicable
        if ($challenge->wager_credits > 0) {
            foreach ($attackers->merge($defenders) as $player) {
                if ($player->credits < $challenge->wager_credits) {
                    return ['success' => false, 'message' => "{$player->call_sign} cannot afford the wager"];
                }
                $player->deductCredits($challenge->wager_credits);
            }
        }

        // Accept challenge
        $challenge->accept();

        // Create combat session
        $combatSession = CombatSession::create([
            'combat_type' => $attackers->count() > 1 || $defenders->count() > 1 ? 'pve_coop' : 'pvp',
            'poi_id' => $challenge->challenge_poi_id,
            'pvp_challenge_id' => $challenge->id,
        ]);

        // Add attacker participants
        foreach ($attackers as $index => $player) {
            $ship = $player->activeShip;
            CombatParticipant::create([
                'combat_session_id' => $combatSession->id,
                'player_id' => $player->id,
                'player_ship_id' => $ship->id,
                'side' => $index === 0 ? 'attacker' : 'ally_attacker',
                'starting_hull' => $ship->hull,
                'current_hull' => $ship->hull,
            ]);
        }

        // Add defender participants
        foreach ($defenders as $index => $player) {
            $ship = $player->activeShip;
            CombatParticipant::create([
                'combat_session_id' => $combatSession->id,
                'player_id' => $player->id,
                'player_ship_id' => $ship->id,
                'side' => $index === 0 ? 'defender' : 'ally_defender',
                'starting_hull' => $ship->hull,
                'current_hull' => $ship->hull,
            ]);
        }

        // Resolve team combat
        $result = $this->resolveTeamCombat($combatSession, $challenge);

        return [
            'success' => true,
            'combat_session' => $combatSession,
            'result' => $result,
        ];
    }

    /**
     * Resolve team-based combat
     */
    private function resolveTeamCombat(CombatSession $session, PvPChallenge $challenge): array
    {
        $attackers = $session->attackers()->with(['player', 'playerShip'])->get();
        $defenders = $session->defenders()->with(['player', 'playerShip'])->get();

        $combatLog = [];
        $round = 1;

        $combatLog[] = ['type' => 'header', 'message' => '⚔️  TEAM COMBAT INITIATED  ⚔️'];
        $combatLog[] = ['type' => 'info', 'message' => 'ATTACKERS:'];
        foreach ($attackers as $attacker) {
            $combatLog[] = [
                'type' => 'info',
                'message' => "  • {$attacker->player->call_sign}: {$attacker->playerShip->name} (Hull: {$attacker->current_hull})",
            ];
        }
        $combatLog[] = ['type' => 'info', 'message' => 'DEFENDERS:'];
        foreach ($defenders as $defender) {
            $combatLog[] = [
                'type' => 'info',
                'message' => "  • {$defender->player->call_sign}: {$defender->playerShip->name} (Hull: {$defender->current_hull})",
            ];
        }
        $combatLog[] = ['type' => 'divider', 'message' => str_repeat('─', 50)];

        // Combat loop
        while ($attackers->where('current_hull', '>', 0)->count() > 0 &&
               $defenders->where('current_hull', '>', 0)->count() > 0 &&
               $round <= 100) {

            $combatLog[] = ['type' => 'round', 'message' => "\n🔹 ROUND {$round}"];

            // Attackers' turn
            foreach ($attackers->where('current_hull', '>', 0) as $attacker) {
                // Target weakest defender
                $target = $defenders->where('current_hull', '>', 0)->sortBy('current_hull')->first();

                if (! $target) {
                    break;
                }

                $damage = $this->calculateDamage($attacker->playerShip->weapons);
                $target->takeDamage($damage);
                $attacker->recordDamageDealt($damage);

                $combatLog[] = [
                    'type' => 'attack',
                    'message' => "  ➜ {$attacker->player->call_sign} fires at {$target->player->call_sign} for {$damage} damage! (Hull: {$target->current_hull})",
                ];

                if (! $target->isAlive()) {
                    $combatLog[] = [
                        'type' => 'destroyed',
                        'message' => "  💥 {$target->player->call_sign}'s ship DESTROYED!",
                    ];
                }
            }

            // Check if all defenders destroyed
            if ($defenders->where('current_hull', '>', 0)->count() === 0) {
                break;
            }

            // Defenders' turn
            foreach ($defenders->where('current_hull', '>', 0) as $defender) {
                // Target weakest attacker
                $target = $attackers->where('current_hull', '>', 0)->sortBy('current_hull')->first();

                if (! $target) {
                    break;
                }

                $damage = $this->calculateDamage($defender->playerShip->weapons);
                $target->takeDamage($damage);
                $defender->recordDamageDealt($damage);

                $combatLog[] = [
                    'type' => 'attack',
                    'message' => "  ⬅ {$defender->player->call_sign} fires at {$target->player->call_sign} for {$damage} damage! (Hull: {$target->current_hull})",
                ];

                if (! $target->isAlive()) {
                    $combatLog[] = [
                        'type' => 'destroyed',
                        'message' => "  💥 {$target->player->call_sign}'s ship DESTROYED!",
                    ];
                }
            }

            $round++;
        }

        $combatLog[] = ['type' => 'divider', 'message' => str_repeat('─', 50)];

        // Determine victors and losers
        $attackersAlive = $attackers->where('current_hull', '>', 0)->count() > 0;
        $defendersAlive = $defenders->where('current_hull', '>', 0)->count() > 0;

        $victors = $attackersAlive ? $attackers : $defenders;
        $losers = $attackersAlive ? $defenders : $attackers;
        $victorType = $attackersAlive ? 'attacker' : 'defender';

        $combatLog[] = [
            'type' => 'victory',
            'message' => '🏆 '.($attackersAlive ? 'ATTACKERS' : 'DEFENDERS').' are VICTORIOUS!',
        ];

        // Update ship hulls
        foreach ($victors as $victor) {
            $victor->playerShip->update(['hull' => $victor->current_hull]);
        }
        foreach ($losers as $loser) {
            $loser->playerShip->update(['hull' => 0]);
        }

        // Calculate and distribute rewards
        $baseXP = 150; // Base team combat XP
        $totalWager = $challenge->wager_credits * ($attackers->count() + $defenders->count());
        $xpPerVictor = (int) ceil($baseXP / $victors->count());
        $creditsPerVictor = $victors->count() > 0 ? ($totalWager / $victors->count()) : 0;

        foreach ($victors as $victor) {
            $victor->awardRewards($xpPerVictor, $creditsPerVictor);
        }

        $combatLog[] = [
            'type' => 'rewards',
            'message' => "⭐ Each victor earned: {$xpPerVictor} XP".($creditsPerVictor > 0 ? ' and $'.number_format($creditsPerVictor, 2) : ''),
        ];

        // Handle deaths
        $deathResults = [];
        foreach ($losers as $loser) {
            $deathResult = $this->deathService->processPlayerDeath($loser->player, $loser->playerShip);
            $deathResults[] = $deathResult;
            $combatLog[] = [
                'type' => 'death',
                'message' => "☠️  {$loser->player->call_sign}'s ship was destroyed and they were sent to {$deathResult['respawn_location']->name}",
            ];
        }

        // Complete combat session
        $victorPlayerId = $victors->first()->player_id;
        $session->update([
            'combat_log' => $combatLog,
            'current_round' => $round,
        ]);
        $session->complete($victorType, $victorPlayerId);

        // Mark challenge as completed
        $challenge->complete();

        return [
            'victor_team' => $victorType,
            'victors' => $victors->map(fn ($v) => [
                'player' => $v->player,
                'hull_remaining' => $v->current_hull,
                'damage_dealt' => $v->damage_dealt,
                'xp_earned' => $xpPerVictor,
                'credits_earned' => $creditsPerVictor,
            ]),
            'losers' => $losers->map(fn ($l) => [
                'player' => $l->player,
                'damage_dealt' => $l->damage_dealt,
            ]),
            'rounds' => $round,
            'combat_log' => $combatLog,
            'death_results' => $deathResults,
        ];
    }

    /**
     * Calculate damage with ±20% randomization
     */
    private function calculateDamage(int $weaponsPower): int
    {
        $variance = $weaponsPower * 0.20;
        $min = (int) floor($weaponsPower - $variance);
        $max = (int) ceil($weaponsPower + $variance);

        return rand($min, $max);
    }

    /**
     * Get current team size (including accepted invitations)
     */
    private function getTeamSize(PvPChallenge $challenge, string $side): int
    {
        // 1 for the original challenger/target
        $baseSize = 1;

        // Count accepted invitations for this side
        $acceptedCount = $challenge->acceptedInvitations()
            ->where('side', $side)
            ->count();

        return $baseSize + $acceptedCount;
    }
}

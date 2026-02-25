<?php

namespace App\Services;

use App\Models\CombatParticipant;
use App\Models\CombatSession;
use App\Models\Player;
use App\Models\PvPChallenge;

class PvPCombatService
{
    public function __construct(
        private readonly PlayerDeathService $deathService
    ) {}

    /**
     * Create a PvP challenge
     */
    public function createChallenge(
        Player $challenger,
        Player $target,
        ?string $message = null,
        float $wagerCredits = 0,
        int $maxTeamSize = 1
    ): array {
        // Validation
        if ($challenger->id === $target->id) {
            return ['success' => false, 'message' => 'You cannot challenge yourself'];
        }

        // Check if players are at the same location
        if ($challenger->current_poi_id !== $target->current_poi_id) {
            return ['success' => false, 'message' => 'You must be at the same location as your target'];
        }

        // Check if challenger has active ship
        if (! $challenger->activeShip) {
            return ['success' => false, 'message' => 'You need an active ship to issue a challenge'];
        }

        // Check if target has active ship
        if (! $target->activeShip) {
            return ['success' => false, 'message' => 'Target player does not have an active ship'];
        }

        // Check for existing pending challenge
        $existingChallenge = PvPChallenge::where('challenger_id', $challenger->id)
            ->where('target_id', $target->id)
            ->where('status', 'pending')
            ->first();

        if ($existingChallenge && ! $existingChallenge->isExpired()) {
            return ['success' => false, 'message' => 'You already have a pending challenge with this player'];
        }

        // Check wager
        if ($wagerCredits > 0) {
            if ($challenger->credits < $wagerCredits) {
                return ['success' => false, 'message' => 'Insufficient credits for wager'];
            }
            if ($target->credits < $wagerCredits) {
                return ['success' => false, 'message' => 'Target player cannot match the wager'];
            }
        }

        // Create challenge
        $challenge = PvPChallenge::create([
            'challenger_id' => $challenger->id,
            'target_id' => $target->id,
            'message' => $message,
            'wager_credits' => $wagerCredits,
            'max_team_size' => $maxTeamSize,
            'challenge_poi_id' => $challenger->current_poi_id,
        ]);

        return [
            'success' => true,
            'challenge' => $challenge,
            'message' => 'Challenge issued successfully',
        ];
    }

    /**
     * Accept a PvP challenge and initiate combat
     */
    public function acceptChallenge(PvPChallenge $challenge, Player $acceptingPlayer): array
    {
        // Validation
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

        // Check location
        $challenger = $challenge->challenger;
        if ($challenger->current_poi_id !== $acceptingPlayer->current_poi_id) {
            return ['success' => false, 'message' => 'You are no longer at the same location as the challenger'];
        }

        // Check both players have active ships
        $challengerShip = $challenger->activeShip;
        $targetShip = $acceptingPlayer->activeShip;

        if (! $challengerShip) {
            return ['success' => false, 'message' => 'Challenger no longer has an active ship'];
        }

        if (! $targetShip) {
            return ['success' => false, 'message' => 'You need an active ship to accept this challenge'];
        }

        // Deduct wagers if applicable
        if ($challenge->wager_credits > 0) {
            $challenger->deductCredits($challenge->wager_credits);
            $acceptingPlayer->deductCredits($challenge->wager_credits);
        }

        // Accept challenge
        $challenge->accept();

        // Create combat session
        $combatSession = CombatSession::create([
            'combat_type' => 'pvp',
            'poi_id' => $challenger->current_poi_id,
            'pvp_challenge_id' => $challenge->id,
        ]);

        // Add participants
        CombatParticipant::create([
            'combat_session_id' => $combatSession->id,
            'player_id' => $challenger->id,
            'player_ship_id' => $challengerShip->id,
            'side' => 'attacker',
            'starting_hull' => $challengerShip->hull,
            'current_hull' => $challengerShip->hull,
        ]);

        CombatParticipant::create([
            'combat_session_id' => $combatSession->id,
            'player_id' => $acceptingPlayer->id,
            'player_ship_id' => $targetShip->id,
            'side' => 'defender',
            'starting_hull' => $targetShip->hull,
            'current_hull' => $targetShip->hull,
        ]);

        // Resolve combat
        $result = $this->resolvePvPCombat($combatSession);

        return [
            'success' => true,
            'combat_session' => $combatSession,
            'result' => $result,
        ];
    }

    /**
     * Decline a PvP challenge
     */
    public function declineChallenge(PvPChallenge $challenge, Player $decliningPlayer): array
    {
        if ($challenge->target_id !== $decliningPlayer->id) {
            return ['success' => false, 'message' => 'You are not the target of this challenge'];
        }

        if ($challenge->status !== 'pending') {
            return ['success' => false, 'message' => 'This challenge is no longer available'];
        }

        $challenge->decline();

        return [
            'success' => true,
            'message' => 'Challenge declined',
        ];
    }

    /**
     * Resolve PvP combat between two players
     */
    private function resolvePvPCombat(CombatSession $session): array
    {
        $participants = $session->participants()->with(['player', 'playerShip'])->get();
        $attacker = $participants->where('side', 'attacker')->first();
        $defender = $participants->where('side', 'defender')->first();

        $combatLog = [];
        $round = 1;

        $combatLog[] = ['type' => 'header', 'message' => '⚔️  PVP COMBAT INITIATED  ⚔️'];
        $combatLog[] = [
            'type' => 'info',
            'message' => "{$attacker->player->call_sign}: {$attacker->playerShip->name} (Hull: {$attacker->current_hull})",
        ];
        $combatLog[] = [
            'type' => 'info',
            'message' => "{$defender->player->call_sign}: {$defender->playerShip->name} (Hull: {$defender->current_hull})",
        ];
        $combatLog[] = ['type' => 'divider', 'message' => str_repeat('─', 50)];

        // Combat loop
        while ($attacker->isAlive() && $defender->isAlive() && $round <= 100) {
            $combatLog[] = ['type' => 'round', 'message' => "\n🔹 ROUND {$round}"];

            // Attacker's turn
            $damage = $this->calculateDamage($attacker->playerShip->weapons);
            $defender->takeDamage($damage);
            $attacker->recordDamageDealt($damage);

            $combatLog[] = [
                'type' => 'attack',
                'message' => "  ➜ {$attacker->player->call_sign} fires for {$damage} damage! ({$defender->player->call_sign} Hull: {$defender->current_hull})",
            ];

            if (! $defender->isAlive()) {
                $combatLog[] = [
                    'type' => 'destroyed',
                    'message' => "  💥 {$defender->player->call_sign}'s ship DESTROYED!",
                ];
                break;
            }

            // Defender's turn
            $damage = $this->calculateDamage($defender->playerShip->weapons);
            $attacker->takeDamage($damage);
            $defender->recordDamageDealt($damage);

            $combatLog[] = [
                'type' => 'attack',
                'message' => "  ⬅ {$defender->player->call_sign} fires for {$damage} damage! ({$attacker->player->call_sign} Hull: {$attacker->current_hull})",
            ];

            if (! $attacker->isAlive()) {
                $combatLog[] = [
                    'type' => 'destroyed',
                    'message' => "  💥 {$attacker->player->call_sign}'s ship DESTROYED!",
                ];
                break;
            }

            $round++;
        }

        $combatLog[] = ['type' => 'divider', 'message' => str_repeat('─', 50)];

        // Determine victor
        $victor = $attacker->isAlive() ? $attacker : $defender;
        $loser = $attacker->isAlive() ? $defender : $attacker;

        $combatLog[] = [
            'type' => 'victory',
            'message' => "🏆 {$victor->player->call_sign} is VICTORIOUS!",
        ];

        // Update ship hull in database
        $victor->playerShip->update(['hull' => $victor->current_hull]);
        $loser->playerShip->update(['hull' => 0]);

        // Calculate rewards
        $xpReward = 100; // Base PvP XP
        $creditReward = 0;

        // Add wager to rewards if applicable
        $challenge = $session->pvpChallenge;
        if ($challenge && $challenge->wager_credits > 0) {
            $creditReward = $challenge->wager_credits * 2; // Winner takes both wagers
        }

        // Award to victor
        $victor->awardRewards($xpReward, $creditReward);

        $combatLog[] = [
            'type' => 'rewards',
            'message' => "⭐ {$victor->player->call_sign} earned: {$xpReward} XP".($creditReward > 0 ? ' and $'.number_format($creditReward) : ''),
        ];

        // Handle loser's death
        $deathResult = $this->deathService->processPlayerDeath($loser->player, $loser->playerShip);
        $combatLog[] = [
            'type' => 'death',
            'message' => "☠️  {$loser->player->call_sign}'s ship was destroyed and they were sent to {$deathResult['respawn_location']->name}",
        ];

        // Complete combat session
        $session->update([
            'combat_log' => $combatLog,
            'current_round' => $round,
        ]);
        $session->complete('player', $victor->player_id);

        // Mark challenge as completed
        if ($challenge) {
            $challenge->complete();
        }

        return [
            'victor' => $victor->player,
            'victor_hull_remaining' => $victor->current_hull,
            'loser' => $loser->player,
            'rounds' => $round,
            'combat_log' => $combatLog,
            'xp_earned' => $xpReward,
            'credits_earned' => $creditReward,
            'death_result' => $deathResult,
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
}

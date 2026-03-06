<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\Player;
use App\Models\PlayerContractReputation;

/**
 * ReputationService
 *
 * Manages player contract reputation tracking and scoring.
 * Reputation ranges from 0-100, starting at 50 (neutral).
 */
class ReputationService
{
    /**
     * Get player's contract reputation score (0-100)
     * Creates default record if doesn't exist
     */
    public function getPlayerReputation(Player $player): int
    {
        $rep = PlayerContractReputation::firstOrCreate(
            ['player_id' => $player->id],
            ['reliability_score' => 50]
        );

        return $rep->reliability_score;
    }

    /**
     * Record a successful contract completion
     * Increases reputation up to 100
     */
    public function recordSuccess(Player $player, Contract $contract): void
    {
        $rep = PlayerContractReputation::firstOrCreate(
            ['player_id' => $player->id],
            ['reliability_score' => 50]
        );

        $rep->increment('completed_count');
        $rep->reliability_score = min(100, $rep->reliability_score + 2);
        $rep->save();
    }

    /**
     * Record a contract failure (deadline exceeded)
     * Reduces reputation, applies cumulative penalty
     */
    public function recordFailure(Player $player, Contract $contract, string $reason = null): void
    {
        $rep = PlayerContractReputation::firstOrCreate(
            ['player_id' => $player->id],
            ['reliability_score' => 50]
        );

        $rep->increment('failed_count');
        $rep->failure_penalty = min(50, $rep->failure_penalty + 5);
        $rep->reliability_score = max(0, 50 - $rep->failure_penalty - $rep->abandonment_penalty);
        $rep->save();
    }

    /**
     * Record an abandoned contract (accepted but never completed or failed)
     * Applies steeper penalty than failure
     */
    public function recordAbandonment(Player $player, Contract $contract): void
    {
        $rep = PlayerContractReputation::firstOrCreate(
            ['player_id' => $player->id],
            ['reliability_score' => 50]
        );

        $rep->increment('abandoned_count');
        $rep->abandonment_penalty = min(50, $rep->abandonment_penalty + 8);
        $rep->reliability_score = max(0, 50 - $rep->failure_penalty - $rep->abandonment_penalty);
        $rep->save();
    }

    /**
     * Reset reputation to default (admin/debug only)
     */
    public function resetReputation(Player $player): void
    {
        $rep = PlayerContractReputation::firstOrCreate(
            ['player_id' => $player->id],
        );

        $rep->update([
            'reliability_score' => 50,
            'completed_count' => 0,
            'failed_count' => 0,
            'abandoned_count' => 0,
            'expired_count' => 0,
            'failure_penalty' => 0,
            'abandonment_penalty' => 0,
        ]);
    }
}

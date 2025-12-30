<?php

namespace App\Console\Tui\Handlers;

use App\Models\Plan;
use App\Models\Player;

class PlanPurchaseHandler
{
    public function __construct(private Player $player)
    {
    }

    /**
     * Execute a plan purchase
     */
    public function executePurchase(Plan $plan): array
    {
        // Attempt purchase
        $result = $this->player->purchasePlan($plan);

        if ($result['success']) {
            // Reload player data
            $this->player->refresh();
            $this->player->load('plans');

            $ownedCount = $this->player->getPlanCount($plan->id);

            return [
                'success' => true,
                'message' => "Purchased {$plan->getFullName()}! You now own {$ownedCount}x - Price: " . number_format($plan->price, 2) . " credits",
                'plan' => $plan->getFullName(),
                'owned_count' => $ownedCount,
                'price' => $plan->price,
            ];
        }

        return $result;
    }

    /**
     * Get plan ownership count
     */
    public function getOwnedCount(int $planId): int
    {
        return $this->player->getPlanCount($planId);
    }

    /**
     * Calculate total bonus for a plan
     */
    public function calculateBonus(Plan $plan, int $ownedCount): int
    {
        return $ownedCount * $plan->additional_levels;
    }

    /**
     * Calculate projected bonus after purchase
     */
    public function calculateProjectedBonus(Plan $plan): int
    {
        $ownedCount = $this->getOwnedCount($plan->id);
        return ($ownedCount + 1) * $plan->additional_levels;
    }
}

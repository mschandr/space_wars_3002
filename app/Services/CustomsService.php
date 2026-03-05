<?php

namespace App\Services;

use App\Enums\Customs\CustomsOutcome;
use App\Models\CustomsOfficial;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;

/**
 * Handles customs checks on arrival at inhabited systems
 *
 * Scans cargo for illegal items, applies detection based on hidden holds,
 * determines outcomes (cleared, fined, seized, bribed, or impounded).
 */
class CustomsService
{
    /**
     * Perform a customs check on a player arriving at a POI
     *
     * @return array {
     *   outcome: CustomsOutcome,
     *   message: string,
     *   fine_amount: int,
     *   seized_items: [],
     *   can_bribe: bool,
     *   bribe_amount: int (if can_bribe)
     * }
     */
    public function performCheck(Player $player, PlayerShip $ship, PointOfInterest $destination): array
    {
        // Check if destination has customs
        $official = CustomsOfficial::where('poi_id', $destination->id)->first();

        if (!$official) {
            // No customs at this location
            return [
                'outcome' => CustomsOutcome::CLEARED,
                'message' => 'Station has no customs authority. Free to dock.',
                'fine_amount' => 0,
                'seized_items' => [],
                'can_bribe' => false,
            ];
        }

        // Scan cargo for illegal items
        $illegalItems = $ship->cargo()
            ->whereHas('mineral', function ($query) {
                $query->where(function ($subquery) {
                    $subquery->where('is_illegal', true)
                        ->orWhere('category', 'black');
                });
            })
            ->get();

        // If no illegal items, cleared
        if ($illegalItems->isEmpty()) {
            return [
                'outcome' => CustomsOutcome::CLEARED,
                'message' => 'Cargo scan complete. You\'re cleared to dock.',
                'fine_amount' => 0,
                'seized_items' => [],
                'can_bribe' => false,
            ];
        }

        // Illegal items found - determine detection and outcome
        $totalIllegalValue = $illegalItems->sum(function ($cargo) {
            return $cargo->mineral->base_value * $cargo->quantity;
        });

        // Compute detection chance (affected by hidden holds)
        $detectionChance = $this->computeDetectionChance($official, $ship);

        // Roll for detection
        $detected = (random_int(0, 10000) / 10000) < $detectionChance;

        if (!$detected) {
            return [
                'outcome' => CustomsOutcome::CLEARED,
                'message' => 'Cargo scan complete. You\'re cleared to dock.',
                'fine_amount' => 0,
                'seized_items' => [],
                'can_bribe' => false,
            ];
        }

        // Detected! Now determine outcome
        // Check if can bribe
        $canBribe = $official->canBeBribed() && $player->credits >= $official->bribe_threshold;

        // Determine severity of outcome
        if ($official->isVeryStrict() && $totalIllegalValue > 100000) {
            // Egregious violation - impound
            return [
                'outcome' => CustomsOutcome::IMPOUNDED,
                'message' => 'Illegal cargo detected! Your ship is impounded pending investigation.',
                'fine_amount' => $totalIllegalValue,
                'seized_items' => $illegalItems->pluck('mineral.name')->toArray(),
                'can_bribe' => false,
                'severity' => 'IMPOUNDED',
            ];
        }

        if ($official->severity > 0.6) {
            // Strict official - seize cargo
            return [
                'outcome' => CustomsOutcome::CARGO_SEIZED,
                'message' => 'Illegal items detected. Your cargo is seized.',
                'fine_amount' => (int) ($totalIllegalValue * 0.5),
                'seized_items' => $illegalItems->pluck('mineral.name')->toArray(),
                'can_bribe' => $canBribe,
                'bribe_amount' => $canBribe ? (int) ($totalIllegalValue * 0.3) : 0,
            ];
        }

        // Lenient official - fine
        return [
            'outcome' => CustomsOutcome::FINED,
            'message' => 'Illegal cargo detected. You\'ve been fined.',
            'fine_amount' => (int) ($totalIllegalValue * 0.25),
            'seized_items' => [],
            'can_bribe' => $canBribe,
            'bribe_amount' => $canBribe ? (int) ($totalIllegalValue * 0.2) : 0,
        ];
    }

    /**
     * Compute effective detection chance
     *
     * Base chance from official.detection_skill, reduced by hidden cargo holds
     */
    private function computeDetectionChance(CustomsOfficial $official, PlayerShip $ship): float
    {
        $baseChance = (float) $official->detection_skill;

        // Get hidden cargo hold level from components
        $hiddenHoldLevel = $ship->components()
            ->whereHas('component', function ($query) {
                $query->where('type', 'hidden_cargo_hold');
            })
            ->sum('level');

        // Each level reduces detection by 15%
        $reduction = $hiddenHoldLevel * 0.15;
        $effectiveChance = max(0.05, $baseChance - $reduction);  // Minimum 5% chance

        return min(1.0, $effectiveChance);  // Cap at 100%
    }

    /**
     * Apply a bribe to avoid fine
     *
     * Only possible if outcome allows it
     */
    public function applyBribe(Player $player, CustomsOfficial $official, int $amount): bool
    {
        // Check if player has enough credits
        if ($player->credits < $amount) {
            return false;
        }

        // Deduct bribe
        $player->deductCredits($amount);

        // Bribe success - update official's reputation
        // (In a more complex system, failed bribes might attract attention)

        return true;
    }

    /**
     * Compute the server-authoritative fine amount for a ship's current illegal cargo.
     *
     * Uses the same logic as performCheck() without re-rolling detection.
     * Call this when the player has already been caught and is accepting penalties.
     */
    public function computeFineAmount(PlayerShip $ship, CustomsOfficial $official): int
    {
        $illegalItems = $ship->cargo()
            ->whereHas('mineral', function ($query) {
                $query->where(function ($subquery) {
                    $subquery->where('is_illegal', true)
                        ->orWhere('category', 'black');
                });
            })
            ->get();

        if ($illegalItems->isEmpty()) {
            return 0;
        }

        $totalIllegalValue = $illegalItems->sum(function ($cargo) {
            return $cargo->mineral->base_value * $cargo->quantity;
        });

        $multiplier = $official->severity > 0.6 ? 0.5 : 0.25;

        return (int) ($totalIllegalValue * $multiplier);
    }

    /**
     * Seize illegal cargo from a ship
     *
     * Removes items and applies any fines
     */
    public function seizeIllegalCargo(PlayerShip $ship, Player $player, int $fine): void
    {
        // Find and delete all illegal cargo
        $ship->cargo()
            ->whereHas('mineral', function ($query) {
                $query->where('is_illegal', true)
                    ->orWhere('category', 'black');
            })
            ->delete();

        // Apply fine
        if ($fine > 0) {
            $player->deductCredits($fine);
        }
    }
}

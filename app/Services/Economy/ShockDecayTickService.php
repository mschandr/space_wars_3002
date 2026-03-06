<?php

namespace App\Services\Economy;

use App\Models\EconomicShock;
use App\Models\Galaxy;

/**
 * ShockDecayTickService
 *
 * Processes decay and deactivation of economic shocks in a tick.
 * - Checks all active shocks for decay threshold (< 1% remaining)
 * - Marks fully decayed shocks as inactive
 * - Uses Unix timestamp as tick number (consistent with EconomicShock::getEffectiveMagnitude)
 */
class ShockDecayTickService
{
    /**
     * Process shock decay for this tick
     *
     * @param Galaxy|null $galaxy Filter to single galaxy, or null for all
     * @param bool $dryRun If true, does not write to database
     * @return array Results: ['checked' => int, 'deactivated' => int, 'errors' => []]
     */
    public function processTick(?Galaxy $galaxy = null, bool $dryRun = false): array
    {
        $query = EconomicShock::active();

        if ($galaxy) {
            $query->where('galaxy_id', $galaxy->id);
        }

        $shocks = $query->get();

        $currentTick = now()->timestamp;

        $results = [
            'checked' => 0,
            'deactivated' => 0,
            'errors' => [],
        ];

        foreach ($shocks as $shock) {
            try {
                $results['checked']++;

                if ($shock->isFullyDecayed($currentTick)) {
                    if (!$dryRun) {
                        $shock->deactivate();
                    }
                    $results['deactivated']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'shock_id' => $shock->id,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}

<?php

namespace App\Services\Crew;

use App\Enums\Crew\CrewAlignment;
use App\Models\PlayerShip;

/**
 * Computes ship persona based on assigned crew members
 *
 * The ship persona represents the collective personality and capabilities
 * of the crew, affecting vendor access, black market visibility, and more.
 */
class ShipPersonaService
{
    /**
     * Compute the ship persona for a given player ship
     *
     * Returns array with:
     * - overall_alignment: 'lawful', 'neutral', or 'shady'
     * - shady_score: sum of crew shady scores
     * - vendor_bonuses: crew-provided bonuses
     * - total_shady_interactions: sum of crew.shady_actions
     * - black_market_visible: bool (true if threshold crossed, NEVER included in response if false)
     *
     * @param PlayerShip $ship Ship with crew loaded
     * @return array Ship persona data
     */
    public function computePersona(PlayerShip $ship): array
    {
        // Load crew if not already loaded
        $crew = $ship->crew;
        if ($crew->isEmpty()) {
            $crew = $ship->load('crew')->crew;
        }

        // Compute overall alignment from crew mix
        if ($crew->isEmpty()) {
            $overallAlignment = 'neutral';
            $shadyScore = 0;
            $totalShadyInteractions = 0;
            $vendorBonuses = [];
        } else {
            // Sum shady scores
            $shadyScore = $crew->sum(fn ($member) => $member->alignment->shadyScore());

            // Determine overall alignment from aggregate shady score
            if ($shadyScore < -($crew->count() / 2)) {
                $overallAlignment = 'lawful';
            } elseif ($shadyScore > ($crew->count() / 2)) {
                $overallAlignment = 'shady';
            } else {
                $overallAlignment = 'neutral';
            }

            // Sum total shady interactions
            $totalShadyInteractions = $crew->sum('shady_actions');

            // Compute vendor bonuses from crew
            $vendorBonuses = $this->computeVendorBonuses($crew);
        }

        // Check if black market is visible
        $threshold = config('economy.black_market.visibility_threshold', 10);
        $blackMarketVisible = $totalShadyInteractions >= $threshold;

        $persona = [
            'overall_alignment' => $overallAlignment,
            'shady_score' => $shadyScore,
            'vendor_bonuses' => $vendorBonuses,
            'total_shady_interactions' => $totalShadyInteractions,
        ];

        // Only include black_market_visible if true (never expose false status)
        if ($blackMarketVisible) {
            $persona['black_market_visible'] = true;
        }

        return $persona;
    }

    /**
     * Compute vendor bonuses from crew
     *
     * Different crew roles and alignments provide different bonuses:
     * - Logistics Officer → trading_discount
     * - Chief Engineer → repair_discount
     * - Shady crew → black market access
     * - Neutral crew → balanced bonuses
     *
     * @param \Illuminate\Support\Collection $crew Crew members
     * @return array Vendor bonuses [bonus_name => value]
     */
    private function computeVendorBonuses($crew): array
    {
        $bonuses = [];

        // Base discounts by role
        $tradeCount = $crew->filter(fn ($m) => $m->role->value === 'logistics_officer')->count();
        if ($tradeCount > 0) {
            $bonuses['trading_discount'] = min(0.15, $tradeCount * 0.05); // max 15%
        }

        $engineerCount = $crew->filter(fn ($m) => $m->role->value === 'chief_engineer')->count();
        if ($engineerCount > 0) {
            $bonuses['repair_discount'] = min(0.15, $engineerCount * 0.05); // max 15%
        }

        // Negotiation from Science/Tactical officers
        $diplomatCount = $crew->filter(fn ($m) =>
            in_array($m->role->value, ['science_officer', 'tactical_officer'])
        )->count();
        if ($diplomatCount > 0) {
            $bonuses['negotiation_power'] = min(0.2, $diplomatCount * 0.1); // max 20% improvement
        }

        // Shady crew unlock rare items
        $shadyCount = $crew->filter(fn ($m) => $m->alignment === CrewAlignment::SHADY)->count();
        if ($shadyCount > 0) {
            $bonuses['shady_vendor_access'] = true;
        }

        return $bonuses;
    }
}

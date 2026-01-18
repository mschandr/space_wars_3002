<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PointOfInterest;

class TravelNotificationService
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Process all travel-related notifications when player arrives at a location
     */
    public function processArrivalNotifications(Player $player, PointOfInterest $destination): array
    {
        $notifications = [];

        // Check for colonization opportunities
        if ($this->hasColonizationOpportunities($destination)) {
            $this->notificationService->alertColonizationOpportunity($player, $destination);
            $notifications[] = 'colonization_opportunity';
        }

        // Check for mining opportunities (ice giants, asteroid belts)
        if ($this->hasMiningOpportunities($destination)) {
            $miningInfo = $this->getMiningOpportunityInfo($destination);
            $notifications[] = 'mining_opportunity';

            // Could add a mining opportunity notification here if desired
        }

        // Check for danger (pirates, other players)
        // This would be integrated with the pirate system

        return $notifications;
    }

    /**
     * Check if the current system has colonization opportunities
     */
    private function hasColonizationOpportunities(PointOfInterest $poi): bool
    {
        $star = $poi->star;

        if (! $star) {
            return false;
        }

        return $star->pointsOfInterest()
            ->where('is_colonizable', true)
            ->where('is_colonized', false)
            ->where('habitability_score', '>', 0.3)
            ->exists();
    }

    /**
     * Check if the current location or system has mining opportunities
     */
    private function hasMiningOpportunities(PointOfInterest $poi): bool
    {
        // Check if current POI is mineable
        if (in_array($poi->planet_class, ['ice_giant', 'asteroid_field'])) {
            $deposits = $poi->mineral_deposits ?? [];

            return ! empty($deposits);
        }

        // Check if system has mineable locations
        $star = $poi->star;
        if (! $star) {
            return false;
        }

        return $star->pointsOfInterest()
            ->whereIn('planet_class', ['ice_giant', 'asteroid_field'])
            ->whereNotNull('mineral_deposits')
            ->exists();
    }

    /**
     * Get mining opportunity details
     */
    private function getMiningOpportunityInfo(PointOfInterest $poi): array
    {
        $star = $poi->star;
        if (! $star) {
            return [];
        }

        $mineableLocations = $star->pointsOfInterest()
            ->whereIn('planet_class', ['ice_giant', 'asteroid_field'])
            ->whereNotNull('mineral_deposits')
            ->get();

        $info = [];
        foreach ($mineableLocations as $location) {
            $deposits = $location->mineral_deposits ?? [];
            $info[] = [
                'name' => $location->name,
                'type' => $location->planet_class,
                'minerals' => array_keys($deposits),
                'has_quantium' => isset($deposits['Quantium']),
            ];
        }

        return $info;
    }

    /**
     * Get a summary message for display in the travel interface
     */
    public function getArrivalSummary(Player $player, PointOfInterest $destination): string
    {
        $messages = [];

        // Colonization opportunities
        if ($this->hasColonizationOpportunities($destination)) {
            $star = $destination->star;
            $count = $star->pointsOfInterest()
                ->where('is_colonizable', true)
                ->where('is_colonized', false)
                ->where('habitability_score', '>', 0.3)
                ->count();

            $messages[] = "🌍 {$count} uninhabited ".($count === 1 ? 'world' : 'worlds').' detected';
        }

        // Mining opportunities
        $miningInfo = $this->getMiningOpportunityInfo($destination);
        if (! empty($miningInfo)) {
            $hasQuantium = collect($miningInfo)->contains('has_quantium', true);
            $mineralCount = count($miningInfo);

            $msg = "⛏️ {$mineralCount} mining ".($mineralCount === 1 ? 'location' : 'locations');
            if ($hasQuantium) {
                $msg .= ' (Quantium detected!)';
            }
            $messages[] = $msg;
        }

        return empty($messages) ? '' : implode(' | ', $messages);
    }
}

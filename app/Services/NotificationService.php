<?php

namespace App\Services;

use App\Models\Colony;
use App\Models\Player;
use App\Models\PlayerNotification;
use App\Models\PointOfInterest;

class NotificationService
{
    /**
     * Create a notification for a player
     */
    public function createNotification(
        Player $player,
        string $type,
        string $severity,
        string $title,
        string $message,
        ?Colony $colony = null,
        ?PointOfInterest $poi = null,
        ?array $data = null
    ): PlayerNotification {
        return PlayerNotification::create([
            'player_id' => $player->id,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'colony_id' => $colony?->id,
            'poi_id' => $poi?->id,
            'data' => $data,
        ]);
    }

    /**
     * Alert player about low Quantium on colony
     */
    public function alertLowQuantium(Colony $colony): void
    {
        $player = $colony->player;
        $quantiumLevel = $colony->quantium_storage;

        // Only alert once when crossing threshold
        $existingAlert = PlayerNotification::where('player_id', $player->id)
            ->where('colony_id', $colony->id)
            ->where('type', 'low_quantium')
            ->where('is_read', false)
            ->exists();

        if (!$existingAlert && $quantiumLevel < 24) {
            $this->createNotification(
                $player,
                'low_quantium',
                $quantiumLevel < 12 ? 'critical' : 'warning',
                'Low Warp Gate Fuel',
                "Colony '{$colony->name}' at {$colony->poi->name} has only {$quantiumLevel} Quantium remaining. Gates will shut down at 0!",
                $colony,
                $colony->poi,
                ['quantium_remaining' => $quantiumLevel]
            );
        }
    }

    /**
     * Alert player that warp gate has shut down
     */
    public function alertGateShutdown(Colony $colony): void
    {
        $this->createNotification(
            $colony->player,
            'gate_shutdown',
            'critical',
            'Warp Gate Offline',
            "🌀 The warp gate on {$colony->poi->name} has shut down due to lack of Quantium. You are no longer generating income!",
            $colony,
            $colony->poi,
            ['income_lost' => 600] // Base gate income
        );
    }

    /**
     * Alert player about pirate attack on colony
     */
    public function alertPirateAttack(Colony $colony, array $pirateFleetData): void
    {
        $defenseRating = $colony->buildings()
            ->where('building_type', 'orbital_defense')
            ->where('status', 'operational')
            ->sum('effects->defense_rating');

        $this->createNotification(
            $colony->player,
            'pirate_attack',
            'critical',
            'Pirate Raid Detected',
            "☠️ Pirates are attacking your colony '{$colony->name}' at {$colony->poi->name}! Fleet size: {$pirateFleetData['fleet_size']}, Your defenses: {$defenseRating}",
            $colony,
            $colony->poi,
            [
                'pirate_fleet' => $pirateFleetData,
                'defense_rating' => $defenseRating,
            ]
        );
    }

    /**
     * Alert player about another player attacking their colony
     */
    public function alertPlayerAttack(Colony $colony, Player $attacker, array $attackData): void
    {
        $defenseRating = $colony->buildings()
            ->where('building_type', 'orbital_defense')
            ->where('status', 'operational')
            ->sum('effects->defense_rating');

        $this->createNotification(
            $colony->player,
            'player_attack',
            'critical',
            'Colony Under Attack',
            "⚔️ Player '{$attacker->name}' is attacking your colony '{$colony->name}' at {$colony->poi->name}! Incoming fleet: {$attackData['fleet_size']} ships, Your defenses: {$defenseRating}",
            $colony,
            $colony->poi,
            [
                'attacker_id' => $attacker->id,
                'attacker_name' => $attacker->name,
                'attack_data' => $attackData,
                'defense_rating' => $defenseRating,
            ]
        );
    }

    /**
     * Alert player about incoming attack detected by sensors (early warning)
     */
    public function alertIncomingThreat(Colony $colony, string $threatType, array $threatData): void
    {
        // Only send if colony has sensor array
        $hasSensors = $colony->buildings()
            ->where('building_type', 'orbital_sensor')
            ->where('status', 'operational')
            ->exists();

        if (!$hasSensors) {
            return; // No early warning without sensors
        }

        $message = $threatType === 'player'
            ? "📡 Sensors detected incoming fleet from '{$threatData['attacker_name']}' heading to your colony '{$colony->name}'. ETA: {$threatData['eta']} turns"
            : "📡 Sensors detected pirate activity near your colony '{$colony->name}'. Possible raid imminent!";

        $this->createNotification(
            $colony->player,
            'threat_detected',
            'warning',
            'Incoming Threat Detected',
            $message,
            $colony,
            $colony->poi,
            $threatData
        );
    }

    /**
     * Alert player that their colony was captured
     */
    public function alertColonyCaptured(Colony $colony, Player $attacker): void
    {
        $this->createNotification(
            $colony->player,
            'colony_captured',
            'critical',
            'Colony Captured',
            "💀 Your colony '{$colony->name}' at {$colony->poi->name} has been captured by '{$attacker->name}'!",
            $colony,
            $colony->poi,
            [
                'attacker_id' => $attacker->id,
                'attacker_name' => $attacker->name,
                'population_lost' => $colony->population,
            ]
        );
    }

    /**
     * Alert player that their colony was destroyed
     */
    public function alertColonyDestroyed(Colony $colony, string $destroyedBy): void
    {
        $this->createNotification(
            $colony->player,
            'colony_destroyed',
            'critical',
            'Colony Destroyed',
            "💀 Your colony '{$colony->name}' at {$colony->poi->name} has been destroyed by {$destroyedBy}!",
            $colony,
            $colony->poi,
            [
                'destroyed_by' => $destroyedBy,
                'population_lost' => $colony->population,
            ]
        );
    }

    /**
     * Alert player that building construction is complete
     */
    public function alertBuildingComplete(Colony $colony, string $buildingName): void
    {
        $this->createNotification(
            $colony->player,
            'building_complete',
            'info',
            'Construction Complete',
            "✅ {$buildingName} construction completed on colony '{$colony->name}' at {$colony->poi->name}!",
            $colony,
            $colony->poi,
            ['building' => $buildingName]
        );
    }

    /**
     * Alert player about low food supply
     */
    public function alertLowFood(Colony $colony): void
    {
        $foodLevel = $colony->food_storage;

        // Only alert once when crossing threshold
        $existingAlert = PlayerNotification::where('player_id', $colony->player->id)
            ->where('colony_id', $colony->id)
            ->where('type', 'low_food')
            ->where('is_read', false)
            ->exists();

        if (!$existingAlert && $foodLevel < 100) {
            $this->createNotification(
                $colony->player,
                'low_food',
                $foodLevel < 50 ? 'critical' : 'warning',
                'Low Food Supply',
                "🌾 Colony '{$colony->name}' at {$colony->poi->name} has only {$foodLevel} food remaining. Population growth will stall!",
                $colony,
                $colony->poi,
                ['food_remaining' => $foodLevel]
            );
        }
    }

    /**
     * Alert player about low mineral supply
     */
    public function alertLowMinerals(Colony $colony): void
    {
        $mineralLevel = $colony->mineral_storage;

        // Only alert once when crossing threshold
        $existingAlert = PlayerNotification::where('player_id', $colony->player->id)
            ->where('colony_id', $colony->id)
            ->where('type', 'low_minerals')
            ->where('is_read', false)
            ->exists();

        if (!$existingAlert && $mineralLevel < 100) {
            $this->createNotification(
                $colony->player,
                'low_minerals',
                $mineralLevel < 50 ? 'critical' : 'warning',
                'Low Mineral Supply',
                "⛏️ Colony '{$colony->name}' at {$colony->poi->name} has only {$mineralLevel} minerals remaining. Construction halted!",
                $colony,
                $colony->poi,
                ['minerals_remaining' => $mineralLevel]
            );
        }
    }

    /**
     * Get unread notifications for a player
     */
    public function getUnreadNotifications(Player $player, ?string $severity = null)
    {
        $query = PlayerNotification::where('player_id', $player->id)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc');

        if ($severity) {
            $query->where('severity', $severity);
        }

        return $query->get();
    }

    /**
     * Get critical alerts for a player (to display prominently)
     */
    public function getCriticalAlerts(Player $player)
    {
        return $this->getUnreadNotifications($player, 'critical');
    }

    /**
     * Mark all notifications as read for a player
     */
    public function markAllAsRead(Player $player): void
    {
        PlayerNotification::where('player_id', $player->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Alert player about colonization opportunities in a system
     */
    public function alertColonizationOpportunity(Player $player, PointOfInterest $currentPOI): void
    {
        // Get the star system the player is in
        $star = $currentPOI->star;

        if (!$star) {
            return;
        }

        // Find colonizable, uninhabited planets in this system
        $colonizablePlanets = $star->pointsOfInterest()
            ->where('is_colonizable', true)
            ->where('is_colonized', false)
            ->where('habitability_score', '>', 0.3) // Minimum 30% habitability
            ->get();

        if ($colonizablePlanets->isEmpty()) {
            return;
        }

        // Check if we've already notified about this system
        $existingNotification = PlayerNotification::where('player_id', $player->id)
            ->where('poi_id', $currentPOI->id)
            ->where('type', 'colonization_opportunity')
            ->where('created_at', '>', now()->subDays(7)) // Don't spam within 7 days
            ->exists();

        if ($existingNotification) {
            return;
        }

        // Find the best planet (highest habitability)
        $bestPlanet = $colonizablePlanets->sortByDesc('habitability_score')->first();
        $habitabilityPercent = round($bestPlanet->habitability_score * 100);

        // Build list of all colonizable planets
        $planetList = $colonizablePlanets->map(function ($planet) {
            $hab = round($planet->habitability_score * 100);
            return "{$planet->name} ({$hab}% habitable)";
        })->join(', ');

        $message = "🌍 Uninhabited worlds detected in the {$star->name} system! ";

        if ($colonizablePlanets->count() === 1) {
            $message .= "{$bestPlanet->name} is {$habitabilityPercent}% habitable and ready for colonization.";
        } else {
            $message .= "{$colonizablePlanets->count()} planets available: {$planetList}. ";
            $message .= "Best candidate: {$bestPlanet->name} ({$habitabilityPercent}% habitable).";
        }

        // Add requirements hint
        $message .= " You'll need a Colony Ship to establish a settlement.";

        $this->createNotification(
            $player,
            'colonization_opportunity',
            'info',
            'Colonization Opportunity',
            $message,
            null,
            $bestPlanet,
            [
                'star_id' => $star->id,
                'star_name' => $star->name,
                'planet_count' => $colonizablePlanets->count(),
                'best_planet_id' => $bestPlanet->id,
                'best_habitability' => $bestPlanet->habitability_score,
                'planets' => $colonizablePlanets->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'habitability' => $p->habitability_score,
                    'planet_class' => $p->planet_class,
                    'temperature' => $p->temperature,
                ])->toArray(),
            ]
        );
    }

    /**
     * Clear old read notifications (cleanup)
     */
    public function clearOldNotifications(int $daysOld = 7): int
    {
        return PlayerNotification::where('is_read', true)
            ->where('read_at', '<', now()->subDays($daysOld))
            ->delete();
    }
}

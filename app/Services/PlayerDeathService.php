<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;

class PlayerDeathService
{
    /**
     * Process player death - ship destroyed in combat
     *
     * 1. Record what was lost
     * 2. Delete ship (cascades to cargo)
     * 3. Detach all upgrade plans
     * 4. Respawn at trading hub with shipyard (or fallback location)
     * 5. Keep credits and XP (with minimum credits guarantee)
     *
     * @return array Death summary
     */
    public function processPlayerDeath(Player $player, PlayerShip $playerShip): array
    {
        // Step 1: Record losses
        $losses = $this->recordLosses($player, $playerShip);

        // Step 2 & 3: Delete ship and plans
        $this->destroyShipAndPlans($player, $playerShip);

        // Step 4: Ensure minimum credits (enough to buy a basic ship)
        $creditsGranted = $this->ensureMinimumCredits($player);

        // Step 5: Determine respawn location (prioritize shipyards)
        $respawnLocation = $this->determineRespawnLocation($player);

        // Update player location
        if ($respawnLocation) {
            $player->current_poi_id = $respawnLocation->id;
            $player->save();
        }

        return [
            'losses' => $losses,
            'respawn_location' => $respawnLocation,
            'credits_retained' => $player->credits,
            'credits_granted' => $creditsGranted,
            'xp_retained' => $player->experience ?? 0,
        ];
    }

    /**
     * Record what the player lost
     */
    private function recordLosses(Player $player, PlayerShip $playerShip): array
    {
        $cargo = $playerShip->cargo()->with('mineral')->get();
        $cargoValue = 0;

        foreach ($cargo as $item) {
            if ($item->mineral) {
                // Estimate value (would need to fetch current market prices for accuracy)
                $cargoValue += $item->quantity * ($item->mineral->base_value ?? 0);
            }
        }

        return [
            'ship_name' => $playerShip->name,
            'ship_class' => $playerShip->ship->class ?? 'Unknown',
            'cargo_items_lost' => $cargo->count(),
            'estimated_cargo_value' => $cargoValue,
            'upgrade_plans_lost' => $player->plans()->count(),
            'ship_value' => $playerShip->ship->base_price ?? 0,
        ];
    }

    /**
     * Destroy ship and detach plans
     */
    private function destroyShipAndPlans(Player $player, PlayerShip $playerShip): void
    {
        // Detach all upgrade plans (player loses them)
        $player->plans()->detach();

        // Delete the ship (cascades to cargo via DB foreign key)
        $playerShip->delete();
    }

    /**
     * Ensure player has minimum credits to buy a basic ship
     * Grants credits if needed (minimum: 5000 credits for starter ship)
     *
     * @return int Credits granted (0 if player already had enough)
     */
    private function ensureMinimumCredits(Player $player): int
    {
        $minimumCredits = 5000; // Enough to buy a basic scout/starter ship

        if ($player->credits < $minimumCredits) {
            $creditsGranted = $minimumCredits - $player->credits;
            $player->credits = $minimumCredits;
            $player->save();

            return $creditsGranted;
        }

        return 0;
    }

    /**
     * Determine where player respawns
     *
     * Priority:
     * 1. Last trading hub visited WITH ships available (last_trading_hub_poi_id)
     * 2. Any trading hub WITH ships available in current galaxy
     * 3. Any active trading hub in current galaxy (even without ships)
     * 4. Any POI in current galaxy
     */
    private function determineRespawnLocation(Player $player): ?PointOfInterest
    {
        // Try last trading hub if it has ships available
        if ($player->last_trading_hub_poi_id) {
            $lastHub = PointOfInterest::with('tradingHub')
                ->find($player->last_trading_hub_poi_id);

            if ($lastHub && $lastHub->tradingHub && $lastHub->tradingHub->is_active) {
                $hasShips = $this->tradingHubHasShips($lastHub->tradingHub);
                if ($hasShips) {
                    return $lastHub;
                }
            }
        }

        // Try any trading hub WITH ships available in current galaxy
        $currentLocation = $player->currentLocation;
        if ($currentLocation) {
            $galaxy = $currentLocation->galaxy;

            // Find trading hub with ships
            $hubs = PointOfInterest::whereHas('tradingHub', function ($query) {
                $query->where('is_active', true);
            })
                ->with('tradingHub')
                ->where('galaxy_id', $galaxy->id)
                ->where('is_hidden', false)
                ->get();

            foreach ($hubs as $hub) {
                if ($this->tradingHubHasShips($hub->tradingHub)) {
                    return $hub;
                }
            }

            // Fallback: any trading hub (even without ships)
            $anyHub = PointOfInterest::whereHas('tradingHub', function ($query) {
                $query->where('is_active', true);
            })
                ->where('galaxy_id', $galaxy->id)
                ->where('is_hidden', false)
                ->first();

            if ($anyHub) {
                return $anyHub;
            }

            // Last resort: any visible POI in galaxy
            return PointOfInterest::where('galaxy_id', $galaxy->id)
                ->where('is_hidden', false)
                ->first();
        }

        // Ultimate fallback: any trading hub with ships anywhere
        $allHubs = PointOfInterest::whereHas('tradingHub', function ($query) {
            $query->where('is_active', true);
        })
            ->with('tradingHub')
            ->where('is_hidden', false)
            ->get();

        foreach ($allHubs as $hub) {
            if ($this->tradingHubHasShips($hub->tradingHub)) {
                return $hub;
            }
        }

        // Absolute fallback: any trading hub anywhere
        return PointOfInterest::whereHas('tradingHub', function ($query) {
            $query->where('is_active', true);
        })
            ->where('is_hidden', false)
            ->first();
    }

    /**
     * Check if a trading hub has ships available for purchase
     */
    private function tradingHubHasShips($tradingHub): bool
    {
        if (!$tradingHub) {
            return false;
        }

        return \DB::table('trading_hub_ships')
            ->where('trading_hub_id', $tradingHub->id)
            ->where('quantity', '>', 0)
            ->exists();
    }

    /**
     * Generate death message
     */
    public function generateDeathMessage(array $deathResult): string
    {
        $losses = $deathResult['losses'];
        $respawn = $deathResult['respawn_location'];
        $creditsGranted = $deathResult['credits_granted'] ?? 0;

        $messages = [];
        $messages[] = '═══════════════════════════════════════════════';
        $messages[] = '           SHIP DESTROYED - ESCAPE POD LAUNCHED';
        $messages[] = '═══════════════════════════════════════════════';
        $messages[] = '';
        $messages[] = "Your ship, the {$losses['ship_name']} ({$losses['ship_class']}), has been destroyed!";
        $messages[] = '';
        $messages[] = 'LOSSES:';
        $messages[] = '  Ship Value: $'.number_format($losses['ship_value']);
        $messages[] = "  Cargo Items: {$losses['cargo_items_lost']} items (~\$".number_format($losses['estimated_cargo_value']).')';
        $messages[] = "  Upgrade Plans: {$losses['upgrade_plans_lost']} plans";
        $messages[] = '';
        $messages[] = 'RETAINED:';
        $messages[] = '  Credits: $'.number_format($deathResult['credits_retained']);
        $messages[] = "  Experience: {$deathResult['xp_retained']} XP";

        if ($creditsGranted > 0) {
            $messages[] = '';
            $messages[] = 'EMERGENCY FUNDS:';
            $messages[] = '  Granted: $'.number_format($creditsGranted).' (minimum credits for starter ship)';
        }

        $messages[] = '';

        if ($respawn) {
            $messages[] = "Your escape pod drifts to {$respawn->name}...";
            if ($respawn->tradingHub && $this->tradingHubHasShips($respawn->tradingHub)) {
                $messages[] = "This trading hub has ships available - you can purchase a new ship here.";
            } else {
                $messages[] = "You'll need to find a trading hub with ships to continue your journey.";
            }
        } else {
            $messages[] = 'Your escape pod is drifting in space...';
            $messages[] = 'ERROR: No safe haven found!';
        }

        $messages[] = '═══════════════════════════════════════════════';

        return implode("\n", $messages);
    }
}

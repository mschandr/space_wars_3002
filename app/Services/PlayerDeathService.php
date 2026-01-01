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
     * 4. Respawn at last trading hub (or fallback location)
     * 5. Keep credits and XP
     *
     * @param Player $player
     * @param PlayerShip $playerShip
     * @return array Death summary
     */
    public function processPlayerDeath(Player $player, PlayerShip $playerShip): array
    {
        // Step 1: Record losses
        $losses = $this->recordLosses($player, $playerShip);

        // Step 2 & 3: Delete ship and plans
        $this->destroyShipAndPlans($player, $playerShip);

        // Step 4: Determine respawn location
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
            'xp_retained' => $player->xp ?? 0,
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
     * Determine where player respawns
     *
     * Priority:
     * 1. Last trading hub visited (last_trading_hub_poi_id)
     * 2. Any active trading hub in current galaxy
     * 3. Any POI in current galaxy
     */
    private function determineRespawnLocation(Player $player): ?PointOfInterest
    {
        // Try last trading hub
        if ($player->last_trading_hub_poi_id) {
            $lastHub = PointOfInterest::with('tradingHub')
                ->find($player->last_trading_hub_poi_id);

            if ($lastHub && $lastHub->tradingHub && $lastHub->tradingHub->is_active) {
                return $lastHub;
            }
        }

        // Try any trading hub in current galaxy
        $currentLocation = $player->currentLocation;
        if ($currentLocation) {
            $galaxy = $currentLocation->galaxy;

            // Find nearest trading hub
            $nearestHub = PointOfInterest::whereHas('tradingHub', function ($query) {
                    $query->where('is_active', true);
                })
                ->where('galaxy_id', $galaxy->id)
                ->where('is_hidden', false)
                ->first();

            if ($nearestHub) {
                return $nearestHub;
            }

            // Last resort: any visible POI in galaxy
            return PointOfInterest::where('galaxy_id', $galaxy->id)
                ->where('is_hidden', false)
                ->first();
        }

        // Ultimate fallback: any trading hub anywhere
        return PointOfInterest::whereHas('tradingHub', function ($query) {
                $query->where('is_active', true);
            })
            ->where('is_hidden', false)
            ->first();
    }

    /**
     * Generate death message
     */
    public function generateDeathMessage(array $deathResult): string
    {
        $losses = $deathResult['losses'];
        $respawn = $deathResult['respawn_location'];

        $messages = [];
        $messages[] = "═══════════════════════════════════════════════";
        $messages[] = "           SHIP DESTROYED - ESCAPE POD LAUNCHED";
        $messages[] = "═══════════════════════════════════════════════";
        $messages[] = "";
        $messages[] = "Your ship, the {$losses['ship_name']} ({$losses['ship_class']}), has been destroyed!";
        $messages[] = "";
        $messages[] = "LOSSES:";
        $messages[] = "  Ship Value: \$" . number_format($losses['ship_value']);
        $messages[] = "  Cargo Items: {$losses['cargo_items_lost']} items (~\$" . number_format($losses['estimated_cargo_value']) . ")";
        $messages[] = "  Upgrade Plans: {$losses['upgrade_plans_lost']} plans";
        $messages[] = "";
        $messages[] = "RETAINED:";
        $messages[] = "  Credits: \$" . number_format($deathResult['credits_retained']);
        $messages[] = "  Experience: {$deathResult['xp_retained']} XP";
        $messages[] = "";

        if ($respawn) {
            $messages[] = "Your escape pod drifts to {$respawn->name}...";
            $messages[] = "You'll need to purchase a new ship to continue your journey.";
        } else {
            $messages[] = "Your escape pod is drifting in space...";
            $messages[] = "ERROR: No safe haven found!";
        }

        $messages[] = "═══════════════════════════════════════════════";

        return implode("\n", $messages);
    }
}

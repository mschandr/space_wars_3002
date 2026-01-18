<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PrecursorShip;

/**
 * Precursor Ship Detection Service
 *
 * Handles scanning for and discovering the legendary Precursor Vessel.
 *
 * Detection Requirements:
 * - Sensor Level 12+ (godlike sensors)
 * - Within 10 unit radius
 * - Active scanning (not passive)
 */
class PrecursorShipDetectionService
{
    /**
     * Scan for Precursor Ship near player's current location
     *
     * @return array{detected: bool, ship: PrecursorShip|null, distance: float|null, message: string}
     */
    public function scan(Player $player): array
    {
        $ship = $player->activeShip;

        if (! $ship) {
            return [
                'detected' => false,
                'ship' => null,
                'distance' => null,
                'message' => 'No active ship to scan with.',
            ];
        }

        // Check sensor requirements
        if ($ship->sensors < 12) {
            return [
                'detected' => false,
                'ship' => null,
                'distance' => null,
                'message' => sprintf(
                    'Your sensors (Level %d) are insufficient. Detecting Precursor vessels requires Sensor Level 12+.',
                    $ship->sensors
                ),
            ];
        }

        // Get Precursor Ship in this galaxy
        $precursorShip = PrecursorShip::where('galaxy_id', $player->galaxy_id)
            ->first();

        if (! $precursorShip) {
            return [
                'detected' => false,
                'ship' => null,
                'distance' => null,
                'message' => 'No Precursor vessels exist in this galaxy.',
            ];
        }

        // Calculate distance
        $distance = $precursorShip->distanceFrom(
            $player->currentLocation->x,
            $player->currentLocation->y
        );

        // Check if within detection range (10 units)
        if ($distance > 10) {
            return [
                'detected' => false,
                'ship' => null,
                'distance' => $distance,
                'message' => $this->getDistanceHint($distance),
            ];
        }

        // DETECTED!
        // If this is first discovery, mark it
        if (! $precursorShip->is_discovered) {
            $precursorShip->discover($player);

            return [
                'detected' => true,
                'ship' => $precursorShip,
                'distance' => $distance,
                'message' => $this->getFirstDiscoveryMessage($precursorShip, $distance),
                'first_discovery' => true,
            ];
        }

        // Already discovered
        return [
            'detected' => true,
            'ship' => $precursorShip,
            'distance' => $distance,
            'message' => $this->getDetectionMessage($precursorShip, $distance),
            'first_discovery' => false,
        ];
    }

    /**
     * Get bearing/direction hint when ship is out of range
     */
    private function getDistanceHint(float $distance): string
    {
        if ($distance <= 20) {
            return '🔍 Your sensors detect faint quantum echoes... something ancient is nearby.';
        }

        if ($distance <= 50) {
            return '🔍 Deep space scans show anomalous energy signatures in this sector. Keep searching.';
        }

        if ($distance <= 100) {
            return '🔍 Long-range sensors detect... something. Very faint. Very old.';
        }

        return '🔍 Your sensors detect nothing unusual in this region.';
    }

    /**
     * First discovery message
     */
    private function getFirstDiscoveryMessage(PrecursorShip $ship, float $distance): string
    {
        return <<<MESSAGE
╔═══════════════════════════════════════════════════════════════╗
║                    🌟 PRECURSOR CONTACT 🌟                    ║
╠═══════════════════════════════════════════════════════════════╣
║                                                               ║
║  ⚠️  ALERT: IMPOSSIBLE ENERGY SIGNATURE DETECTED ⚠️           ║
║                                                               ║
║  Distance: {$distance} units                                 ║
║  Age: 500,000 years                                           ║
║  Status: Dormant... awaiting activation                       ║
║                                                               ║
║  {$ship->getDiscoveryLore()}                                 ║
║                                                               ║
║  +100,000 XP - LEGENDARY DISCOVERY                            ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝
MESSAGE;
    }

    /**
     * Subsequent detection message
     */
    private function getDetectionMessage(PrecursorShip $ship, float $distance): string
    {
        $claimedBy = $ship->claimedBy ? $ship->claimedBy->call_sign : 'Unclaimed';

        return <<<MESSAGE
🛸 Precursor Vessel Detected
━━━━━━━━━━━━━━━━━━━━━━━━━━
Name: {$ship->precursor_name}
Distance: {$distance} units
Status: {$claimedBy}

The ancient ship waits in the void...
MESSAGE;
    }

    /**
     * Navigate to Precursor Ship (if player has jump drive or sufficient fuel)
     */
    public function travelTo(Player $player, PrecursorShip $precursorShip): array
    {
        $ship = $player->activeShip;

        if (! $ship) {
            return [
                'success' => false,
                'message' => 'No active ship.',
            ];
        }

        // Calculate distance
        $distance = $precursorShip->distanceFrom(
            $player->currentLocation->x,
            $player->currentLocation->y
        );

        // Calculate fuel cost (no warp gates in interstellar space!)
        $baseCost = ceil($distance);
        $efficiency = 1 + (($ship->warp_drive - 1) * 0.2);
        $fuelCost = max(1, (int) ceil($baseCost / $efficiency));

        // Check fuel
        if ($ship->current_fuel < $fuelCost) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Insufficient fuel for interstellar travel. Required: %d, Available: %d',
                    $fuelCost,
                    $ship->current_fuel
                ),
            ];
        }

        // Consume fuel and travel
        $ship->consumeFuel($fuelCost);

        // Player doesn't move to a POI - they're in interstellar space
        // Store coordinates in player session or create temporary "location"
        // For now, we'll just track that they're "at Precursor Ship coordinates"

        return [
            'success' => true,
            'message' => sprintf(
                'Jumped %d units through deep space. Fuel consumed: %d. You have arrived at the Precursor Vessel.',
                round($distance, 1),
                $fuelCost
            ),
            'fuel_cost' => $fuelCost,
            'distance' => $distance,
        ];
    }

    /**
     * Claim the Precursor Ship (board and take ownership)
     */
    public function claim(Player $player, PrecursorShip $precursorShip): array
    {
        // Must be discovered first
        if (! $precursorShip->is_discovered) {
            return [
                'success' => false,
                'message' => 'You must detect the ship before claiming it.',
            ];
        }

        // Already claimed?
        if ($precursorShip->claimed_by_player_id) {
            $owner = $precursorShip->claimedBy;

            return [
                'success' => false,
                'message' => sprintf(
                    'This Precursor Vessel has already been claimed by %s.',
                    $owner ? $owner->call_sign : 'another player'
                ),
            ];
        }

        // Claim it!
        $playerShip = $precursorShip->claim($player);

        return [
            'success' => true,
            'message' => $this->getClaimMessage($precursorShip),
            'player_ship' => $playerShip,
        ];
    }

    /**
     * Claiming message
     */
    private function getClaimMessage(PrecursorShip $ship): string
    {
        return <<<MESSAGE
╔═══════════════════════════════════════════════════════════════╗
║                   ⚡ PRECURSOR VESSEL CLAIMED ⚡                ║
╠═══════════════════════════════════════════════════════════════╣
║                                                               ║
║  As you step aboard the {$ship->precursor_name},              ║
║  its systems awaken after half a million years of slumber.    ║
║                                                               ║
║  Ancient neural interfaces sync with your consciousness.      ║
║  The ship recognizes you as its new captain.                  ║
║                                                               ║
║  ━━━━━━━━━━━━━━━━━ SHIP SYSTEMS ━━━━━━━━━━━━━━━━━            ║
║                                                               ║
║  Hull:         1,000,000 (Regenerating)                       ║
║  Weapons:      10,000 (100x conventional)                     ║
║  Sensors:      100 (Quantum-enhanced)                         ║
║  Speed:        10,000 (Relativistic)                          ║
║  Cargo:        1,000,000 (Pocket Dimension)                   ║
║  Fuel:         ∞ (Self-sustaining)                            ║
║                                                               ║
║  ━━━━━━━━━━━━━━━ PRECURSOR TECH ━━━━━━━━━━━━━━━              ║
║                                                               ║
║  ✓ Jump Drive (Travel anywhere without gates)                ║
║  ✓ Pocket Dimension Storage (Effectively infinite)            ║
║  ✓ Shield Harmonics (Regenerating defenses)                  ║
║  ✓ Matter Replicator (Self-repair)                            ║
║  ✓ Neural Interface (Direct mind control)                     ║
║  ✓ Complete Stellar Cartography (Ancient star maps)           ║
║                                                               ║
║  You are now captain of the most powerful vessel in           ║
║  the galaxy.                                                  ║
║                                                               ║
║  The Precursors chose wisely.                                 ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝
MESSAGE;
    }
}

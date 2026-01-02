<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\WarpGate;

/**
 * Travel Service
 *
 * Handles travel calculations and execution
 */
class TravelService
{
    /**
     * Calculate fuel cost for travel based on distance and ship warp drive
     *
     * Formula:
     * - Base cost: ceil(distance)
     * - Efficiency: 1 + ((warp_drive - 1) * 0.2) - 20% reduction per warp level
     * - Final cost: max(1, ceil(baseCost / efficiency))
     *
     * @param float $distance The distance to travel
     * @param PlayerShip $ship The ship performing the travel
     * @return int The fuel cost
     */
    public function calculateFuelCost(float $distance, PlayerShip $ship): int
    {
        // Base fuel cost uses actual Euclidean distance
        $baseCost = ceil($distance);

        // Warp drive reduces fuel consumption (20% reduction per level)
        $efficiency = 1 + (($ship->warp_drive ?? 1) - 1) * 0.2;
        $fuelCost = max(1, (int) ceil($baseCost / $efficiency));

        return $fuelCost;
    }

    /**
     * Calculate XP earned for travel
     *
     * Formula: max(10, distance * 5)
     * - 5 XP per unit distance
     * - Minimum 10 XP
     *
     * @param float $distance The distance traveled
     * @return int The XP earned
     */
    public function calculateTravelXP(float $distance): int
    {
        return (int) max(10, $distance * 5);
    }

    /**
     * Execute travel through a warp gate
     *
     * @param Player $player The player traveling
     * @param WarpGate $gate The warp gate to travel through
     * @return array Result with success status, message, XP earned, and level info
     */
    public function executeTravel(Player $player, WarpGate $gate): array
    {
        $ship = $player->activeShip;

        if (!$ship) {
            return [
                'success' => false,
                'message' => 'No active ship',
                'xp_earned' => 0,
                'old_level' => $player->level,
                'new_level' => $player->level,
            ];
        }

        $destination = $gate->destinationPoi;
        $distance = $gate->distance ?? $gate->calculateDistance();
        $fuelCost = $this->calculateFuelCost($distance, $ship);

        // Check if ship has enough fuel
        if ($ship->current_fuel < $fuelCost) {
            return [
                'success' => false,
                'message' => 'Insufficient fuel',
                'required_fuel' => $fuelCost,
                'current_fuel' => $ship->current_fuel,
                'xp_earned' => 0,
                'old_level' => $player->level,
                'new_level' => $player->level,
            ];
        }

        // Consume fuel
        $ship->consumeFuel($fuelCost);

        // Update player location
        $player->current_poi_id = $destination->id;

        // Track last trading hub for respawn
        if ($destination->tradingHub && $destination->tradingHub->is_active) {
            $player->last_trading_hub_poi_id = $destination->id;
        }

        $player->save();

        // Award XP
        $xpEarned = $this->calculateTravelXP($distance);
        $oldLevel = $player->level;
        $player->addExperience($xpEarned);
        $newLevel = $player->level;

        return [
            'success' => true,
            'message' => 'Travel successful',
            'destination' => $destination->name,
            'distance' => $distance,
            'fuel_cost' => $fuelCost,
            'fuel_remaining' => $ship->current_fuel,
            'xp_earned' => $xpEarned,
            'old_level' => $oldLevel,
            'new_level' => $newLevel,
            'leveled_up' => $newLevel > $oldLevel,
        ];
    }
}

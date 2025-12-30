<?php

namespace App\Console\Tui\Handlers;

use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\WarpGate;

class TravelHandler
{
    public function __construct(private Player $player)
    {
    }

    /**
     * Execute warp gate travel
     */
    public function executeTravel(WarpGate $gate): array
    {
        $ship = $this->player->activeShip;
        $destination = $gate->destinationPoi;
        $distance = $gate->distance ?? $gate->calculateDistance();
        $fuelCost = $this->calculateFuelCost($distance, $ship);

        // Validate
        $error = $this->validateTravel($gate, $ship, $fuelCost);
        if ($error) {
            return [
                'success' => false,
                'message' => $error,
            ];
        }

        // Consume fuel
        if (!$ship->consumeFuel($fuelCost)) {
            return [
                'success' => false,
                'message' => "INSUFFICIENT FUEL! Need {$fuelCost}, have {$ship->current_fuel}",
            ];
        }

        // Update player location
        $this->player->current_poi_id = $destination->id;
        $this->player->save();

        // Reload relationships
        $this->player->load([
            'currentLocation.children',
            'currentLocation.parent',
            'currentLocation.tradingHub'
        ]);

        return [
            'success' => true,
            'message' => "Arrived at {$destination->name}! Fuel remaining: {$ship->current_fuel}",
            'destination' => $destination,
            'fuel_remaining' => $ship->current_fuel,
        ];
    }

    /**
     * Calculate fuel cost for travel
     */
    public function calculateFuelCost(float $distance, PlayerShip $ship): int
    {
        // Base fuel cost is distance divided by 10
        $baseCost = (int) ceil($distance / 10);

        // Warp drive reduces fuel consumption
        $efficiency = $ship->warp_drive ?? 1;
        $fuelCost = max(1, (int) floor($baseCost / $efficiency));

        return $fuelCost;
    }

    /**
     * Validate travel attempt
     */
    private function validateTravel(WarpGate $gate, PlayerShip $ship, int $fuelCost): ?string
    {
        if (!$gate->is_active) {
            return "Warp gate is not active";
        }

        if ($gate->status !== 'active') {
            return "Warp gate status: {$gate->status}";
        }

        if ($ship->current_fuel < $fuelCost) {
            return "Insufficient fuel: need {$fuelCost}, have {$ship->current_fuel}";
        }

        return null;
    }
}

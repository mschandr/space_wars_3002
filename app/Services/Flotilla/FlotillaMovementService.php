<?php

namespace App\Services\Flotilla;

use App\Models\Flotilla;
use App\Models\PointOfInterest;
use Illuminate\Support\Facades\DB;

class FlotillaMovementService
{
    /**
     * Check if a flotilla can move to a destination
     *
     * @param Flotilla $flotilla
     * @return array ['can_move' => bool, 'reason' => string|null, 'fuel_costs' => array]
     */
    public function canMoveFlotilla(Flotilla $flotilla): array
    {
        // Check all ships are at the same location
        if (!$flotilla->areAllShipsAtSamePoi()) {
            return [
                'can_move' => false,
                'reason' => 'All ships must be at the same location to move as a flotilla',
                'fuel_costs' => [],
            ];
        }

        // Calculate fuel costs
        $fuelCosts = $this->calculateFlotillaFuelCosts($flotilla);

        // Check each ship has sufficient fuel
        foreach ($flotilla->ships as $ship) {
            $ship->regenerateFuel();
            $requiredFuel = $fuelCosts[$ship->id] ?? 0;

            if ($ship->current_fuel < $requiredFuel) {
                return [
                    'can_move' => false,
                    'reason' => "Ship '{$ship->name}' does not have sufficient fuel ({$ship->current_fuel}/{$requiredFuel})",
                    'fuel_costs' => $fuelCosts,
                ];
            }
        }

        return [
            'can_move' => true,
            'fuel_costs' => $fuelCosts,
        ];
    }

    /**
     * Move a flotilla to a destination
     *
     * @param Flotilla $flotilla
     * @param PointOfInterest $destination
     * @param int $distance
     * @return void
     * @throws \Exception
     */
    public function moveFlotilla(Flotilla $flotilla, PointOfInterest $destination, int $distance): void
    {
        DB::transaction(function () use ($flotilla, $destination, $distance) {
            // Validate can move
            $canMove = $this->canMoveFlotilla($flotilla);
            if (!$canMove['can_move']) {
                throw new \Exception($canMove['reason']);
            }

            $fuelCosts = $canMove['fuel_costs'];

            // Move each ship and consume fuel
            foreach ($flotilla->ships as $ship) {
                $requiredFuel = $fuelCosts[$ship->id] ?? 0;

                // Consume fuel from ship
                if (!$ship->consumeFuel($requiredFuel)) {
                    throw new \Exception("Failed to consume fuel for ship '{$ship->name}'");
                }

                // Update ship location
                $ship->update(['current_poi_id' => $destination->id]);
            }
        });
    }

    /**
     * Calculate fuel costs for all ships in a flotilla with formation penalties
     *
     * @param Flotilla $flotilla
     * @param int $distance Base distance (defaults to next POI)
     * @return array ['ship_id' => cost, ...]
     */
    public function calculateFlotillaFuelCosts(Flotilla $flotilla, int $distance = 1): array
    {
        $fuelPenalty = $this->getFormationFuelPenalty($flotilla->shipCount());
        $costs = [];

        foreach ($flotilla->ships as $ship) {
            // Calculate base fuel cost for this ship
            $baseCost = $ship->getEffectiveFuelConsumption($distance);

            // Apply formation penalty
            $totalCost = (int) ceil($baseCost * $fuelPenalty);

            $costs[$ship->id] = $totalCost;
        }

        return $costs;
    }

    /**
     * Get the movement speed of a flotilla (determined by slowest ship)
     *
     * @param Flotilla $flotilla
     * @return int The warp drive level of the slowest ship
     */
    public function getFlotillaSpeed(Flotilla $flotilla): int
    {
        $slowest = $flotilla->slowestShip();
        return $slowest ? $slowest->warp_drive : 1;
    }

    /**
     * Get the fuel penalty multiplier for a flotilla based on ship count
     *
     * Formula:
     * 1 ship = 1.0× (no penalty)
     * 2 ships = 1.1× (+10%)
     * 3 ships = 1.2× (+20%)
     * 4 ships = 1.3× (+30%)
     *
     * @param int $shipCount
     * @return float The fuel penalty multiplier
     */
    public function getFormationFuelPenalty(int $shipCount): float
    {
        if ($shipCount <= 1) {
            return 1.0;
        }

        // Each additional ship adds penalty_per_ship (default 0.10)
        $penalty = config('game_config.flotilla.fuel_penalty_per_ship', 0.10);
        return 1.0 + ($shipCount - 1) * $penalty;
    }

    /**
     * Calculate estimated fuel cost for moving a flotilla to a destination
     * Useful for displaying to players before they commit to movement
     *
     * @param Flotilla $flotilla
     * @param PointOfInterest $destination
     * @return array ['total_cost' => int, 'by_ship' => [...], 'penalty_multiplier' => float]
     */
    public function estimateFuelCost(Flotilla $flotilla, PointOfInterest $destination): array
    {
        $origin = $flotilla->getCurrentLocation();
        $distance = $origin ? (int) sqrt(
            pow($destination->x - $origin->x, 2) + pow($destination->y - $origin->y, 2)
        ) : 1;

        $costs = $this->calculateFlotillaFuelCosts($flotilla, $distance);
        $totalCost = array_sum($costs);
        $penalty = $this->getFormationFuelPenalty($flotilla->shipCount());

        return [
            'total_cost' => $totalCost,
            'by_ship' => $costs,
            'penalty_multiplier' => $penalty,
            'distance' => $distance,
        ];
    }

    /**
     * Get movement status for each ship in a flotilla
     *
     * @param Flotilla $flotilla
     * @return array
     */
    public function getMovementStatus(Flotilla $flotilla): array
    {
        return [
            'speed_limiting_ship' => [
                'id' => $flotilla->slowestShip()?->id,
                'name' => $flotilla->slowestShip()?->name,
                'warp_drive' => $flotilla->slowestShip()?->warp_drive,
            ],
            'ship_fuel_status' => $this->getShipFuelStatus($flotilla),
            'can_move' => $this->canMoveFlotilla($flotilla)['can_move'],
        ];
    }

    /**
     * Get detailed fuel status for each ship
     *
     * @param Flotilla $flotilla
     * @return array
     */
    private function getShipFuelStatus(Flotilla $flotilla): array
    {
        return $flotilla->ships->map(function ($ship) {
            $ship->regenerateFuel();
            return [
                'ship_id' => $ship->id,
                'ship_name' => $ship->name,
                'current_fuel' => $ship->current_fuel,
                'max_fuel' => $ship->getEffectiveMaxFuel(),
                'fuel_percent' => (int) (($ship->current_fuel / $ship->getEffectiveMaxFuel()) * 100),
            ];
        })->all();
    }
}

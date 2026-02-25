<?php

namespace App\Services;

use App\Models\PlayerShip;
use Carbon\Carbon;

/**
 * Fuel Regeneration Service
 *
 * Handles predictable fuel regeneration based on warp drive level
 */
class FuelRegenerationService
{
    /**
     * Get base fuel regeneration rate (units per hour), derived from the
     * canonical config value (seconds per unit) so there's one place to change it.
     */
    private static function baseRegenRatePerHour(): float
    {
        $secondsPerUnit = (int) config('game_config.ships.fuel_regen_seconds_per_unit', 30);

        return 3600 / max(1, $secondsPerUnit);
    }

    /**
     * Regenerate fuel for a player ship based on time elapsed
     *
     * Formula: Fuel regen = BASE_RATE * (1 + (warp_drive - 1) * 0.3) * hours_elapsed
     * Higher warp drives = more efficient fuel regeneration
     *
     * @return array{regenerated: float, new_fuel: float, hours_elapsed: float}
     */
    public function regenerateFuel(PlayerShip $ship): array
    {
        $now = Carbon::now();
        $lastUpdate = Carbon::parse($ship->fuel_last_updated_at);
        $hoursElapsed = $lastUpdate->diffInMinutes($now) / 60.0;

        // Calculate regeneration based on warp drive
        // Warp drive 1 = 1.0x base rate
        // Warp drive 2 = 1.3x base rate
        // Warp drive 5 = 2.2x base rate
        // Warp drive 10 = 3.7x base rate
        $efficiency = 1 + ($ship->warp_drive - 1) * 0.3;
        $regenRate = self::baseRegenRatePerHour() * $efficiency;

        // Calculate total fuel to regenerate
        $fuelToRegen = $regenRate * $hoursElapsed;

        // Apply regeneration (cap at max_fuel)
        $oldFuel = $ship->current_fuel;
        $newFuel = min($ship->max_fuel, $ship->current_fuel + $fuelToRegen);

        $ship->current_fuel = $newFuel;
        $ship->fuel_last_updated_at = $now;
        $ship->save();

        return [
            'regenerated' => $newFuel - $oldFuel,
            'new_fuel' => $newFuel,
            'old_fuel' => $oldFuel,
            'hours_elapsed' => $hoursElapsed,
            'regen_rate' => $regenRate,
        ];
    }

    /**
     * Get estimated time to full fuel
     *
     * @return float Hours until full fuel
     */
    public function getTimeToFullFuel(PlayerShip $ship): float
    {
        if ($ship->current_fuel >= $ship->max_fuel) {
            return 0;
        }

        $efficiency = 1 + ($ship->warp_drive - 1) * 0.3;
        $regenRate = self::baseRegenRatePerHour() * $efficiency;

        $fuelNeeded = $ship->max_fuel - $ship->current_fuel;

        return $fuelNeeded / $regenRate;
    }

    /**
     * Get fuel regeneration info for display
     */
    public function getRegenerationInfo(PlayerShip $ship): array
    {
        $efficiency = 1 + ($ship->warp_drive - 1) * 0.3;
        $regenRate = self::baseRegenRatePerHour() * $efficiency;

        return [
            'regen_rate_per_hour' => $regenRate,
            'regen_rate_per_minute' => $regenRate / 60,
            'time_to_full_hours' => $this->getTimeToFullFuel($ship),
            'efficiency_multiplier' => $efficiency,
        ];
    }
}

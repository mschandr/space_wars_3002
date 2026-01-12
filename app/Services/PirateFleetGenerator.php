<?php

namespace App\Services;

use App\Models\Mineral;
use App\Models\PirateCaptain;
use App\Models\PirateCargo;
use App\Models\PirateFleet;
use App\Models\Plan;
use App\Models\Ship;
use App\Models\WarpLanePirate;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PirateFleetGenerator
{
    // Ship preferences by difficulty tier
    private const TIER_SHIP_PREFERENCES = [
        1 => ['Sparrow-class Light Freighter' => 40, 'Viper-class Fighter' => 40, 'Interdictor-class Corvette' => 20],
        2 => ['Viper-class Fighter' => 50, 'Interdictor-class Corvette' => 30, 'Phantom-class Scout' => 20],
        3 => ['Interdictor-class Corvette' => 40, 'Corsair-class Gunship' => 30, 'Viper-class Fighter' => 30],
        4 => ['Corsair-class Gunship' => 50, 'Interdictor-class Corvette' => 30, 'Leviathan-class Battleship' => 20],
        5 => ['Corsair-class Gunship' => 40, 'Leviathan-class Battleship' => 40, 'Interdictor-class Corvette' => 20],
    ];

    // Stat multipliers by tier
    private const TIER_STAT_MULTIPLIER = [
        1 => 1.0,
        2 => 1.15,
        3 => 1.3,
        4 => 1.45,
        5 => 1.6,
    ];

    // Ship name components
    private const SHIP_NAME_PREFIXES = [
        'Crimson', 'Shadow', 'Dark', 'Blood', 'Iron', 'Steel', 'Void', 'Ghost',
        'Phantom', 'Vengeful', 'Savage', 'Black', 'Red', 'Death', 'Hell', 'Doom',
    ];

    private const SHIP_NAME_SUFFIXES = [
        'Dagger', 'Talon', 'Reaver', 'Serpent', 'Fang', 'Claw', 'Terror', 'Raider',
        'Marauder', 'Scourge', 'Fury', 'Vengeance', 'Blade', 'Edge', 'Storm', 'Wraith',
    ];

    /**
     * Generate a pirate fleet for an encounter
     */
    public function generateFleet(WarpLanePirate $warpLanePirate): Collection
    {
        $captain = $warpLanePirate->captain;
        $tier = $warpLanePirate->difficulty_tier;
        $fleetSize = $warpLanePirate->fleet_size;

        $fleet = collect();

        for ($i = 0; $i < $fleetSize; $i++) {
            $pirateShip = $this->generatePirateShip($captain, $tier);
            $this->generateCargo($pirateShip, $tier);
            $fleet->push($pirateShip);
        }

        return $fleet;
    }

    /**
     * Generate a single pirate ship
     */
    private function generatePirateShip(PirateCaptain $captain, int $tier): PirateFleet
    {
        // Select ship type based on tier preferences
        $shipType = $this->selectShipType($tier);

        if (! $shipType) {
            // Fallback to Viper if no ship found
            $shipType = Ship::where('name', 'Viper-class Fighter')->first();
        }

        // Get stat multiplier for this tier
        $multiplier = self::TIER_STAT_MULTIPLIER[$tier] ?? 1.0;

        // Create pirate ship with scaled stats
        $pirateShip = PirateFleet::create([
            'uuid' => Str::uuid(),
            'captain_id' => $captain->id,
            'ship_id' => $shipType->id,
            'ship_name' => $this->generateShipName(),
            'hull' => (int) round($shipType->hull_strength * $multiplier),
            'max_hull' => (int) round($shipType->hull_strength * $multiplier),
            'weapons' => (int) round(($shipType->attributes['starting_weapons'] ?? 10) * $multiplier),
            'speed' => $shipType->speed,
            'warp_drive' => $shipType->attributes['starting_warp_drive'] ?? 1,
            'cargo_capacity' => $shipType->cargo_capacity,
            'status' => 'active',
        ]);

        return $pirateShip;
    }

    /**
     * Select a ship type based on tier preferences (weighted random)
     */
    private function selectShipType(int $tier): ?Ship
    {
        $preferences = self::TIER_SHIP_PREFERENCES[$tier] ?? self::TIER_SHIP_PREFERENCES[1];

        // Build weighted array
        $weightedShips = [];
        foreach ($preferences as $shipName => $weight) {
            $ship = Ship::where('name', $shipName)->first();
            if ($ship) {
                for ($i = 0; $i < $weight; $i++) {
                    $weightedShips[] = $ship;
                }
            }
        }

        return ! empty($weightedShips) ? $weightedShips[array_rand($weightedShips)] : null;
    }

    /**
     * Generate a pirate ship name
     */
    private function generateShipName(): string
    {
        $prefix = self::SHIP_NAME_PREFIXES[array_rand(self::SHIP_NAME_PREFIXES)];
        $suffix = self::SHIP_NAME_SUFFIXES[array_rand(self::SHIP_NAME_SUFFIXES)];

        return "The {$prefix} {$suffix}";
    }

    /**
     * Generate cargo for a pirate ship
     */
    private function generateCargo(PirateFleet $pirateShip, int $tier): void
    {
        // Generate 1-3 mineral types
        $mineralCount = rand(1, 3);
        $minerals = Mineral::inRandomOrder()->limit($mineralCount)->get();

        foreach ($minerals as $mineral) {
            // Quantity scales with tier (10-50 for tier 1, up to 50-250 for tier 5)
            $baseMin = 10 * $tier;
            $baseMax = 50 * $tier;
            $quantity = rand($baseMin, $baseMax);

            PirateCargo::create([
                'pirate_fleet_id' => $pirateShip->id,
                'mineral_id' => $mineral->id,
                'plan_id' => null,
                'quantity' => $quantity,
            ]);
        }

        // 10% chance for an upgrade plan
        if (rand(1, 100) <= 10) {
            $plan = Plan::inRandomOrder()->first();

            if ($plan) {
                PirateCargo::create([
                    'pirate_fleet_id' => $pirateShip->id,
                    'mineral_id' => null,
                    'plan_id' => $plan->id,
                    'quantity' => 1,
                ]);
            }
        }
    }

    /**
     * Calculate total fleet combat power
     */
    public function calculateFleetPower(Collection $fleet): int
    {
        return $fleet->sum(fn ($ship) => $ship->getCombatRating());
    }
}

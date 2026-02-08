<?php

namespace App\Http\Controllers\Api\Builders;

use App\Models\PointOfInterest;

/**
 * Generates thematic bar names and atmosphere descriptions.
 *
 * Consolidates bar-related generation that was duplicated in
 * StarSystemController and FacilitiesController.
 */
class BarNameGenerator
{
    /**
     * Primary bar names pool.
     */
    protected static array $primaryNames = [
        'The Dusty Airlock',
        "Void's Edge Cantina",
        'The Stellar Drift',
        'Black Nebula Bar',
        'The Rusty Thruster',
        "Pilot's Rest",
        'The Gravity Well',
        'Starlight Saloon',
        'The Docking Bay Dive',
        'Zero-G Tavern',
    ];

    /**
     * Secondary bar names for core systems.
     */
    protected static array $secondaryNames = [
        "The Officer's Club",
        "Merchant's Respite",
        'The Trade Wind',
        'Hub Station Lounge',
    ];

    /**
     * Bar atmosphere descriptions.
     */
    protected static array $atmospheres = [
        'Dimly lit with the hum of recycled air',
        'Crowded with traders and pilots',
        'Quiet, with a few regulars at the bar',
        'Lively, with music playing from old speakers',
        'Smoky despite the air filters',
        'Clean and well-maintained, surprisingly upscale',
        'Rough around the edges, but welcoming',
    ];

    /**
     * Generate bar names for a star system.
     *
     * Uses the system name as a seed for deterministic but varied names.
     *
     * @param  PointOfInterest  $system  The star system
     * @return array Array of bar names
     */
    public static function generate(PointOfInterest $system): array
    {
        $hash = crc32($system->name);
        $primaryIndex = $hash % count(self::$primaryNames);
        $primaryBar = self::$primaryNames[$primaryIndex];

        $bars = [$primaryBar];

        // Core systems get additional bars
        if ($system->region?->value === 'core') {
            $secondaryIndex = ($hash >> 4) % count(self::$secondaryNames);
            $bars[] = self::$secondaryNames[$secondaryIndex];
        }

        return $bars;
    }

    /**
     * Get a random bar atmosphere description.
     */
    public static function randomAtmosphere(): string
    {
        return self::$atmospheres[array_rand(self::$atmospheres)];
    }

    /**
     * Get a deterministic atmosphere based on bar index.
     *
     * @param  int  $index  Bar index for consistent atmosphere
     */
    public static function atmosphereForIndex(int $index): string
    {
        return self::$atmospheres[$index % count(self::$atmospheres)];
    }

    /**
     * Get all available atmosphere descriptions.
     */
    public static function allAtmospheres(): array
    {
        return self::$atmospheres;
    }
}

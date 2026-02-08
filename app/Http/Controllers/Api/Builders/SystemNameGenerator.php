<?php

namespace App\Http\Controllers\Api\Builders;

use App\Models\PointOfInterest;

/**
 * Generates procedural names for star systems.
 *
 * Extracted from GalaxyController for reuse in system generation.
 */
class SystemNameGenerator
{
    /**
     * Phonetic prefixes for name generation.
     */
    protected static array $prefixes = [
        'Al', 'Be', 'Ca', 'De', 'El', 'Fa', 'Ga', 'Ha', 'In', 'Jo',
        'Ka', 'La', 'Ma', 'Na', 'Ol', 'Pa', 'Qu', 'Ra', 'Sa', 'Ta',
    ];

    /**
     * Phonetic middle syllables.
     */
    protected static array $middles = [
        'ra', 'ri', 'ro', 'ta', 'ti', 'na', 'ni', 'sa', 'si', 'ma',
    ];

    /**
     * Phonetic suffixes.
     */
    protected static array $suffixes = [
        'nis', 'ria', 'tis', 'ron', 'lan', 'dar', 'nis', 'per', 'tar', 'ion',
    ];

    /**
     * Generate a name for a system based on its coordinates.
     *
     * Uses a deterministic algorithm based on x,y coordinates
     * to produce phonetically pleasing names.
     *
     * @param  PointOfInterest  $poi  The POI to generate a name for
     * @return string The generated name
     */
    public static function generate(PointOfInterest $poi): string
    {
        return self::fromCoordinates((int) $poi->x, (int) $poi->y);
    }

    /**
     * Generate a name from raw coordinates.
     *
     * @param  int  $x  X coordinate
     * @param  int  $y  Y coordinate
     * @return string The generated name
     */
    public static function fromCoordinates(int $x, int $y): string
    {
        $prefixIndex = ($x + $y) % count(self::$prefixes);
        $middleIndex = abs($x - $y) % count(self::$middles);
        $suffixIndex = ($x * $y) % count(self::$suffixes);

        return self::$prefixes[$prefixIndex].self::$middles[$middleIndex].self::$suffixes[$suffixIndex];
    }

    /**
     * Ensure a POI has a name, generating one if needed.
     *
     * @param  PointOfInterest  $poi  The POI to name
     * @return string The name (existing or newly generated)
     */
    public static function ensureName(PointOfInterest $poi): string
    {
        if (! empty($poi->name)) {
            return $poi->name;
        }

        $poi->name = self::generate($poi);
        $poi->save();

        return $poi->name;
    }
}

<?php

namespace App\Http\Controllers\Api\Builders;

use App\Enums\PointsOfInterest\PointOfInterestType;

/**
 * Utility class for categorizing Points of Interest.
 *
 * Centralizes the POI type to category mapping that was duplicated
 * across multiple controllers (StarSystemController, NavigationController,
 * FacilitiesController, LocationController).
 */
class PoiCategorizer
{
    /**
     * Available categories for POIs.
     */
    public const CATEGORY_PLANETS = 'planets';

    public const CATEGORY_MOONS = 'moons';

    public const CATEGORY_ASTEROID_BELTS = 'asteroid_belts';

    public const CATEGORY_STATIONS = 'stations';

    public const CATEGORY_DEFENSE_PLATFORMS = 'defense_platforms';

    public const CATEGORY_DERELICTS = 'derelicts';

    public const CATEGORY_ANOMALIES = 'anomalies';

    public const CATEGORY_OTHER = 'other';

    /**
     * Planet type POIs.
     */
    protected static array $planetTypes = [
        PointOfInterestType::TERRESTRIAL,
        PointOfInterestType::SUPER_EARTH,
        PointOfInterestType::GAS_GIANT,
        PointOfInterestType::ICE_GIANT,
        PointOfInterestType::HOT_JUPITER,
        PointOfInterestType::OCEAN,
        PointOfInterestType::LAVA,
        PointOfInterestType::CHTHONIC,
        PointOfInterestType::PLANET,
        PointOfInterestType::DWARF_PLANET,
    ];

    /**
     * Station type POIs.
     */
    protected static array $stationTypes = [
        PointOfInterestType::TRADING_STATION,
        PointOfInterestType::SHIPYARD,
        PointOfInterestType::SALVAGE_YARD,
        PointOfInterestType::DERELICT,
    ];

    /**
     * Categorize a POI type into a display category.
     */
    public static function categorize(?PointOfInterestType $type): string
    {
        if ($type === null) {
            return self::CATEGORY_OTHER;
        }

        return match ($type) {
            PointOfInterestType::TERRESTRIAL,
            PointOfInterestType::SUPER_EARTH,
            PointOfInterestType::GAS_GIANT,
            PointOfInterestType::ICE_GIANT,
            PointOfInterestType::HOT_JUPITER,
            PointOfInterestType::OCEAN,
            PointOfInterestType::LAVA,
            PointOfInterestType::CHTHONIC,
            PointOfInterestType::PLANET,
            PointOfInterestType::DWARF_PLANET => self::CATEGORY_PLANETS,

            PointOfInterestType::MOON => self::CATEGORY_MOONS,

            PointOfInterestType::ASTEROID_BELT,
            PointOfInterestType::ASTEROID => self::CATEGORY_ASTEROID_BELTS,

            PointOfInterestType::DERELICT => self::CATEGORY_DERELICTS,

            PointOfInterestType::TRADING_STATION,
            PointOfInterestType::SHIPYARD,
            PointOfInterestType::SALVAGE_YARD => self::CATEGORY_STATIONS,

            PointOfInterestType::DEFENSE_PLATFORM => self::CATEGORY_DEFENSE_PLATFORMS,

            PointOfInterestType::ANOMALY,
            PointOfInterestType::NEBULA => self::CATEGORY_ANOMALIES,

            default => self::CATEGORY_OTHER,
        };
    }

    /**
     * Check if a POI type is a planet.
     */
    public static function isPlanet(?PointOfInterestType $type): bool
    {
        return $type !== null && in_array($type, self::$planetTypes, true);
    }

    /**
     * Check if a POI type is a station.
     */
    public static function isStation(?PointOfInterestType $type): bool
    {
        return $type !== null && in_array($type, self::$stationTypes, true);
    }

    /**
     * Get a simple body type label for display.
     */
    public static function getBodyTypeLabel(?PointOfInterestType $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return match ($type) {
            PointOfInterestType::TERRESTRIAL,
            PointOfInterestType::SUPER_EARTH,
            PointOfInterestType::GAS_GIANT,
            PointOfInterestType::ICE_GIANT,
            PointOfInterestType::HOT_JUPITER,
            PointOfInterestType::OCEAN,
            PointOfInterestType::LAVA,
            PointOfInterestType::CHTHONIC,
            PointOfInterestType::PLANET,
            PointOfInterestType::DWARF_PLANET => 'planet',
            PointOfInterestType::MOON => 'moon',
            PointOfInterestType::ASTEROID_BELT => 'asteroid_belt',
            PointOfInterestType::ASTEROID => 'asteroid',
            PointOfInterestType::DERELICT => 'derelict',
            PointOfInterestType::STAR => 'star',
            default => null,
        };
    }

    /**
     * Get empty category structure for building responses.
     */
    public static function emptyCategoryStructure(): array
    {
        return [
            self::CATEGORY_PLANETS => [],
            self::CATEGORY_MOONS => [],
            self::CATEGORY_ASTEROID_BELTS => [],
            self::CATEGORY_STATIONS => [],
            self::CATEGORY_DEFENSE_PLATFORMS => [],
            self::CATEGORY_DERELICTS => [],
            self::CATEGORY_ANOMALIES => [],
            self::CATEGORY_OTHER => [],
        ];
    }
}

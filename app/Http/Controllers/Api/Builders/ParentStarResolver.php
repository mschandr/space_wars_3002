<?php

namespace App\Http\Controllers\Api\Builders;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\PointOfInterest;

/**
 * Utility class for resolving parent star systems from any POI.
 *
 * Consolidates the getParentStar/getParentSystem logic that was duplicated
 * in StarSystemController, FacilitiesController, and LocationController.
 */
class ParentStarResolver
{
    /**
     * Get the parent star for any POI in a system.
     *
     * Traverses up the parent chain until a STAR is found.
     *
     * @param  PointOfInterest  $location  Any POI (planet, moon, station, etc.)
     * @return PointOfInterest|null The parent star, or null if not found
     */
    public static function resolve(PointOfInterest $location): ?PointOfInterest
    {
        // If this is already a star, return it
        if ($location->type === PointOfInterestType::STAR) {
            return $location;
        }

        // Traverse up to find the star
        $current = $location;
        while ($current->parent_poi_id) {
            $current = $current->parent;
            if (! $current) {
                break;
            }
            if ($current->type === PointOfInterestType::STAR) {
                return $current;
            }
        }

        // Fallback: try to find the star in this location's galaxy at the same approximate coordinates
        if ($location->galaxy_id) {
            return PointOfInterest::where('galaxy_id', $location->galaxy_id)
                ->where('type', PointOfInterestType::STAR)
                ->where('id', $location->parent_poi_id ?? $location->id)
                ->first();
        }

        return null;
    }

    /**
     * Get the parent star or return the location if it is a star.
     *
     * Throws if no star can be resolved.
     *
     * @param  PointOfInterest  $location  Any POI
     * @return PointOfInterest The resolved star
     *
     * @throws \RuntimeException If no parent star can be found
     */
    public static function resolveOrFail(PointOfInterest $location): PointOfInterest
    {
        $star = self::resolve($location);

        if (! $star) {
            throw new \RuntimeException("Could not resolve parent star for POI {$location->uuid}");
        }

        return $star;
    }
}

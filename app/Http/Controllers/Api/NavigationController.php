<?php

namespace App\Http\Controllers\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Http\Controllers\Api\Builders\PoiCategorizer;
use App\Http\Resources\PointOfInterestResource;
use App\Models\Player;
use App\Models\PointOfInterest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationController extends BaseApiController
{
    /**
     * Get current location details
     *
     * GET /api/players/{uuid}/location
     */
    public function getLocation(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with(['currentLocation.galaxy', 'currentLocation.tradingHub', 'currentLocation.outgoingGates'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $location = $player->currentLocation;

        if (! $location) {
            return $this->error('Player has no current location', 'NO_LOCATION');
        }

        // Get warp gate count for this location
        $warpGateCount = $location->outgoingGates()
            ->where('is_hidden', false)
            ->where('status', 'active')
            ->count();

        // Check if there's a trading hub
        $hasTradingHub = $location->tradingHub !== null;
        $tradingHubInfo = null;

        if ($hasTradingHub) {
            $tradingHubInfo = [
                'uuid' => $location->tradingHub->uuid,
                'name' => $location->tradingHub->name,
                'type' => $location->tradingHub->type,
                'has_salvage_yard' => $location->tradingHub->has_salvage_yard,
                'services' => $location->tradingHub->services,
            ];
        }

        return $this->success([
            'location' => new PointOfInterestResource($location),
            'galaxy' => [
                'uuid' => $location->galaxy->uuid,
                'name' => $location->galaxy->name,
            ],
            'warp_gates_available' => $warpGateCount,
            'trading_hub' => $tradingHubInfo,
            'is_inhabited' => $location->is_inhabited,
        ]);
    }

    /**
     * Get systems within sensor range
     *
     * GET /api/players/{uuid}/nearby-systems
     */
    public function getNearbySystems(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with(['currentLocation', 'activeShip'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        if (! $player->currentLocation) {
            return $this->error('Player has no current location', 'NO_LOCATION');
        }

        if (! $player->activeShip) {
            return $this->error('Player has no active ship', 'NO_ACTIVE_SHIP');
        }

        $currentLocation = $player->currentLocation;
        $sensorLevel = $player->activeShip->sensors;
        $sensorRange = $sensorLevel * 100; // 100 units per sensor level

        // Find all POIs within sensor range
        // Use bounding box pre-filter to reduce spatial calculations
        $x = $currentLocation->x;
        $y = $currentLocation->y;

        $nearbySystems = PointOfInterest::where('galaxy_id', $currentLocation->galaxy_id)
            ->where('id', '!=', $currentLocation->id)
            ->where('type', PointOfInterestType::STAR)
            // Bounding box filter (can use indexes)
            ->where('x', '>=', $x - $sensorRange)
            ->where('x', '<=', $x + $sensorRange)
            ->where('y', '>=', $y - $sensorRange)
            ->where('y', '<=', $y + $sensorRange)
            ->selectRaw('*, SQRT(POW(x - ?, 2) + POW(y - ?, 2)) as distance', [$x, $y])
            // Precise circular distance (on remaining candidates)
            ->whereRaw('SQRT(POW(x - ?, 2) + POW(y - ?, 2)) <= ?', [$x, $y, $sensorRange])
            ->orderBy('distance')
            ->limit(50)
            ->get();

        // Pre-load all charted POI IDs once (prevents N+1 queries)
        $chartedPoiIds = $player->getChartedPoiIds();

        $systems = $nearbySystems->map(function ($system) use ($chartedPoiIds) {
            // Check if player has a star chart for this system (in-memory lookup)
            $hasChart = in_array($system->id, $chartedPoiIds, true);
            $typeLabel = is_object($system->type) ? $system->type->label() : $system->type;

            return [
                'uuid' => $system->uuid,
                'name' => $hasChart ? $system->name : 'Unknown System',
                'type' => $typeLabel,
                'distance' => round($system->distance, 2),
                'coordinates' => $hasChart ? [
                    'x' => (float) $system->x,
                    'y' => (float) $system->y,
                ] : null,
                'is_inhabited' => $system->is_inhabited,
                'has_chart' => $hasChart,
            ];
        });

        return $this->success([
            'current_location' => [
                'name' => $currentLocation->name,
                'coordinates' => [
                    'x' => (float) $currentLocation->x,
                    'y' => (float) $currentLocation->y,
                ],
            ],
            'sensor_range' => $sensorRange,
            'sensor_level' => $sensorLevel,
            'systems_detected' => $systems->count(),
            'nearby_systems' => $systems,
        ]);
    }

    /**
     * Get detailed scan of all nearby POIs (not just stars)
     *
     * GET /api/players/{uuid}/scan-local
     */
    public function scanLocal(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with(['currentLocation', 'activeShip'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        if (! $player->currentLocation) {
            return $this->error('Player has no current location', 'NO_LOCATION');
        }

        if (! $player->activeShip) {
            return $this->error('Player has no active ship', 'NO_ACTIVE_SHIP');
        }

        $currentLocation = $player->currentLocation;
        $sensorLevel = $player->activeShip->sensors;
        $sensorRange = $sensorLevel * 100;

        // Find all POIs within sensor range (including planets, asteroids, nebulae, etc.)
        // Use bounding box pre-filter to reduce spatial calculations
        $x = $currentLocation->x;
        $y = $currentLocation->y;

        $nearbyPOIs = PointOfInterest::where('galaxy_id', $currentLocation->galaxy_id)
            ->where('id', '!=', $currentLocation->id)
            // Bounding box filter (can use indexes)
            ->where('x', '>=', $x - $sensorRange)
            ->where('x', '<=', $x + $sensorRange)
            ->where('y', '>=', $y - $sensorRange)
            ->where('y', '<=', $y + $sensorRange)
            ->selectRaw('*, SQRT(POW(x - ?, 2) + POW(y - ?, 2)) as distance', [$x, $y])
            // Precise circular distance (on remaining candidates)
            ->whereRaw('SQRT(POW(x - ?, 2) + POW(y - ?, 2)) <= ?', [$x, $y, $sensorRange])
            ->orderBy('distance')
            ->limit(100)
            ->get();

        // Pre-load all charted POI IDs once (prevents N+1 queries)
        $chartedPoiIds = $player->getChartedPoiIds();

        // Group by type for easier navigation
        $groupedByType = $nearbyPOIs->groupBy('type')->map(function ($group, $type) use ($chartedPoiIds) {
            return $group->map(function ($poi) use ($chartedPoiIds) {
                // In-memory lookup instead of database query
                $hasChart = in_array($poi->id, $chartedPoiIds, true);

                $typeLabel = is_object($poi->type) ? $poi->type->label() : $poi->type;

                return [
                    'uuid' => $poi->uuid,
                    'name' => $hasChart ? $poi->name : "Unknown {$typeLabel}",
                    'type' => $typeLabel,
                    'distance' => round($poi->distance, 2),
                    'coordinates' => $hasChart ? [
                        'x' => (float) $poi->x,
                        'y' => (float) $poi->y,
                    ] : null,
                    'is_inhabited' => $poi->is_inhabited ?? false,
                    'has_chart' => $hasChart,
                    'parent_poi' => $poi->parent_poi_id ? [
                        'id' => $poi->parent_poi_id,
                    ] : null,
                ];
            })->values();
        });

        return $this->success([
            'current_location' => [
                'name' => $currentLocation->name,
                'type' => $currentLocation->type,
                'coordinates' => [
                    'x' => (float) $currentLocation->x,
                    'y' => (float) $currentLocation->y,
                ],
            ],
            'sensor_range' => $sensorRange,
            'sensor_level' => $sensorLevel,
            'total_pois_detected' => $nearbyPOIs->count(),
            'pois_by_type' => $groupedByType,
        ]);
    }

    /**
     * Get local bodies (planets, moons, asteroids) at the current location.
     *
     * GET /api/players/{uuid}/local-bodies
     *
     * Returns all orbital bodies within the current star system.
     */
    public function getLocalBodies(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->unauthorized();
        }

        $player = Player::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->with(['currentLocation.sector'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $currentLocation = $player->currentLocation;

        if (! $currentLocation) {
            return $this->error('Player has no current location', 'NO_LOCATION', null, 400);
        }

        // Get all children (planets, moons, asteroid belts, etc.)
        $children = $currentLocation->children()
            ->orderBy('orbital_index')
            ->get();

        // Categorize bodies using PoiCategorizer
        $bodies = [
            'planets' => [],
            'moons' => [],
            'asteroid_belts' => [],
            'stations' => [],
            'defense_platforms' => [],
            'other' => [],
        ];

        foreach ($children as $child) {
            $bodyData = [
                'uuid' => $child->uuid,
                'name' => $child->name,
                'type' => $child->type?->value,
                'type_label' => $child->type?->label(),
                'orbital_index' => $child->orbital_index,
                'is_inhabited' => $child->is_inhabited ?? false,
                'has_colony' => $child->colony !== null,
                'attributes' => $this->getVisibleAttributes($child),
            ];

            // Check for moons of this body
            $moons = $child->children()
                ->where('type', PointOfInterestType::MOON)
                ->get();

            if ($moons->isNotEmpty()) {
                $bodyData['moons'] = $moons->map(fn ($moon) => [
                    'uuid' => $moon->uuid,
                    'name' => $moon->name,
                    'is_inhabited' => $moon->is_inhabited ?? false,
                ])->toArray();
            }

            // Categorize by type using PoiCategorizer
            $category = PoiCategorizer::categorize($child->type);

            // Map derelicts to stations for this response (simplified view)
            if ($category === PoiCategorizer::CATEGORY_DERELICTS) {
                $category = 'stations';
            } elseif ($category === PoiCategorizer::CATEGORY_ANOMALIES) {
                $category = 'other';
            }

            $bodies[$category][] = $bodyData;
        }

        // Get sector info
        $sector = $currentLocation->sector;

        return $this->success([
            'system' => [
                'uuid' => $currentLocation->uuid,
                'name' => $currentLocation->name,
                'type' => $currentLocation->type?->value,
                'coordinates' => [
                    'x' => (int) $currentLocation->x,
                    'y' => (int) $currentLocation->y,
                ],
                'is_inhabited' => $currentLocation->is_inhabited,
            ],
            'sector' => $sector ? [
                'uuid' => $sector->uuid,
                'name' => $sector->name,
                'grid' => ['x' => $sector->grid_x, 'y' => $sector->grid_y],
            ] : null,
            'bodies' => $bodies,
            'summary' => [
                'total_bodies' => $children->count(),
                'planets' => count($bodies['planets']),
                'moons' => count($bodies['moons']),
                'asteroid_belts' => count($bodies['asteroid_belts']),
                'stations' => count($bodies['stations']),
            ],
        ]);
    }

    /**
     * Get visible attributes for a body based on common properties.
     */
    private function getVisibleAttributes(PointOfInterest $body): array
    {
        $attrs = $body->attributes ?? [];
        $visible = [];

        // Basic properties that are always visible
        if (isset($attrs['habitable'])) {
            $visible['habitable'] = $attrs['habitable'];
        }
        if (isset($attrs['in_goldilocks_zone'])) {
            $visible['in_goldilocks_zone'] = $attrs['in_goldilocks_zone'];
        }
        if (isset($attrs['temperature'])) {
            $visible['temperature'] = $attrs['temperature'];
        }
        if (isset($attrs['atmosphere'])) {
            $visible['atmosphere'] = $attrs['atmosphere'];
        }
        if (isset($attrs['has_rings'])) {
            $visible['has_rings'] = $attrs['has_rings'];
        }
        if (isset($attrs['mineral_richness'])) {
            $visible['mineral_richness'] = $attrs['mineral_richness'];
        }

        return $visible;
    }
}

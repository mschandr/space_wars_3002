<?php

namespace App\Http\Controllers\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
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
        $nearbySystems = PointOfInterest::where('galaxy_id', $currentLocation->galaxy_id)
            ->where('id', '!=', $currentLocation->id)
            ->where('type', PointOfInterestType::STAR)
            ->selectRaw('
                *,
                SQRT(POW(x - ?, 2) + POW(y - ?, 2)) as distance
            ', [$currentLocation->x, $currentLocation->y])
            ->having('distance', '<=', $sensorRange)
            ->orderBy('distance')
            ->limit(50)
            ->get();

        $systems = $nearbySystems->map(function ($system) use ($player) {
            // Check if player has a star chart for this system
            $hasChart = $player->hasChartFor($system);
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
        $nearbyPOIs = PointOfInterest::where('galaxy_id', $currentLocation->galaxy_id)
            ->where('id', '!=', $currentLocation->id)
            ->selectRaw('
                *,
                SQRT(POW(x - ?, 2) + POW(y - ?, 2)) as distance
            ', [$currentLocation->x, $currentLocation->y])
            ->having('distance', '<=', $sensorRange)
            ->orderBy('distance')
            ->limit(100)
            ->get();

        // Group by type for easier navigation
        $groupedByType = $nearbyPOIs->groupBy('type')->map(function ($group, $type) use ($player) {
            return $group->map(function ($poi) use ($player) {
                $hasChart = $player->hasChartFor($poi);

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
}

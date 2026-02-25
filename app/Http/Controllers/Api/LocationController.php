<?php

namespace App\Http\Controllers\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Http\Controllers\Api\Builders\PoiCategorizer;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\Sector;
use App\Services\LaneKnowledgeService;
use App\Services\SystemScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Location Controller
 *
 * Provides information about locations in the galaxy.
 * Response detail varies based on player's knowledge (scans, charts, etc.)
 */
class LocationController extends BaseApiController
{
    public function __construct(
        private readonly SystemScanService $scanService,
        private readonly LaneKnowledgeService $laneKnowledgeService,
    ) {}

    /**
     * Get information about a location by coordinates or UUID.
     *
     * POST /api/location/current?x={x}&y={y}
     * POST /api/location/current/{uuid}
     *
     * @param  string|null  $uuid  Optional system UUID
     */
    public function current(Request $request, ?string $uuid = null): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Authentication required', 'UNAUTHENTICATED', null, 401);
        }

        // Get the player - need to find which galaxy they're in
        $player = Player::where('user_id', $user->id)
            ->whereNotNull('current_poi_id')
            ->with(['currentLocation.galaxy', 'activeShip'])
            ->first();

        if (! $player) {
            return $this->error(
                'No active player found',
                'NO_ACTIVE_PLAYER',
                null,
                404
            );
        }

        $galaxy = $player->currentLocation->galaxy;

        // Determine the location to query
        if ($uuid) {
            // Query by UUID
            $poi = PointOfInterest::where('uuid', $uuid)
                ->where('galaxy_id', $galaxy->id)
                ->first();

            if (! $poi) {
                return $this->notFound('System not found in current galaxy');
            }

            return $this->getSystemInfo($player, $poi);
        }

        // Query by coordinates
        $x = $request->query('x');
        $y = $request->query('y');

        if ($x === null || $y === null) {
            return $this->error(
                'Coordinates (x, y) or system UUID required',
                'MISSING_PARAMETERS',
                null,
                400
            );
        }

        $x = (int) $x;
        $y = (int) $y;

        // Find POI at coordinates
        $poi = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('x', $x)
            ->where('y', $y)
            ->first();

        if (! $poi) {
            // Empty space - find sector by coordinates
            $sector = $this->findSectorByCoordinates($galaxy->id, $x, $y);

            return $this->success([
                'location' => 'empty_space',
                'x' => $x,
                'y' => $y,
                'message' => 'User is in empty space',
                'sector' => $sector ? $this->formatSectorInfo($sector) : null,
            ]);
        }

        return $this->getSystemInfo($player, $poi);
    }

    /**
     * Get detailed system information based on player's knowledge level.
     */
    private function getSystemInfo(Player $player, PointOfInterest $poi): JsonResponse
    {
        // Determine knowledge level
        $scanLevel = $this->scanService->getScanLevelFor($player, $poi);
        $hasChart = $player->hasChartForId($poi->id);
        $isCurrentLocation = $player->current_poi_id === $poi->id;

        // Core systems and inhabited systems have baseline knowledge
        $isKnownSystem = $scanLevel >= 2 || $hasChart || $poi->is_inhabited;

        if (! $isKnownSystem && ! $isCurrentLocation) {
            // Unknown system - minimal info
            $sector = $poi->sector ?? $this->findSectorByCoordinates($poi->galaxy_id, $poi->x, $poi->y);

            return $this->success([
                'location' => 'star_system',
                'system_name' => $poi->name ?? 'Unknown System',
                'system_uuid' => $poi->uuid,
                'x' => (int) $poi->x,
                'y' => (int) $poi->y,
                'sector' => $sector ? $this->formatSectorInfo($sector) : null,
                'knowledge_level' => 'unknown',
                'inhabited' => 'unknown',
                'planets' => 'unknown',
                'minerals' => 'unknown',
                'colonies' => 'unknown',
            ]);
        }

        // Known system - build detailed response
        return $this->success($this->buildKnownSystemResponse($player, $poi, $scanLevel));
    }

    /**
     * Build detailed response for a known system.
     *
     * Optimized to load all related data once and pass through to helpers.
     */
    private function buildKnownSystemResponse(Player $player, PointOfInterest $poi, int $scanLevel): array
    {
        // Get sector info
        $sector = $poi->sector ?? $this->findSectorByCoordinates($poi->galaxy_id, $poi->x, $poi->y);

        // Load children ONCE for reuse across multiple methods
        $children = $poi->children()->get();

        // Load ALL gates (both directions) ONCE with all needed relationships for reuse
        $outgoing = $poi->outgoingGates()
            ->where('is_hidden', false)
            ->where('status', 'active')
            ->with(['destinationPoi', 'warpLanePirate'])
            ->get();

        $incoming = $poi->incomingGates()
            ->where('is_hidden', false)
            ->where('status', 'active')
            ->with(['sourcePoi', 'warpLanePirate'])
            ->get();

        $gates = $outgoing->merge($incoming);

        $response = [
            'location' => 'star_system',
            'system_name' => $poi->name,
            'system_uuid' => $poi->uuid,
            'x' => (int) $poi->x,
            'y' => (int) $poi->y,
            'sector' => $sector ? $this->formatSectorInfo($sector) : null,
            'type' => $poi->type?->value ?? 'unknown',
            'knowledge_level' => $this->getKnowledgeLevelLabel($scanLevel),
            'is_current_location' => $player->current_poi_id === $poi->id,
        ];

        // Inhabited info - pass pre-loaded children
        $response['inhabited'] = $this->getInhabitedInfo($poi, $scanLevel, $children);

        // Facilities and services - pass pre-loaded children and gates
        $response['has'] = $this->getFacilitiesInfo($player, $poi, $scanLevel, $children, $gates);

        // Danger info (if high enough scan level) - pass pre-loaded data
        if ($scanLevel >= 3) {
            $response['danger'] = $this->getDangerInfo($poi, $scanLevel, $gates, $children);
        }

        return $response;
    }

    /**
     * Get inhabited info for a system.
     *
     * @param  \Illuminate\Support\Collection|null  $children  Pre-loaded children collection
     */
    private function getInhabitedInfo(PointOfInterest $poi, int $scanLevel, ?\Illuminate\Support\Collection $children = null): array
    {
        $info = [
            'is_inhabited' => $poi->is_inhabited,
        ];

        if ($scanLevel < 1) {
            return $info;
        }

        // Use pre-loaded children or load once
        $children = $children ?? $poi->children()->get();

        $bodies = [];
        foreach ($children as $child) {
            $bodyType = PoiCategorizer::getBodyTypeLabel($child->type);
            if ($bodyType) {
                $bodies[] = [
                    'type' => $bodyType,
                    'uuid' => $child->uuid,
                    'name' => $child->name,
                ];
            }
        }

        if (! empty($bodies)) {
            $info['bodies'] = $bodies;
        }

        // Count by type
        $info['planet_count'] = $children->filter(fn ($c) => $c->type?->isSystemType())->count();
        $info['moon_count'] = $children->filter(fn ($c) => $c->type === PointOfInterestType::MOON)->count();
        $info['station_count'] = $children->filter(fn ($c) => $c->type === PointOfInterestType::DERELICT)->count();

        return $info;
    }

    /**
     * Get facilities and services info for a system.
     *
     * @param  \Illuminate\Support\Collection|null  $children  Pre-loaded children collection
     * @param  \Illuminate\Support\Collection|null  $gates  Pre-loaded gates collection
     */
    private function getFacilitiesInfo(Player $player, PointOfInterest $poi, int $scanLevel, ?\Illuminate\Support\Collection $children = null, ?\Illuminate\Support\Collection $gates = null): array
    {
        $facilities = [];

        // Use pre-loaded gates or load both directions
        if ($gates === null) {
            $outgoing = $poi->outgoingGates()
                ->where('is_hidden', false)
                ->where('status', 'active')
                ->with('destinationPoi')
                ->get();

            $incoming = $poi->incomingGates()
                ->where('is_hidden', false)
                ->where('status', 'active')
                ->with('sourcePoi')
                ->get();

            $gates = $outgoing->merge($incoming);
        }

        // Use bulk check instead of N individual queries
        $gateIds = $gates->pluck('id')->toArray();
        $knownLanes = $this->laneKnowledgeService->bulkKnowsLane($player, $gateIds);

        $knownGates = [];
        foreach ($gates as $gate) {
            $knowsLane = $knownLanes[$gate->id] ?? false;

            // For outgoing gates the other end is destinationPoi;
            // for incoming gates the other end is sourcePoi
            $isOutgoing = $gate->source_poi_id === $poi->id;
            $otherEnd = $isOutgoing ? $gate->destinationPoi : $gate->sourcePoi;

            if ($knowsLane || $poi->is_inhabited) {
                $knownGates[$gate->uuid] = [
                    'destination_uuid' => $otherEnd?->uuid,
                    'destination_name' => $otherEnd?->name ?? 'Unknown',
                    'distance' => round($gate->distance ?? $gate->calculateDistance(), 1),
                ];
            } else {
                // Show gate exists but destination unknown
                $knownGates[$gate->uuid] = [
                    'destination_uuid' => null,
                    'destination_name' => 'Unknown destination',
                    'distance' => null,
                ];
            }
        }

        if (! empty($knownGates)) {
            $facilities['gates'] = $knownGates;
            $facilities['gate_count'] = count($knownGates);
        }

        // Services available at this location
        $services = [];

        // Trading hub
        if ($poi->tradingHub && $poi->tradingHub->is_active) {
            $services[] = 'trading_hub';
        }

        // Stellar cartographer
        if ($poi->stellarCartographer) {
            $services[] = 'cartography';
        }

        // Ship shop
        if ($poi->shipShop && $poi->shipShop->is_active) {
            $services[] = 'ship_shop';
        }

        // Repair shop
        if ($poi->repairShop && $poi->repairShop->is_active) {
            $services[] = 'repair_yard';
        }

        // Plans shop
        if ($poi->plansShop && $poi->plansShop->is_active) {
            $services[] = 'plans_shop';
        }

        // Salvage yard (check children for derelicts - use pre-loaded collection)
        $children = $children ?? $poi->children()->get();
        $hasDerelicts = $children->contains(fn ($c) => $c->type === PointOfInterestType::DERELICT);
        if ($hasDerelicts) {
            $services[] = 'salvage_yard';
        }

        if (! empty($services)) {
            $facilities['services'] = $services;
        }

        // Orbital defenses (if scan level high enough)
        if ($scanLevel >= 4 && $poi->is_fortified) {
            $facilities['orbital_defenses'] = $this->getOrbitalDefenses($poi, $children);
        }

        return $facilities;
    }

    /**
     * Get orbital defense info.
     *
     * @param  \Illuminate\Support\Collection|null  $children  Pre-loaded children collection
     */
    private function getOrbitalDefenses(PointOfInterest $poi, ?\Illuminate\Support\Collection $children = null): array
    {
        $defenses = [];

        // Use pre-loaded children or load once
        $children = $children ?? $poi->children()->get();

        foreach ($children as $child) {
            $attrs = $child->attributes ?? [];

            if ($attrs['has_orbital_defenses'] ?? false) {
                $defenses[] = [
                    'type' => 'orbital_cannons',
                    'location' => $child->name,
                    'location_uuid' => $child->uuid,
                ];
            }

            if ($attrs['has_defense_platform'] ?? false) {
                $defenses[] = [
                    'type' => 'defense_platform',
                    'location' => $child->name,
                    'location_uuid' => $child->uuid,
                ];
            }
        }

        // Check POI itself for system-wide defenses
        $poiAttrs = $poi->attributes ?? [];
        if ($poiAttrs['has_defense_grid'] ?? false) {
            $defenses[] = [
                'type' => 'defense_grid',
                'location' => 'system_wide',
            ];
        }

        return $defenses;
    }

    /**
     * Get danger info for a system.
     *
     * @param  \Illuminate\Support\Collection|null  $gates  Pre-loaded gates with warpLanePirate relationship
     * @param  \Illuminate\Support\Collection|null  $children  Pre-loaded children collection
     */
    private function getDangerInfo(PointOfInterest $poi, int $scanLevel, ?\Illuminate\Support\Collection $gates = null, ?\Illuminate\Support\Collection $children = null): array
    {
        $danger = [
            'threat_level' => 'low',
        ];

        // Use pre-loaded gates or load with pirate relationship
        if ($gates === null) {
            $gates = $poi->outgoingGates()->with('warpLanePirate')->get();
        }

        // Count pirate-infested gates from pre-loaded collection
        $pirateGates = $gates->filter(fn ($g) => $g->warpLanePirate !== null)->count();

        if ($pirateGates > 0 && $scanLevel >= 5) {
            $danger['pirate_presence'] = true;
            $danger['affected_lanes'] = $pirateGates;
        }

        // Determine threat level
        if ($pirateGates >= 3) {
            $danger['threat_level'] = 'high';
        } elseif ($pirateGates >= 1) {
            $danger['threat_level'] = 'medium';
        }

        // Check for anomalies (scan level 6+) - use pre-loaded children
        if ($scanLevel >= 6) {
            $children = $children ?? $poi->children()->get();
            $anomalies = $children->filter(fn ($c) => $c->type === PointOfInterestType::ANOMALY)->count();

            if ($anomalies > 0) {
                $danger['anomalies'] = $anomalies;
            }
        }

        return $danger;
    }

    /**
     * Get knowledge level label.
     */
    private function getKnowledgeLevelLabel(int $scanLevel): string
    {
        return match (true) {
            $scanLevel >= 7 => 'complete',
            $scanLevel >= 5 => 'detailed',
            $scanLevel >= 3 => 'moderate',
            $scanLevel >= 1 => 'basic',
            default => 'minimal',
        };
    }

    /**
     * Find sector by coordinates.
     */
    private function findSectorByCoordinates(int $galaxyId, float $x, float $y): ?Sector
    {
        return Sector::where('galaxy_id', $galaxyId)
            ->where('x_min', '<=', $x)
            ->where('x_max', '>=', $x)
            ->where('y_min', '<=', $y)
            ->where('y_max', '>=', $y)
            ->first();
    }

    /**
     * Format sector information for response.
     */
    private function formatSectorInfo(Sector $sector): array
    {
        return [
            'uuid' => $sector->uuid,
            'name' => $sector->name,
            'grid' => [
                'x' => $sector->grid_x,
                'y' => $sector->grid_y,
            ],
            'bounds' => [
                'x_min' => (int) $sector->x_min,
                'x_max' => (int) $sector->x_max,
                'y_min' => (int) $sector->y_min,
                'y_max' => (int) $sector->y_max,
            ],
            'danger_level' => $sector->getDangerLevel(),
            'display_name' => $sector->getDisplayName(),
        ];
    }
}

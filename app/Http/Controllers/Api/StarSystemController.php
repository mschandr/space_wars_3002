<?php

namespace App\Http\Controllers\Api;

use App\Enums\Exploration\ScanLevel;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Http\Controllers\Api\Builders\ParentStarResolver;
use App\Http\Controllers\Api\Builders\StarSystemResponseBuilder;
use App\Http\Controllers\Api\Builders\SystemGenerationHandler;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Services\SystemPopulationService;
use App\Services\SystemScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Star System Controller
 *
 * Provides comprehensive star system data endpoints.
 * Visibility is based on:
 * - Inhabited systems: All details visible to any visitor
 * - Uninhabited systems: Details filtered by ship sensor level + existing scans
 *
 * Uses extracted helper classes:
 * - StarSystemResponseBuilder: Constructs complex response structures
 * - SystemGenerationHandler: Manages async generation status
 */
class StarSystemController extends BaseApiController
{
    public function __construct(
        protected SystemScanService $scanService,
        protected SystemPopulationService $populationService,
        protected StarSystemResponseBuilder $responseBuilder,
        protected SystemGenerationHandler $generationHandler
    ) {}

    /**
     * Get comprehensive star system details.
     *
     * GET /api/players/{playerUuid}/star-systems/{systemUuid}
     */
    public function show(string $playerUuid, string $systemUuid, Request $request): JsonResponse
    {
        $result = $this->findAuthenticatedPlayerOrFail($playerUuid, $request, ['activeShip']);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $player = $result;

        // Find the star system
        $system = PointOfInterest::where('uuid', $systemUuid)
            ->where('galaxy_id', $player->galaxy_id)
            ->with(['sector', 'tradingHub', 'outgoingGates' => function ($query) {
                $query->where('is_hidden', false)->where('status', 'active');
            }])
            ->first();

        if (! $system) {
            return $this->notFound('Star system not found');
        }

        // Handle async generation
        $genResult = $this->generationHandler->checkAndHandle($system);

        if ($genResult['needs_generation'] && $genResult['response']) {
            return $genResult['response'];
        }

        // Reload with fresh data if generation just completed
        $system = $system->fresh(['sector', 'tradingHub', 'outgoingGates' => function ($query) {
            $query->where('is_hidden', false)->where('status', 'active');
        }]);

        // Determine visibility and build response
        $visibilityLevel = $this->determineVisibilityLevel($player, $system);
        $response = $this->responseBuilder
            ->for($system, $player, $visibilityLevel)
            ->build();

        return $this->success($response, 'Star system data retrieved');
    }

    /**
     * Check generation status for a star system.
     *
     * GET /api/players/{playerUuid}/star-systems/{systemUuid}/status
     */
    public function status(string $playerUuid, string $systemUuid, Request $request): JsonResponse
    {
        $result = $this->findAuthenticatedPlayerOrFail($playerUuid, $request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $player = $result;

        $system = PointOfInterest::where('uuid', $systemUuid)
            ->where('galaxy_id', $player->galaxy_id)
            ->first();

        if (! $system) {
            return $this->notFound('Star system not found');
        }

        $status = $this->generationHandler->getStatus($system);

        if ($status['status'] === 'error') {
            return $this->error(
                $status['details']['message'] ?? 'Generation failed',
                'GENERATION_FAILED',
                ['system_uuid' => $system->uuid],
                500
            );
        }

        return $this->success([
            'status' => $status['status'],
            'system_uuid' => $system->uuid,
            'system_name' => $system->name,
            'ready' => $status['ready'],
            'progress' => $status['details']['progress'] ?? null,
            'percent' => $status['details']['percent'] ?? 0,
            'started_at' => $status['details']['started_at'] ?? null,
            'completed_at' => $status['details']['completed_at'] ?? null,
            'polling' => $status['ready'] ? null : ['retry_after' => 5],
        ], $status['ready'] ? 'System data is ready' : 'System generation in progress');
    }

    /**
     * Get list of star systems the player knows about.
     *
     * GET /api/players/{playerUuid}/star-systems
     */
    public function index(string $playerUuid, Request $request): JsonResponse
    {
        $result = $this->findAuthenticatedPlayerOrFail($playerUuid, $request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $player = $result;

        // Get filter parameters
        $filter = $request->input('filter', 'known');
        $limit = min((int) $request->input('limit', 50), 200);
        $offset = (int) $request->input('offset', 0);

        $query = PointOfInterest::where('galaxy_id', $player->galaxy_id)
            ->where('type', PointOfInterestType::STAR);

        $query = $this->applySystemFilter($query, $player, $filter);

        $total = $query->count();
        $systems = $query->orderBy('name')
            ->skip($offset)
            ->take($limit)
            ->get();

        $chartedPoiIds = $player->getChartedPoiIds();

        $systemList = $systems->map(function ($system) use ($player, $chartedPoiIds) {
            $scanLevel = $this->scanService->getScanLevelFor($player, $system);
            $hasChart = in_array($system->id, $chartedPoiIds, true);

            return [
                'uuid' => $system->uuid,
                'name' => $hasChart || $system->is_inhabited ? $system->name : 'Unknown System',
                'x' => $hasChart || $system->is_inhabited ? (float) $system->x : null,
                'y' => $hasChart || $system->is_inhabited ? (float) $system->y : null,
                'is_inhabited' => $system->is_inhabited,
                'has_chart' => $hasChart,
                'scan_level' => $scanLevel,
                'scan_level_label' => ScanLevel::fromSensorLevel($scanLevel)->label(),
            ];
        });

        return $this->success([
            'systems' => $systemList,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total,
            ],
            'filter' => $filter,
        ], 'Star systems retrieved');
    }

    /**
     * Get a summary of the current star system.
     *
     * GET /api/players/{playerUuid}/current-system
     */
    public function current(string $playerUuid, Request $request): JsonResponse
    {
        $result = $this->findPlayerWithLocationOrFail($playerUuid, $request, ['activeShip']);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $player = $result;
        $currentLocation = $player->currentLocation;

        // Find the parent star system
        $system = ParentStarResolver::resolve($currentLocation) ?? $currentLocation;

        // Reload with relationships
        $system = PointOfInterest::where('id', $system->id)
            ->with(['sector', 'tradingHub', 'outgoingGates' => function ($query) {
                $query->where('is_hidden', false)->where('status', 'active');
            }])
            ->first();

        // Handle async generation
        $genResult = $this->generationHandler->checkAndHandle($system);

        if ($genResult['needs_generation'] && $genResult['response']) {
            // Add current position to generation response
            $responseData = $genResult['response']->getData(true);
            $responseData['data']['current_position'] = $this->buildCurrentPosition($currentLocation, $system);

            return response()->json($responseData, 202);
        }

        // Reload with fresh data
        $system = $system->fresh(['sector', 'tradingHub', 'outgoingGates' => function ($query) {
            $query->where('is_hidden', false)->where('status', 'active');
        }]);

        $visibilityLevel = $this->determineVisibilityLevel($player, $system);
        $response = $this->responseBuilder
            ->for($system, $player, $visibilityLevel)
            ->build();

        $response['current_position'] = $this->buildCurrentPosition($currentLocation, $system);

        return $this->success($response, 'Current system data retrieved');
    }

    /**
     * Determine the visibility level for a system.
     */
    protected function determineVisibilityLevel(Player $player, PointOfInterest $system): int
    {
        if ($system->is_inhabited) {
            return ScanLevel::FULL_VISIBILITY;
        }

        $baselineLevel = $this->scanService->getBaselineScanLevel($system);
        $scanLevel = $this->scanService->getScanLevelFor($player, $system);
        $visibilityLevel = max($baselineLevel, $scanLevel);

        if ($player->activeShip) {
            $sensorLevel = $player->activeShip->sensors ?? 1;

            if ($player->current_poi_id === $system->id ||
                ($player->currentLocation && $player->currentLocation->parent_poi_id === $system->id)) {
                $visibilityLevel = max($visibilityLevel, $sensorLevel);
            }
        }

        return $visibilityLevel;
    }

    /**
     * Apply filter to systems query.
     */
    protected function applySystemFilter($query, Player $player, string $filter)
    {
        switch ($filter) {
            case 'inhabited':
                return $query->where('is_inhabited', true);

            case 'scanned':
                $scannedPoiIds = $player->systemScans()->pluck('poi_id');

                return $query->whereIn('id', $scannedPoiIds);

            case 'charted':
                $chartedPoiIds = $player->getChartedPoiIds();

                return $query->whereIn('id', $chartedPoiIds);

            case 'known':
            default:
                $scannedPoiIds = $player->systemScans()->pluck('poi_id')->toArray();
                $chartedPoiIds = $player->getChartedPoiIds();
                $knownIds = array_unique(array_merge($scannedPoiIds, $chartedPoiIds));

                return $query->where(function ($q) use ($knownIds) {
                    $q->where('is_inhabited', true)
                        ->orWhereIn('id', $knownIds);
                });
        }
    }

    /**
     * Build current position info.
     */
    protected function buildCurrentPosition(PointOfInterest $location, PointOfInterest $system): array
    {
        return [
            'uuid' => $location->uuid,
            'name' => $location->name,
            'type' => $location->type?->value,
            'type_label' => $location->type?->label(),
            'is_at_star' => $location->id === $system->id,
        ];
    }
}

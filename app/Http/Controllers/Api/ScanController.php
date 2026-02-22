<?php

namespace App\Http\Controllers\Api;

use App\Models\Player;
use App\Models\PointOfInterest;
use App\Services\SystemScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Scan Controller
 *
 * Handles system scanning and scan data retrieval.
 * Scans reveal information about a system based on ship sensor level.
 */
class ScanController extends BaseApiController
{
    public function __construct(
        protected SystemScanService $scanService
    ) {}

    /**
     * Scan a system (current location or nearby).
     *
     * POST /api/players/{uuid}/scan-system
     *
     * @param  string  $uuid  Player UUID
     */
    public function scanSystem(string $uuid, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->unauthorized();
        }

        $player = Player::where('uuid', $uuid)
            ->with(['activeShip', 'currentLocation'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $this->authorizePlayer($player, $user);

        if (! $player->activeShip) {
            return $this->error('No active ship', 'NO_SHIP', null, 400);
        }

        // Determine target POI (current location or specified)
        $targetPoiUuid = $request->input('uuid') ?? $request->input('poi_uuid');
        if ($targetPoiUuid) {
            $targetPoi = PointOfInterest::where('uuid', $targetPoiUuid)
                ->where('galaxy_id', $player->galaxy_id)
                ->first();

            if (! $targetPoi) {
                return $this->notFound('System not found');
            }

            // Check if target is within scan range
            $currentPoi = $player->currentLocation;
            if ($currentPoi && $targetPoi->id !== $currentPoi->id) {
                $distance = sqrt(
                    pow($targetPoi->x - $currentPoi->x, 2) +
                    pow($targetPoi->y - $currentPoi->y, 2)
                );

                $scanRange = $this->getScanRange($player->activeShip);
                if ($distance > $scanRange) {
                    return $this->error(
                        'System out of scan range',
                        'OUT_OF_RANGE',
                        [
                            'distance' => round($distance, 2),
                            'max_range' => $scanRange,
                        ],
                        400
                    );
                }
            }
        } else {
            $targetPoi = $player->currentLocation;
            if (! $targetPoi) {
                return $this->error('No current location', 'NO_LOCATION', null, 400);
            }
        }

        $forceScan = $request->boolean('force', false);
        $result = $this->scanService->scanSystem($player, $targetPoi, $forceScan);

        if (! $result['success']) {
            return $this->error($result['message'], 'SCAN_FAILED', $result, 400);
        }

        return $this->success([
            'system' => [
                'uuid' => $targetPoi->uuid,
                'name' => $targetPoi->name,
                'x' => $targetPoi->x,
                'y' => $targetPoi->y,
            ],
            'scan_level' => $result['scan_level'],
            'scan_data' => $result['scan_data'],
            'cached' => $result['cached'] ?? false,
            'can_reveal_more' => $result['can_reveal_more'],
            'next_level_reveals' => $result['next_level_reveals'],
            'new_discoveries' => $result['new_discoveries'] ?? [],
        ], $result['message']);
    }

    /**
     * Get scan results for a specific system.
     *
     * GET /api/players/{uuid}/scan-results/{poiUuid}
     *
     * @param  string  $uuid  Player UUID
     * @param  string  $poiUuid  POI UUID
     */
    public function getScanResults(string $uuid, string $poiUuid, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->unauthorized();
        }

        $player = Player::where('uuid', $uuid)->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $this->authorizePlayer($player, $user);

        $poi = PointOfInterest::where('uuid', $poiUuid)
            ->where('galaxy_id', $player->galaxy_id)
            ->first();

        if (! $poi) {
            return $this->notFound('System not found');
        }

        $results = $this->scanService->getScanResults($player, $poi);

        return $this->success([
            'system' => [
                'uuid' => $poi->uuid,
                'name' => $poi->name,
                'type' => $poi->type->label(),
                'x' => $poi->x,
                'y' => $poi->y,
                'is_inhabited' => $poi->is_inhabited,
            ],
            'scan' => $results,
        ], 'Scan results retrieved');
    }

    /**
     * Get full exploration log (all scanned systems).
     *
     * GET /api/players/{uuid}/exploration-log
     *
     * @param  string  $uuid  Player UUID
     */
    public function explorationLog(string $uuid, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->unauthorized();
        }

        $player = Player::where('uuid', $uuid)->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $this->authorizePlayer($player, $user);

        $scans = $this->scanService->getPlayerScannedSystems($player);

        $entries = $scans->map(function ($scan) {
            $poi = $scan->pointOfInterest;

            return [
                'uuid' => $scan->uuid,
                'system' => [
                    'uuid' => $poi->uuid,
                    'name' => $poi->name,
                    'type' => $poi->type->label(),
                    'x' => $poi->x,
                    'y' => $poi->y,
                    'is_inhabited' => $poi->is_inhabited,
                    'region' => $poi->region?->value ?? 'unknown',
                ],
                'scan_level' => $scan->scan_level,
                'scan_level_label' => $scan->getScanLevelEnum()->label(),
                'scanned_at' => $scan->scanned_at?->toIso8601String(),
                'can_reveal_more' => $scan->canRevealMore(),
                'display' => [
                    'color' => $scan->getColor(),
                    'opacity' => $scan->getOpacity(),
                ],
            ];
        });

        // Get statistics
        $stats = [
            'total_scanned' => $scans->count(),
            'by_level' => $scans->groupBy('scan_level')->map->count(),
            'by_region' => $scans->groupBy(fn ($s) => $s->pointOfInterest->region?->value ?? 'unknown')->map->count(),
        ];

        return $this->success([
            'entries' => $entries,
            'statistics' => $stats,
        ], 'Exploration log retrieved');
    }

    /**
     * Get scan levels for multiple systems (for map display).
     *
     * POST /api/players/{uuid}/bulk-scan-levels
     *
     * @param  string  $uuid  Player UUID
     */
    public function bulkScanLevels(string $uuid, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->unauthorized();
        }

        $player = Player::where('uuid', $uuid)->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $this->authorizePlayer($player, $user);

        $poiUuids = $request->input('uuids') ?? $request->input('poi_uuids', []);
        if (empty($poiUuids) || ! is_array($poiUuids)) {
            return $this->validationError(['uuids' => 'Array of system UUIDs required']);
        }

        // Limit to prevent abuse
        if (count($poiUuids) > 500) {
            return $this->error('Too many POIs requested (max 500)', 'TOO_MANY', null, 400);
        }

        // Get POI IDs from UUIDs
        $pois = PointOfInterest::whereIn('uuid', $poiUuids)
            ->where('galaxy_id', $player->galaxy_id)
            ->get()
            ->keyBy('uuid');

        $poiIds = $pois->pluck('id')->toArray();
        $scanLevels = $this->scanService->getBulkScanLevels($player, $poiIds);

        // Map back to UUIDs
        $result = [];
        foreach ($pois as $uuid => $poi) {
            $level = $scanLevels[$poi->id] ?? 0;
            $scanLevelEnum = \App\Enums\Exploration\ScanLevel::fromSensorLevel($level);

            $result[$uuid] = [
                'scan_level' => $level,
                'color' => $scanLevelEnum->color(),
                'opacity' => $scanLevelEnum->opacity(),
                'label' => $scanLevelEnum->label(),
            ];
        }

        return $this->success([
            'scan_levels' => $result,
        ], 'Bulk scan levels retrieved');
    }

    /**
     * Get filtered system data based on player's scan level.
     *
     * GET /api/players/{uuid}/system-data/{poiUuid}
     *
     * @param  string  $uuid  Player UUID
     * @param  string  $poiUuid  POI UUID
     */
    public function getSystemData(string $uuid, string $poiUuid, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->unauthorized();
        }

        $player = Player::where('uuid', $uuid)->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $this->authorizePlayer($player, $user);

        $poi = PointOfInterest::where('uuid', $poiUuid)
            ->where('galaxy_id', $player->galaxy_id)
            ->first();

        if (! $poi) {
            return $this->notFound('System not found');
        }

        $scanLevel = $this->scanService->getScanLevelFor($player, $poi);
        $filteredData = $this->scanService->getFilteredSystemData($poi, $scanLevel);

        return $this->success([
            'system_data' => $filteredData,
            'scan_level' => $scanLevel,
        ], 'System data retrieved');
    }

    /**
     * Calculate scan range based on ship sensors.
     */
    protected function getScanRange($ship): float
    {
        return \App\Support\SensorRangeCalculator::getRangeLY($ship->sensors ?? 1);
    }
}

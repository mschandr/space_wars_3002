<?php

namespace App\Http\Controllers\Api\Builders;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Jobs\PopulateStarSystemJob;
use App\Models\PointOfInterest;
use App\Services\SystemPopulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Handles star system generation status and polling.
 *
 * Consolidates the generation status handling logic that was
 * duplicated between show() and current() methods in StarSystemController.
 */
class SystemGenerationHandler
{
    public function __construct(
        protected SystemPopulationService $populationService
    ) {}

    /**
     * Check if a system needs generation and handle accordingly.
     *
     * @param  PointOfInterest  $system  The star system
     * @return array{needs_generation: bool, response: ?JsonResponse, status: ?array}
     */
    public function checkAndHandle(PointOfInterest $system): array
    {
        $needsPopulation = $this->populationService->needsPopulation($system);

        if (! $needsPopulation) {
            return [
                'needs_generation' => false,
                'response' => null,
                'status' => null,
            ];
        }

        $cacheKey = PopulateStarSystemJob::getCacheKey($system->uuid);
        $generationStatus = Cache::get($cacheKey);

        if ($generationStatus) {
            // Generation is in progress or completed
            if ($generationStatus['status'] === 'generating') {
                return [
                    'needs_generation' => true,
                    'response' => $this->generatingResponse($system, $generationStatus),
                    'status' => $generationStatus,
                ];
            }

            if ($generationStatus['status'] === 'error') {
                Cache::forget($cacheKey);

                return [
                    'needs_generation' => true,
                    'response' => $this->errorResponse($generationStatus),
                    'status' => $generationStatus,
                ];
            }

            // Status is 'complete' - clear cache and continue
            Cache::forget($cacheKey);

            return [
                'needs_generation' => false,
                'response' => null,
                'status' => $generationStatus,
            ];
        }

        // No generation in progress - start one
        $initialStatus = [
            'status' => 'generating',
            'progress' => 'Initializing long-range sensors...',
            'started_at' => now()->toIso8601String(),
            'percent' => 0,
        ];

        Cache::put($cacheKey, $initialStatus, now()->addMinutes(10));
        PopulateStarSystemJob::dispatch($system->id, $system->uuid);

        return [
            'needs_generation' => true,
            'response' => $this->generatingResponse($system, $initialStatus),
            'status' => $initialStatus,
        ];
    }

    /**
     * Get just the generation status without starting generation.
     *
     * @param  PointOfInterest  $system  The star system
     * @return array{ready: bool, status: string, details: ?array}
     */
    public function getStatus(PointOfInterest $system): array
    {
        $needsPopulation = $this->populationService->needsPopulation($system);

        if (! $needsPopulation) {
            return [
                'ready' => true,
                'status' => 'complete',
                'details' => null,
            ];
        }

        $cacheKey = PopulateStarSystemJob::getCacheKey($system->uuid);
        $generationStatus = Cache::get($cacheKey);

        if (! $generationStatus) {
            return [
                'ready' => false,
                'status' => 'pending',
                'details' => [
                    'message' => 'Generation not started. Request system details to begin.',
                ],
            ];
        }

        return [
            'ready' => $generationStatus['status'] === 'complete',
            'status' => $generationStatus['status'],
            'details' => $generationStatus,
        ];
    }

    /**
     * Build the "generating" response.
     */
    protected function generatingResponse(PointOfInterest $system, array $status): JsonResponse
    {
        $facilitiesPreview = null;
        if ($system->is_inhabited) {
            $facilitiesPreview = $this->buildFacilitiesPreview($system);
        }

        return response()->json([
            'success' => true,
            'status' => 'generating',
            'message' => 'System data is being generated...',
            'data' => [
                'system' => [
                    'uuid' => $system->uuid,
                    'name' => $system->name,
                    'is_inhabited' => $system->is_inhabited,
                    'region' => $system->region?->value,
                    'coordinates' => [
                        'x' => (float) $system->x,
                        'y' => (float) $system->y,
                    ],
                ],
                'facilities_preview' => $facilitiesPreview,
                'generation' => [
                    'progress' => $status['progress'] ?? 'Generating...',
                    'percent' => $status['percent'] ?? 0,
                    'started_at' => $status['started_at'] ?? null,
                ],
                'polling' => [
                    'retry_after' => 5,
                    'message' => 'Poll this endpoint again in 5 seconds',
                ],
            ],
        ], 202);
    }

    /**
     * Build error response.
     */
    protected function errorResponse(array $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'GENERATION_FAILED',
                'message' => $status['message'] ?? 'Generation failed',
                'details' => null,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], 500);
    }

    /**
     * Build a quick preview of facilities.
     */
    protected function buildFacilitiesPreview(PointOfInterest $system): array
    {
        $preview = [
            'has_trading_hub' => $system->tradingHub !== null,
            'has_bar' => $system->is_inhabited,
        ];

        $stationTypes = $system->children()
            ->whereIn('type', [
                PointOfInterestType::TRADING_STATION,
                PointOfInterestType::SHIPYARD,
                PointOfInterestType::SALVAGE_YARD,
            ])
            ->pluck('type')
            ->unique()
            ->map(fn ($t) => $t->value)
            ->toArray();

        $preview['has_shipyard'] = in_array(PointOfInterestType::SHIPYARD->value, $stationTypes);
        $preview['has_salvage_yard'] = in_array(PointOfInterestType::SALVAGE_YARD->value, $stationTypes);
        $preview['has_trading_stations'] = in_array(PointOfInterestType::TRADING_STATION->value, $stationTypes);

        if ($system->tradingHub) {
            $preview['has_cartographer'] = $system->tradingHub->has_cartographer ?? false;
        }

        return $preview;
    }
}

<?php

namespace App\Jobs;

use App\Models\PointOfInterest;
use App\Services\SystemPopulationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Background job to populate star system details.
 *
 * Used for lazy generation of system data on first access.
 * Progress is tracked via cache for polling by the frontend.
 */
class PopulateStarSystemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $systemId,
        public string $systemUuid
    ) {}

    /**
     * Get the cache key for this system's generation status.
     */
    public static function getCacheKey(string $systemUuid): string
    {
        return "system_generation:{$systemUuid}";
    }

    /**
     * Execute the job.
     */
    public function handle(SystemPopulationService $populationService): void
    {
        $cacheKey = self::getCacheKey($this->systemUuid);

        try {
            // Update status to show we're working
            Cache::put($cacheKey, [
                'status' => 'generating',
                'progress' => 'Analyzing stellar composition...',
                'started_at' => now()->toIso8601String(),
                'percent' => 10,
            ], now()->addMinutes(10));

            $system = PointOfInterest::find($this->systemId);

            if (! $system) {
                Cache::put($cacheKey, [
                    'status' => 'error',
                    'message' => 'System not found',
                ], now()->addMinutes(5));

                return;
            }

            // Progress updates during generation
            Cache::put($cacheKey, [
                'status' => 'generating',
                'progress' => 'Mapping orbital bodies...',
                'percent' => 30,
            ], now()->addMinutes(10));

            // Actually populate the system
            $wasPopulated = $populationService->ensurePopulated($system);

            Cache::put($cacheKey, [
                'status' => 'generating',
                'progress' => 'Cataloging planetary atmospheres...',
                'percent' => 60,
            ], now()->addMinutes(10));

            // Small delay to simulate more complex generation for UX
            usleep(500000); // 0.5 seconds

            Cache::put($cacheKey, [
                'status' => 'generating',
                'progress' => 'Surveying mineral deposits...',
                'percent' => 80,
            ], now()->addMinutes(10));

            usleep(500000); // 0.5 seconds

            // Mark as complete
            Cache::put($cacheKey, [
                'status' => 'complete',
                'completed_at' => now()->toIso8601String(),
                'was_generated' => $wasPopulated,
                'percent' => 100,
            ], now()->addMinutes(5));

            Log::info('Star system populated', [
                'system_id' => $this->systemId,
                'system_uuid' => $this->systemUuid,
                'was_generated' => $wasPopulated,
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to populate star system', [
                'system_id' => $this->systemId,
                'system_uuid' => $this->systemUuid,
                'error' => $e->getMessage(),
            ]);

            Cache::put($cacheKey, [
                'status' => 'error',
                'message' => 'Generation failed, please try again',
                'error' => $e->getMessage(),
            ], now()->addMinutes(5));

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $cacheKey = self::getCacheKey($this->systemUuid);

        Cache::put($cacheKey, [
            'status' => 'error',
            'message' => 'Generation failed after multiple attempts',
        ], now()->addMinutes(5));
    }
}

<?php

namespace App\Services\Economy;

use App\Models\ConstructionJob;
use App\Models\Galaxy;

/**
 * ConstructionTickService
 *
 * Processes construction job maturation during economy ticks.
 * Completes any pending jobs that have reached their completes_at time.
 */
class ConstructionTickService
{
    public function __construct(
        private readonly ConstructionService $constructionService,
    ) {}

    /**
     * Process construction job maturation for this tick
     *
     * @return array ['checked' => int, 'completed' => int, 'errors' => []]
     */
    public function processTick(?Galaxy $galaxy = null, bool $dryRun = false): array
    {
        // Query matured jobs: PENDING status and completes_at <= now()
        $query = ConstructionJob::pending()
            ->where('completes_at', '<=', now());

        if ($galaxy) {
            $query->byGalaxy($galaxy);
        }

        $maturedJobs = $query->get();
        $checked = $maturedJobs->count();
        $completed = 0;
        $errors = [];

        foreach ($maturedJobs as $job) {
            try {
                if (!$dryRun) {
                    $this->constructionService->completeJob($job);
                }
                $completed++;
            } catch (\Exception $e) {
                $errors[] = [
                    'job_uuid' => $job->uuid,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'checked' => $checked,
            'completed' => $completed,
            'errors' => $errors,
        ];
    }
}

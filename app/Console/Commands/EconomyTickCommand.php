<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use App\Models\TradingHub;
use App\Services\Contracts\ContractGenerationService;
use App\Services\Economy\ConstructionTickService;
use App\Services\Economy\HubCommodityStatsService;
use App\Services\Economy\MiningTickService;
use App\Services\Economy\ShockDecayTickService;
use Illuminate\Console\Command;

class EconomyTickCommand extends Command
{
    protected $signature = 'economy:tick
                            {--galaxy= : Limit to one galaxy UUID}
                            {--dry-run : Preview without writing}';

    protected $description = 'Process mining extraction, construction jobs, shock decay, contract generation for this tick, then refresh stats cache';

    public function __construct(
        private readonly MiningTickService $miningService,
        private readonly ConstructionTickService $constructionService,
        private readonly ShockDecayTickService $shockService,
        private readonly ContractGenerationService $contractGenerationService,
        private readonly HubCommodityStatsService $statsService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $galaxy = null;

        // Resolve optional galaxy UUID
        if ($galaxyUuid = $this->option('galaxy')) {
            $galaxy = Galaxy::where('uuid', $galaxyUuid)->first();

            if (!$galaxy) {
                $this->error("Galaxy with UUID '{$galaxyUuid}' not found");
                return Command::FAILURE;
            }
        }

        // Run tick phases
        $miningResults = $this->miningService->processTick($galaxy, $dryRun);
        $constructionResults = $this->constructionService->processTick($galaxy, $dryRun);
        $shockResults = $this->shockService->processTick($galaxy, $dryRun);

        // Generate contracts (only if not dry-run)
        $contractResults = ['generated' => 0];
        if (!$dryRun) {
            $contractResults = $this->generateContracts($galaxy);
        }

        // Refresh stats cache (only if not dry-run)
        $statsResults = null;
        if (!$dryRun) {
            if ($galaxy) {
                $statsResults = $this->statsService->recomputeGalaxyStats($galaxy);
            } else {
                $statsResults = $this->statsService->recomputeAllStats();
            }
        }

        // Display results
        $this->displayResults($miningResults, $constructionResults, $shockResults, $contractResults, $statsResults, $dryRun, $galaxy);

        return Command::SUCCESS;
    }

    private function generateContracts(?Galaxy $galaxy): array
    {
        $generated = 0;
        $errors = [];

        try {
            // Get all trading hubs to generate contracts for
            $hubsQuery = TradingHub::query();

            if ($galaxy) {
                // Only hubs in this galaxy
                $hubsQuery->whereHas('pointOfInterest', function ($q) use ($galaxy) {
                    $q->where('galaxy_id', $galaxy->id);
                });
            }

            $hubs = $hubsQuery->get();

            foreach ($hubs as $hub) {
                try {
                    $newContracts = $this->contractGenerationService->generateContractsForHub($hub, 2);
                    $generated += count($newContracts);
                } catch (\Exception $e) {
                    $errors[] = [
                        'hub_id' => $hub->id,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        } catch (\Exception $e) {
            $errors[] = ['message' => $e->getMessage()];
        }

        return [
            'generated' => $generated,
            'errors' => $errors,
        ];
    }

    private function displayResults(
        array $miningResults,
        array $constructionResults,
        array $shockResults,
        array $contractResults,
        ?array $statsResults,
        bool $dryRun,
        ?Galaxy $galaxy
    ): void {
        $border = '═══════════════════════════════════════════════════════════';

        $this->newLine();
        $this->line("<fg=cyan>{$border}</>");
        $this->line("<fg=cyan>╔══ Economy Tick Results ══════════════════════════════╗</>");
        $this->line("<fg=cyan>{$border}</>");

        if ($dryRun) {
            $this->line("<fg=yellow>[DRY RUN - No database changes]</>");
        }

        if ($galaxy) {
            $this->line("<fg=gray>Galaxy: {$galaxy->name} ({$galaxy->uuid})</>");
        }

        // Mining results
        $this->newLine();
        $this->line("<fg=green>Mining Extraction:</>");
        $this->line("  Deposits processed: <fg=cyan>{$miningResults['processed']}</>");
        $this->line("  Total extracted: <fg=cyan>" . number_format($miningResults['total_extracted'], 2) . " units</>");
        $this->line("  Newly depleted: <fg=yellow>{$miningResults['newly_depleted']}</>");

        if (!empty($miningResults['errors'])) {
            $this->line("  <fg=red>Errors: " . count($miningResults['errors']) . "</>");
            foreach ($miningResults['errors'] as $error) {
                $this->line("    - {$error['message']}");
            }
        }

        // Construction jobs results
        $this->newLine();
        $this->line("<fg=green>Construction Jobs:</>");
        $this->line("  Checked: <fg=cyan>{$constructionResults['checked']}</>");
        $this->line("  Completed: <fg=cyan>{$constructionResults['completed']}</>");

        if (!empty($constructionResults['errors'])) {
            $this->line("  <fg=red>Errors: " . count($constructionResults['errors']) . "</>");
            foreach ($constructionResults['errors'] as $error) {
                $this->line("    - Job {$error['job_uuid']}: {$error['message']}");
            }
        }

        // Shock decay results
        $this->newLine();
        $this->line("<fg=green>Shock Decay:</>");
        $this->line("  Shocks checked: <fg=cyan>{$shockResults['checked']}</>");
        $this->line("  Deactivated: <fg=yellow>{$shockResults['deactivated']}</>");

        if (!empty($shockResults['errors'])) {
            $this->line("  <fg=red>Errors: " . count($shockResults['errors']) . "</>");
            foreach ($shockResults['errors'] as $error) {
                $this->line("    - {$error['message']}");
            }
        }

        // Contract generation results
        $this->newLine();
        $this->line("<fg=green>Contract Generation:</>");
        $this->line("  Contracts generated: <fg=cyan>{$contractResults['generated']}</>");

        if (!empty($contractResults['errors'])) {
            $this->line("  <fg=red>Errors: " . count($contractResults['errors']) . "</>");
            foreach ($contractResults['errors'] as $error) {
                $this->line("    - {$error['message']}");
            }
        }

        // Stats refresh results
        if ($statsResults) {
            $this->newLine();
            $this->line("<fg=green>Stats Cache Refresh:</>");
            $this->line("  Computed: <fg=cyan>{$statsResults['computed']}</>");
            if (isset($statsResults['galaxies'])) {
                $this->line("  Galaxies processed: <fg=cyan>{$statsResults['galaxies']}</>");
            }

            if (!empty($statsResults['errors'])) {
                $this->line("  <fg=red>Errors: " . count($statsResults['errors']) . "</>");
            }
        }

        // Summary
        $this->newLine();
        $this->line("<fg=cyan>{$border}</>");
        $totalErrors = count($miningResults['errors'] ?? []) + count($constructionResults['errors'] ?? []) + count($shockResults['errors'] ?? []) + count($contractResults['errors'] ?? []);
        if ($totalErrors === 0) {
            $this->line("<fg=green>✓ Tick processed successfully</>");
        } else {
            $this->line("<fg=yellow>⚠ Tick completed with {$totalErrors} error(s)</>");
        }
        $this->line("<fg=cyan>{$border}</>");
        $this->newLine();
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\Galaxy\GalaxySizeTier;
use App\Services\GalaxyGeneration\GalaxyGenerationOrchestrator;
use Illuminate\Console\Command;

class BenchmarkGalaxyGeneration extends Command
{
    protected $signature = 'galaxy:benchmark
                            {tier=small : Size tier (small, medium, large)}
                            {--iterations=1 : Number of iterations}
                            {--json : Output as JSON}';

    protected $description = 'Benchmark the galaxy generation system';

    public function handle(): int
    {
        $tierName = $this->argument('tier');
        $iterations = (int) $this->option('iterations');

        $tier = GalaxySizeTier::tryFrom($tierName);
        if (! $tier) {
            $this->error("Invalid tier: {$tierName}. Valid values: small, medium, large");

            return 1;
        }

        $this->info("Benchmarking galaxy generation ({$tier->value})...");
        $this->newLine();

        $orchestrator = new GalaxyGenerationOrchestrator;
        $allResults = [];

        for ($i = 1; $i <= $iterations; $i++) {
            if ($iterations > 1) {
                $this->info("--- Iteration {$i}/{$iterations} ---");
            }

            $result = $orchestrator->generate($tier, [
                'game_mode' => 'multiplayer',
                'skip_mirror' => true,
                'skip_precursors' => true,
            ]);

            $allResults[] = $result;

            if ($this->option('json')) {
                continue;
            }

            $this->displayResult($result);
        }

        if ($this->option('json')) {
            $this->line(json_encode($allResults, JSON_PRETTY_PRINT));

            return 0;
        }

        if ($iterations > 1) {
            $this->displayAggregateStats($allResults);
        }

        return 0;
    }

    private function displayResult(array $result): void
    {
        $success = $result['success'] ? '<fg=green>SUCCESS</>' : '<fg=red>FAILED</>';
        $this->line("Status: {$success}");

        if (! $result['success']) {
            $this->error("Error: {$result['error']}");

            return;
        }

        $this->newLine();
        $this->info('Configuration:');
        $this->table(
            ['Parameter', 'Value'],
            [
                ['Tier', $result['config']['tier']],
                ['Game Mode', $result['config']['game_mode']],
                ['Dimensions', "{$result['config']['dimensions']['width']} x {$result['config']['dimensions']['height']}"],
                ['Core Stars', $result['config']['star_counts']['core']],
                ['Outer Stars', $result['config']['star_counts']['outer']],
                ['Total Stars', $result['config']['star_counts']['total']],
            ]
        );

        $this->newLine();
        $this->info('Generator Performance:');

        $rows = [];
        foreach ($result['metrics']['generators'] as $name => $generator) {
            $metrics = $generator['metrics'] ?? [];
            $rows[] = [
                $name,
                number_format($metrics['elapsed_ms'] ?? 0, 2).'ms',
                $generator['success'] ? '<fg=green>OK</>' : '<fg=red>FAIL</>',
                $this->formatCounts($metrics['counts'] ?? []),
            ];
        }

        $this->table(['Generator', 'Time', 'Status', 'Counts'], $rows);

        $this->newLine();
        $this->info('Total Execution Time: '.number_format($result['metrics']['total_elapsed_ms'], 2).'ms');
        $this->info('Total Execution Time: '.number_format($result['metrics']['total_elapsed_seconds'], 3).'s');

        if (isset($result['statistics'])) {
            $this->newLine();
            $this->info('Galaxy Statistics:');
            $this->table(
                ['Metric', 'Value'],
                collect($result['statistics'])->map(fn ($v, $k) => [$k, $v])->values()->toArray()
            );
        }

        $this->newLine();
    }

    private function formatCounts(array $counts): string
    {
        $parts = [];
        foreach ($counts as $key => $value) {
            $parts[] = "{$key}: {$value}";
        }

        return implode(', ', $parts);
    }

    private function displayAggregateStats(array $results): void
    {
        $this->newLine();
        $this->info('=== Aggregate Statistics ===');

        $times = array_column(array_column($results, 'metrics'), 'total_elapsed_ms');
        $successCount = count(array_filter(array_column($results, 'success')));

        $this->table(
            ['Metric', 'Value'],
            [
                ['Iterations', count($results)],
                ['Successful', $successCount],
                ['Failed', count($results) - $successCount],
                ['Min Time', number_format(min($times), 2).'ms'],
                ['Max Time', number_format(max($times), 2).'ms'],
                ['Avg Time', number_format(array_sum($times) / count($times), 2).'ms'],
                ['Total Time', number_format(array_sum($times), 2).'ms'],
            ]
        );
    }
}

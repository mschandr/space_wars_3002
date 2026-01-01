<?php

namespace App\Console\Commands;

use App\Services\ColonyCycleService;
use Illuminate\Console\Command;

class ProcessColonyCycles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'colony:process-cycles
                          {--dry-run : Run without making changes}
                          {--verbose : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all colony cycles (resource consumption, income generation, alerts)';

    private ColonyCycleService $colonyCycleService;

    public function __construct(ColonyCycleService $colonyCycleService)
    {
        parent::__construct();
        $this->colonyCycleService = $colonyCycleService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🌍 Processing Colony Cycles...');
        $this->newLine();

        $startTime = microtime(true);

        if ($this->option('dry-run')) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be saved');
            $this->newLine();
        }

        // Process all colonies
        $this->line('📊 Processing all colonies...');
        $colonyStats = $this->colonyCycleService->processAllColonies();

        $this->displayStats('Colony Processing', $colonyStats);

        // Process building construction
        $this->line('🏗️  Processing building construction...');
        $constructionStats = $this->colonyCycleService->processConstruction();

        $this->displayStats('Construction', $constructionStats);

        // Process ship production
        $this->line('🚀 Processing ship production...');
        $shipStats = $this->colonyCycleService->processShipProduction();

        $this->displayStats('Ship Production', $shipStats);

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        $this->newLine();
        $this->info("✅ Colony cycle processing completed in {$duration} seconds");

        // Summary
        $this->newLine();
        $this->line('═══════════════════════════════════════════════════');
        $this->line('                  CYCLE SUMMARY                    ');
        $this->line('═══════════════════════════════════════════════════');
        $this->line("Colonies Processed:    {$colonyStats['colonies_processed']}");
        $this->line("Credits Generated:     " . number_format($colonyStats['credits_generated']));
        $this->line("Alerts Sent:           {$colonyStats['alerts_sent']}");
        $this->line("Gates Shut Down:       {$colonyStats['gates_shutdown']}");
        $this->line("Buildings Completed:   {$constructionStats['buildings_completed']}");
        $this->line("Ships Completed:       {$shipStats['ships_completed']}");
        $this->line('═══════════════════════════════════════════════════');

        // Resource consumption breakdown
        if ($this->option('verbose')) {
            $this->newLine();
            $this->line('Resource Consumption:');
            $this->line("  Quantium: {$colonyStats['resources_consumed']['quantium']}");
            $this->line("  Food:     {$colonyStats['resources_consumed']['food']}");
            $this->line("  Minerals: {$colonyStats['resources_consumed']['minerals']}");
            $this->line("  Credits:  {$colonyStats['resources_consumed']['credits']}");
        }

        return Command::SUCCESS;
    }

    /**
     * Display statistics in a formatted way
     */
    private function displayStats(string $title, array $stats): void
    {
        if ($this->option('verbose')) {
            $this->newLine();
            $this->line("  {$title} Stats:");
            foreach ($stats as $key => $value) {
                if (is_array($value)) {
                    $this->line("    {$key}:");
                    foreach ($value as $subKey => $subValue) {
                        $this->line("      {$subKey}: {$subValue}");
                    }
                } else {
                    $this->line("    {$key}: {$value}");
                }
            }
        }
    }
}

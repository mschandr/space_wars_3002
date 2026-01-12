<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use App\Services\InhabitedSystemGenerator;
use Illuminate\Console\Command;

class GalaxyDesignateInhabitedCommand extends Command
{
    protected $signature = 'galaxy:designate-inhabited
                            {galaxy : Galaxy ID}
                            {--percentage=0.15 : Percentage of systems to mark as inhabited (0.0-1.0)}
                            {--min-spacing= : Minimum distance between inhabited systems (defaults to config)}';

    protected $description = 'Designate a percentage of star systems as inhabited with minimum spacing';

    public function handle(InhabitedSystemGenerator $generator): int
    {
        $galaxyId = $this->argument('galaxy');
        $percentage = (float) $this->option('percentage');
        $minSpacing = $this->option('min-spacing') ? (float) $this->option('min-spacing') : null;

        // Validate percentage
        if ($percentage < 0 || $percentage > 1) {
            $this->error('Percentage must be between 0.0 and 1.0');

            return Command::FAILURE;
        }

        // Find galaxy
        $galaxy = Galaxy::find($galaxyId);
        if (! $galaxy) {
            $this->error("Galaxy not found: {$galaxyId}");

            return Command::FAILURE;
        }

        $this->info("Designating inhabited systems for galaxy: {$galaxy->name}");
        $this->info('Target percentage: '.($percentage * 100).'%');
        if ($minSpacing) {
            $this->info("Minimum spacing: {$minSpacing} units");
        }
        $this->newLine();

        // Get stats before designation
        $beforeStats = $generator->getDistributionStats($galaxy);
        $this->info('Current state:');
        $this->line("  Total stars: {$beforeStats['total_stars']}");
        $this->line("  Already inhabited: {$beforeStats['inhabited_stars']}");
        $this->newLine();

        // Designate inhabited systems
        $this->info('Designating inhabited systems...');
        $inhabited = $generator->designateInhabitedSystems($galaxy, $percentage, $minSpacing);

        // Get stats after designation
        $afterStats = $generator->getDistributionStats($galaxy);

        // Display results
        $this->newLine();
        $this->info('✅ Inhabited system designation complete!');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Stars', $afterStats['total_stars']],
                ['Inhabited Systems', $afterStats['inhabited_stars']],
                ['Uninhabited Systems', $afterStats['uninhabited_stars']],
                ['Percentage Inhabited', $afterStats['percentage_inhabited'].'%'],
                ['Avg Distance Between Inhabited', round($afterStats['avg_distance_between_inhabited'], 2).' units'],
                ['Systems Marked This Run', $inhabited->count()],
            ]
        );

        $this->newLine();
        $this->info("Galaxy '{$galaxy->name}' now has {$afterStats['inhabited_stars']} inhabited systems.");
        $this->newLine();

        return Command::SUCCESS;
    }
}

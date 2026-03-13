<?php

namespace App\Console\Commands;

use App\Enums\Galaxy\GalaxySizeTier;
use App\Models\Galaxy;
use App\Services\GalaxyGenerationV2Service;
use Illuminate\Console\Command;

class GalaxyCreateV2Command extends Command
{
    protected $signature = 'galaxy:create-v2
                            {--name= : Galaxy name (optional, auto-generated if not provided)}
                            {--tier=large : Size tier (small, medium, large, massive) - default: large}
                            {--force : Skip confirmation}';

    protected $description = 'Create a new galaxy using V2 generation (discrete phases)';

    public function handle(GalaxyGenerationV2Service $service): int
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('  Galaxy Generation V2 - Phase 1: Create Galaxy Structure');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        // Check admin authorization for tier selection
        $tierOption = $this->option('tier');
        if ($tierOption && $tierOption !== 'large') {
            // Only admins can specify custom sizes
            if (!auth()->check() || !auth()->user()?->is_admin) {
                $this->error('Only administrators can specify custom galaxy sizes.');
                $this->line('Default size is "large". Contact an admin to create other sizes.');
                return self::FAILURE;
            }
        }

        // Get options
        $tierName = $tierOption ?? 'large';
        $name = $this->option('name') ?? 'Galaxy-' . now()->format('YmdHis');
        $force = $this->option('force');

        // Validate tier
        try {
            $tier = GalaxySizeTier::from($tierName);
        } catch (\ValueError $e) {
            $this->error("Invalid tier: {$tierName}");
            $this->line('Valid tiers: small, medium, large, massive');
            return self::FAILURE;
        }

        // Show summary
        $this->line("Galaxy Name: <fg=cyan>{$name}</>");
        $this->line("Size Tier: <fg=cyan>{$tier->value}</> ({$tier->getWidth()}×{$tier->getHeight()})");
        $this->line("Core Stars: <fg=cyan>{$tier->getCoreStars()}</>");
        $this->line("Outer Stars: <fg=cyan>{$tier->getOuterStars()}</>");
        $this->newLine();

        if (!$force && !$this->confirm('Create this galaxy?')) {
            $this->info('Cancelled.');
            return self::FAILURE;
        }

        $this->newLine();

        try {
            // Phase 1: Create empty galaxy
            $this->info('Phase 1: Creating galaxy structure...');
            $startTime = microtime(true);

            $galaxy = $service->createGalaxyOnly($tier, $name);

            $elapsed = round((microtime(true) - $startTime) * 1000);
            $this->line("  ✓ Galaxy created in {$elapsed}ms");
            $this->line("  UUID: <fg=green>{$galaxy->id}</>");
            $this->line("  Dimensions: {$galaxy->width}×{$galaxy->height}");

            $this->newLine();
            $this->info('═══════════════════════════════════════════════════════════════');
            $this->info('Next steps:');
            $this->line("  php artisan galaxy:populate-v2 {$galaxy->id} --step=stars");
            $this->line("  php artisan galaxy:populate-v2 {$galaxy->id} --step=gates");
            $this->line("  php artisan galaxy:populate-v2 {$galaxy->id} --step=all");
            $this->info('═══════════════════════════════════════════════════════════════');
            $this->newLine();

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error creating galaxy: {$e->getMessage()}");
            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use App\Models\GalaxyVendorProfile;
use Illuminate\Console\Command;

/**
 * Mark galaxy vendor profiles as pending for dialogue generation.
 *
 * Usage:
 *   php artisan vendor:dialogue-queue                    # queue pending + failed
 *   php artisan vendor:dialogue-queue --galaxy=<uuid>   # scope to one galaxy
 *   php artisan vendor:dialogue-queue --all             # include complete vendors
 *   php artisan vendor:dialogue-queue --force           # include generating vendors
 */
class VendorDialogueQueueCommand extends Command
{
    protected $signature = 'vendor:dialogue-queue
                            {--galaxy= : Only queue vendors in this galaxy UUID}
                            {--all     : Re-queue all vendors, including those with status=complete}
                            {--force   : Also re-queue vendors currently status=generating}';

    protected $description = 'Mark galaxy vendor profiles as pending for dialogue generation';

    public function handle(): int
    {
        $query = GalaxyVendorProfile::query();

        // Scope to a specific galaxy if provided
        if ($galaxyUuid = $this->option('galaxy')) {
            $galaxy = Galaxy::findByUuid($galaxyUuid);

            if (! $galaxy) {
                $this->error("Galaxy not found: {$galaxyUuid}");
                return Command::FAILURE;
            }

            $query->where('galaxy_id', $galaxy->id);
            $this->line("Scoped to galaxy: {$galaxy->name} ({$galaxyUuid})");
        }

        // Determine which statuses to re-queue
        if ($this->option('all')) {
            $statuses = ['pending', 'failed', 'complete'];
            $this->line('Mode: re-queue all (pending + failed + complete)');
        } elseif ($this->option('force')) {
            $statuses = ['pending', 'failed', 'generating'];
            $this->line('Mode: force re-queue (pending + failed + generating)');
        } else {
            $statuses = ['pending', 'failed'];
            $this->line('Mode: default (pending + failed only)');
        }

        $count = (clone $query)
            ->whereIn('dialogue_generation_status', $statuses)
            ->count();

        if ($count === 0) {
            $this->info('No vendors to queue.');
            return Command::SUCCESS;
        }

        $query->whereIn('dialogue_generation_status', $statuses)
            ->update([
                'dialogue_generation_status' => 'pending',
                'dialogue_generated_at'      => null,
            ]);

        $this->info("Queued {$count} vendor(s) for dialogue generation.");
        $this->line('The Go dialogue generator will pick these up on its next poll.');

        return Command::SUCCESS;
    }
}

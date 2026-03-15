<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use App\Models\GalaxyVendorProfile;
use Illuminate\Console\Command;

/**
 * Show dialogue generation status for vendors, with coverage against
 * the expected generation matrix defined in config/vendor_dialogue.php.
 *
 * Usage:
 *   php artisan vendor:dialogue-status              # all galaxies
 *   php artisan vendor:dialogue-status <galaxyUuid> # one galaxy
 */
class VendorDialogueStatusCommand extends Command
{
    protected $signature = 'vendor:dialogue-status {galaxyUuid? : Optional galaxy UUID to filter by}';

    protected $description = 'Show dialogue generation status and coverage for vendors';

    public function handle(): int
    {
        $query = GalaxyVendorProfile::with([
            'galaxy:id,uuid,name',
            'vendorProfile:id,name,archetype',
        ]);

        if ($galaxyUuid = $this->argument('galaxyUuid')) {
            $galaxy = Galaxy::findByUuid($galaxyUuid);

            if (! $galaxy) {
                $this->error("Galaxy not found: {$galaxyUuid}");
                return Command::FAILURE;
            }

            $query->where('galaxy_id', $galaxy->id);
        }

        $vendors = $query->get();

        if ($vendors->isEmpty()) {
            $this->info('No vendors found.');
            return Command::SUCCESS;
        }

        // Summary: grouped by status
        $grouped = $vendors->groupBy('dialogue_generation_status');
        $this->info('=== Status Summary ===');
        $this->table(
            ['Status', 'Count'],
            $grouped->map(fn ($g, $s) => [$s, $g->count()])->values()->toArray()
        );

        // Coverage: lines stored vs matrix size
        $matrixSize = count(config('vendor_dialogue.generation_matrix', []));

        $this->newLine();
        $this->info('=== Vendor Detail ===');
        $this->table(
            ['Galaxy', 'Vendor', 'Service', 'Status', 'Ver', "Lines/{$matrixSize}", 'Generated At'],
            $vendors->map(function ($v) use ($matrixSize) {
                $lineCount = $v->dialogueLines()->count();
                $coverage  = "{$lineCount}/{$matrixSize}";

                return [
                    $v->galaxy->name ?? substr($v->galaxy->uuid, 0, 8),
                    $v->vendorProfile->name ?? substr($v->uuid, 0, 8),
                    $v->service_type,
                    $v->dialogue_generation_status,
                    $v->dialogue_generation_version,
                    $coverage,
                    $v->dialogue_generated_at?->toDateTimeString() ?? '—',
                ];
            })->toArray()
        );

        return Command::SUCCESS;
    }
}

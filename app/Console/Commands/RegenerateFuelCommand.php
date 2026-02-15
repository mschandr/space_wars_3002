<?php

namespace App\Console\Commands;

use App\Models\PlayerShip;
use App\Services\FuelRegenerationService;
use Illuminate\Console\Command;

class RegenerateFuelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fuel:regenerate
                          {--player= : Specific player UUID to regenerate fuel for}
                          {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate fuel for all player ships based on time elapsed';

    private FuelRegenerationService $fuelService;

    public function __construct(FuelRegenerationService $fuelService)
    {
        parent::__construct();
        $this->fuelService = $fuelService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('⛽ Regenerating Fuel for Player Ships...');
        $this->newLine();

        $startTime = microtime(true);

        if ($this->option('dry-run')) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be saved');
            $this->newLine();
        }

        // Get ships to process
        $query = PlayerShip::query()
            ->where('current_fuel', '<', \DB::raw('max_fuel'))
            ->where('is_active', true);

        if ($playerUuid = $this->option('player')) {
            $query->whereHas('player', fn ($q) => $q->where('uuid', $playerUuid));
        }

        $ships = $query->get();

        if ($ships->isEmpty()) {
            $this->info('No ships need fuel regeneration');

            return Command::SUCCESS;
        }

        $this->line("Processing {$ships->count()} ship(s)...");
        $this->newLine();

        $totalRegenerated = 0;
        $shipsProcessed = 0;

        foreach ($ships as $ship) {
            if ($this->option('dry-run')) {
                // Just calculate, don't save
                $lastUpdate = \Carbon\Carbon::parse($ship->fuel_last_updated_at);
                $hoursElapsed = $lastUpdate->diffInMinutes(now()) / 60.0;
                $efficiency = 1 + ($ship->warp_drive - 1) * 0.3;
                $regenRate = 10 * $efficiency;
                $fuelToRegen = $regenRate * $hoursElapsed;

                $result = [
                    'regenerated' => min($fuelToRegen, $ship->max_fuel - $ship->current_fuel),
                    'new_fuel' => min($ship->max_fuel, $ship->current_fuel + $fuelToRegen),
                    'old_fuel' => $ship->current_fuel,
                    'hours_elapsed' => $hoursElapsed,
                    'regen_rate' => $regenRate,
                ];
            } else {
                $result = $this->fuelService->regenerateFuel($ship);
            }

            $totalRegenerated += $result['regenerated'];
            $shipsProcessed++;

            if ($this->getOutput()->isVerbose()) {
                $this->line("  {$ship->player->call_sign} - {$ship->name}:");
                $this->line("    Fuel: {$result['old_fuel']} → {$result['new_fuel']} (+{$result['regenerated']})");
                $this->line("    Rate: {$result['regen_rate']}/hr ({$result['hours_elapsed']}h elapsed)");
                $this->newLine();
            }
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        $this->newLine();
        $this->info("✅ Fuel regeneration completed in {$duration} seconds");

        // Summary
        $this->newLine();
        $this->line('═══════════════════════════════════════════════════');
        $this->line('              FUEL REGENERATION SUMMARY            ');
        $this->line('═══════════════════════════════════════════════════');
        $this->line("Ships Processed:      {$shipsProcessed}");
        $this->line('Total Fuel Regenerated: '.round($totalRegenerated, 2));
        $this->line('═══════════════════════════════════════════════════');

        return Command::SUCCESS;
    }
}

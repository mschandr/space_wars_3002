<?php

namespace App\Services;

use App\Models\Colony;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ColonyCycleService
{
    private NotificationService $notificationService;

    private MiningService $miningService;

    public function __construct(
        NotificationService $notificationService,
        MiningService $miningService
    ) {
        $this->notificationService = $notificationService;
        $this->miningService = $miningService;
    }

    /**
     * Process all colonies for one cycle
     */
    public function processAllColonies(): array
    {
        $stats = [
            'colonies_processed' => 0,
            'credits_generated' => 0,
            'resources_consumed' => [
                'quantium' => 0,
                'food' => 0,
                'minerals' => 0,
                'credits' => 0,
            ],
            'alerts_sent' => 0,
            'gates_shutdown' => 0,
        ];

        $colonies = Colony::with(['player', 'buildings', 'poi'])->get();

        foreach ($colonies as $colony) {
            try {
                $result = $this->processColonyCycle($colony);
                $stats['colonies_processed']++;
                $stats['credits_generated'] += $result['credits_generated'];
                $stats['resources_consumed']['quantium'] += $result['resources_consumed']['quantium'];
                $stats['resources_consumed']['food'] += $result['resources_consumed']['food'];
                $stats['resources_consumed']['minerals'] += $result['resources_consumed']['minerals'];
                $stats['resources_consumed']['credits'] += $result['resources_consumed']['credits'];
                $stats['alerts_sent'] += $result['alerts_sent'];
                $stats['gates_shutdown'] += $result['gates_shutdown'];
            } catch (\Exception $e) {
                Log::error("Error processing colony {$colony->id}: ".$e->getMessage());
            }
        }

        return $stats;
    }

    /**
     * Process a single colony for one cycle
     */
    public function processColonyCycle(Colony $colony): array
    {
        $log = [];
        $creditsGenerated = 0;
        $resourcesConsumed = ['quantium' => 0, 'food' => 0, 'minerals' => 0, 'credits' => 0];
        $alertsSent = 0;
        $gatesShutdown = 0;

        DB::transaction(function () use ($colony, &$log, &$creditsGenerated, &$resourcesConsumed, &$alertsSent, &$gatesShutdown) {
            // 1. Process population growth
            $colony->processGrowth();
            $log[] = "Population: {$colony->population}";

            // 2. Process each building
            $buildings = $colony->buildings()->where('status', 'operational')->get();

            foreach ($buildings as $building) {
                $buildingLog = $building->processCycle($colony);
                $log = array_merge($log, $buildingLog);

                // TODO: (Null Safety) Use null coalescing operator for building properties that may not exist:
                // e.g., ($building->quantium_per_cycle ?? 0) to avoid potential null addition errors
                $resourcesConsumed['quantium'] += $building->quantium_per_cycle;
                $resourcesConsumed['food'] += $building->food_per_cycle;
                $resourcesConsumed['minerals'] += $building->minerals_per_cycle;
                $resourcesConsumed['credits'] += $building->credits_per_cycle;

                // Track credits generated
                $creditsGenerated += $building->credits_generated_per_cycle;

                // Check if this was a warp gate that shut down
                if ($building->building_type === 'warp_gate' && $building->status === 'damaged') {
                    $this->notificationService->alertGateShutdown($colony);
                    $alertsSent++;
                    $gatesShutdown++;
                }
            }

            // 3. Apply credits generated to player
            if ($creditsGenerated > 0) {
                $colony->player->addCredits($creditsGenerated);
                $log[] = "Credits generated: +{$creditsGenerated}";
            }

            // 4. Update colony production totals
            $colony->calculateProduction();

            // 5. Check for low resource alerts
            if ($colony->quantium_storage < 24) {
                $this->notificationService->alertLowQuantium($colony);
                $alertsSent++;
            }

            if ($colony->food_storage < 100) {
                $this->notificationService->alertLowFood($colony);
                $alertsSent++;
            }

            if ($colony->mineral_storage < 100) {
                $this->notificationService->alertLowMinerals($colony);
                $alertsSent++;
            }

            // 6. Award XP for colony management
            if ($colony->population > 0 || $colony->mineral_production > 0) {
                $xpEarned = $this->calculateColonyXP($colony);
                if ($xpEarned > 0) {
                    $oldLevel = $colony->player->level;
                    $colony->player->addExperience($xpEarned);
                    $newLevel = $colony->player->level;

                    if ($newLevel > $oldLevel) {
                        $log[] = "🎉 Player leveled up to {$newLevel}!";
                    }
                }
            }

            $colony->save();
        });

        return [
            'log' => $log,
            'credits_generated' => $creditsGenerated,
            'resources_consumed' => $resourcesConsumed,
            'alerts_sent' => $alertsSent,
            'gates_shutdown' => $gatesShutdown,
        ];
    }

    /**
     * Calculate XP earned from colony activities
     */
    private function calculateColonyXP(Colony $colony): int
    {
        $xp = 0;

        // XP for population growth (1 XP per 100 population)
        $xp += (int) ($colony->population / 100);

        // XP for resource production (1 XP per 50 minerals)
        $xp += (int) ($colony->mineral_production / 50);

        // XP for food production (1 XP per 100 food)
        $xp += (int) ($colony->food_production / 100);

        // XP for income generation (1 XP per 100 credits)
        $xp += (int) ($colony->credits_per_cycle / 100);

        // Bonus XP for high development level
        $xp += $colony->development_level * 5;

        return max(1, $xp);
    }

    /**
     * Process construction progress for all colonies
     */
    public function processConstruction(): array
    {
        $stats = ['buildings_completed' => 0];

        $constructingBuildings = \App\Models\ColonyBuilding::where('status', 'constructing')
            ->with('colony.player')
            ->get();

        foreach ($constructingBuildings as $building) {
            // Advance construction by 10% per cycle (10 cycles = complete)
            $building->advanceConstruction(10);

            if ($building->status === 'operational') {
                $stats['buildings_completed']++;

                // Notify player
                $this->notificationService->alertBuildingComplete(
                    $building->colony,
                    $building->getTypeDisplay()
                );
            }
        }

        return $stats;
    }

    /**
     * Process ship production for all colonies
     */
    public function processShipProduction(): array
    {
        $stats = ['ships_completed' => 0];

        $buildingProduction = \App\Models\ColonyShipProduction::where('status', 'building')
            ->with(['colony', 'ship', 'player'])
            ->get();

        foreach ($buildingProduction as $production) {
            // Calculate progress based on production time
            $progressPerCycle = (int) (100 / $production->production_time_cycles);
            $production->advanceProduction($progressPerCycle);

            if ($production->status === 'completed') {
                $stats['ships_completed']++;
            }
        }

        return $stats;
    }

    /**
     * Get cycle summary for a player
     */
    public function getPlayerCycleSummary(Player $player): array
    {
        $colonies = $player->colonies()->with('buildings')->get();

        $summary = [
            'total_colonies' => $colonies->count(),
            'total_population' => $colonies->sum('population'),
            'total_credits_per_cycle' => 0,
            'total_quantium_consumption' => 0,
            'active_gates' => 0,
            'critical_alerts' => 0,
        ];

        foreach ($colonies as $colony) {
            $summary['total_credits_per_cycle'] += $colony->credits_per_cycle;

            $gates = $colony->buildings()
                ->where('building_type', 'warp_gate')
                ->where('status', 'operational')
                ->count();

            $summary['active_gates'] += $gates;

            $quantiumConsumption = $colony->buildings()
                ->where('status', 'operational')
                ->sum('quantium_per_cycle');

            $summary['total_quantium_consumption'] += $quantiumConsumption;

            // Check for critical situations
            if ($colony->quantium_storage < 12) {
                $summary['critical_alerts']++;
            }
        }

        return $summary;
    }
}

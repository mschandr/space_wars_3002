<?php

namespace Database\Seeders;

use App\Models\CrewAssignment;
use App\Models\CrewMember;
use App\Models\Galaxy;
use App\Models\TradingHub;
use Illuminate\Database\Seeder;

/**
 * Seed crew assignments for each galaxy
 *
 * Randomly assigns crew members from the permanent pool to active trading hubs
 * in each galaxy. Crew members are available for hire at their assigned hubs.
 */
class CrewAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding crew assignments...');

        $galaxies = Galaxy::all();
        $allCrew = CrewMember::all();

        if ($allCrew->isEmpty()) {
            $this->command->warn('No crew members found. Skipping crew assignment seeding.');
            return;
        }

        $totalAssignments = 0;

        foreach ($galaxies as $galaxy) {
            $this->command->info("Processing {$galaxy->name}...");

            // Get active trading hubs in this galaxy
            $activeHubs = TradingHub::join('points_of_interest', 'trading_hubs.poi_id', '=', 'points_of_interest.id')
                ->where('points_of_interest.galaxy_id', $galaxy->id)
                ->where('points_of_interest.is_inhabited', true)
                ->select('trading_hubs.*')
                ->get();

            if ($activeHubs->isEmpty()) {
                $this->command->warn("  No active trading hubs found. Skipping.");
                continue;
            }

            $this->command->info("  Found {$activeHubs->count()} active trading hubs");

            // Assign crew to hubs (each crew can be assigned to only one hub per galaxy)
            $hubCount = $activeHubs->count();
            $crewPerHub = max(1, intdiv($allCrew->count(), $hubCount));

            $assignedCrew = collect();

            foreach ($activeHubs as $hub) {
                // Get random crew not yet assigned in this galaxy
                $availableCrew = $allCrew->diff($assignedCrew);

                if ($availableCrew->isEmpty()) {
                    break;
                }

                // Assign 1-3 crew members to this hub
                $crewToAssign = $availableCrew->random(min(
                    random_int(1, 3),
                    $availableCrew->count()
                ));

                foreach ($crewToAssign as $crew) {
                    // Check if already assigned in this galaxy
                    if (!CrewAssignment::where('galaxy_id', $galaxy->id)
                        ->where('crew_member_id', $crew->id)
                        ->exists()) {
                        try {
                            CrewAssignment::create([
                                'uuid' => \Illuminate\Support\Str::uuid(),
                                'galaxy_id' => $galaxy->id,
                                'crew_member_id' => $crew->id,
                                'trading_hub_id' => $hub->id,
                            ]);

                            $assignedCrew->push($crew);
                            $totalAssignments++;
                        } catch (\Exception $e) {
                            $this->command->error("    Error assigning crew to {$hub->name}: {$e->getMessage()}");
                        }
                    }
                }
            }

            $this->command->info("  Assigned {$assignedCrew->count()} crew members to hubs");
        }

        $this->command->info("✓ Total crew assignments created: {$totalAssignments}");
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\Exploration\KnowledgeLevel;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerPrecursorRumor;
use App\Models\PointOfInterest;
use App\Services\PlayerKnowledgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class KnowledgeHydrateCommand extends Command
{
    protected $signature = 'knowledge:hydrate
                            {galaxy? : Galaxy ID or name (all galaxies if omitted)}
                            {--player= : Specific player ID to hydrate}';

    protected $description = 'Hydrate player knowledge from existing star charts, scans, lane knowledge, and rumors';

    public function handle(): int
    {
        $knowledgeService = app(PlayerKnowledgeService::class);

        // Determine scope
        $query = Player::query();

        $galaxyIdentifier = $this->argument('galaxy');
        if ($galaxyIdentifier) {
            $galaxy = is_numeric($galaxyIdentifier)
                ? Galaxy::find($galaxyIdentifier)
                : Galaxy::where('name', 'like', "%{$galaxyIdentifier}%")->first();

            if (! $galaxy) {
                $this->error("Galaxy not found: {$galaxyIdentifier}");

                return Command::FAILURE;
            }

            $query->where('galaxy_id', $galaxy->id);
            $this->info("Hydrating knowledge for galaxy: {$galaxy->name}");
        }

        if ($playerId = $this->option('player')) {
            $query->where('id', $playerId);
        }

        $players = $query->with(['currentLocation', 'activeShip'])->get();

        if ($players->isEmpty()) {
            $this->info('No players found to hydrate.');

            return Command::SUCCESS;
        }

        $this->info("Processing {$players->count()} player(s)...");
        $this->newLine();

        $totalKnowledge = 0;

        foreach ($players as $player) {
            $playerKnowledge = 0;
            $this->info("Player: {$player->call_sign} (ID: {$player->id})");

            // 1. Mark current location as VISITED
            if ($player->currentLocation) {
                $knowledgeService->markVisited($player, $player->currentLocation);
                $playerKnowledge++;
            }

            // 2. Convert player_star_charts → BASIC/SURVEYED knowledge
            $chartPois = DB::table('player_star_charts')
                ->where('player_id', $player->id)
                ->pluck('revealed_poi_id');

            if ($chartPois->isNotEmpty()) {
                $chartSystems = PointOfInterest::whereIn('id', $chartPois)->get();
                $bulkEntries = [];

                foreach ($chartSystems as $poi) {
                    $level = $poi->is_inhabited ? KnowledgeLevel::SURVEYED : KnowledgeLevel::BASIC;
                    $bulkEntries[] = [
                        'poi_id' => $poi->id,
                        'level' => $level,
                        'source' => 'chart',
                    ];
                }

                if (! empty($bulkEntries)) {
                    $knowledgeService->grantBulkKnowledge($player, $bulkEntries);
                    $playerKnowledge += count($bulkEntries);
                }
            }

            // 3. Convert pilot_lane_knowledge endpoints → DETECTED knowledge
            $laneEndpoints = DB::table('pilot_lane_knowledge')
                ->where('player_id', $player->id)
                ->join('warp_gates', 'pilot_lane_knowledge.warp_gate_id', '=', 'warp_gates.id')
                ->selectRaw('DISTINCT destination_poi_id')
                ->pluck('destination_poi_id');

            $sourceEndpoints = DB::table('pilot_lane_knowledge')
                ->where('player_id', $player->id)
                ->join('warp_gates', 'pilot_lane_knowledge.warp_gate_id', '=', 'warp_gates.id')
                ->selectRaw('DISTINCT source_poi_id')
                ->pluck('source_poi_id');

            $allEndpoints = $laneEndpoints->merge($sourceEndpoints)->unique();
            if ($allEndpoints->isNotEmpty()) {
                $bulkEntries = [];
                foreach ($allEndpoints as $poiId) {
                    $bulkEntries[] = [
                        'poi_id' => $poiId,
                        'level' => KnowledgeLevel::DETECTED,
                        'source' => 'warp_lane',
                    ];
                }

                $knowledgeService->grantBulkKnowledge($player, $bulkEntries);
                $playerKnowledge += count($bulkEntries);
            }

            // 4. Convert system_scans → VISITED (if scanned, player was likely there)
            $scannedPois = DB::table('system_scans')
                ->where('player_id', $player->id)
                ->pluck('poi_id');

            if ($scannedPois->isNotEmpty()) {
                $bulkEntries = [];
                foreach ($scannedPois as $poiId) {
                    $bulkEntries[] = [
                        'poi_id' => $poiId,
                        'level' => KnowledgeLevel::VISITED,
                        'source' => 'scan',
                    ];
                }

                $knowledgeService->grantBulkKnowledge($player, $bulkEntries);
                $playerKnowledge += count($bulkEntries);
            }

            // 5. Convert precursor rumors → DETECTED knowledge for nearby systems
            $rumors = PlayerPrecursorRumor::where('player_id', $player->id)->get();
            foreach ($rumors as $rumor) {
                $nearestPoi = PointOfInterest::where('galaxy_id', $player->galaxy_id)
                    ->stars()
                    ->where('is_hidden', false)
                    ->whereRaw(
                        'SQRT(POW(CAST(x AS SIGNED) - ?, 2) + POW(CAST(y AS SIGNED) - ?, 2)) <= 10',
                        [$rumor->rumor_x, $rumor->rumor_y]
                    )
                    ->first();

                if ($nearestPoi) {
                    $knowledgeService->applyRumorKnowledge($player, $nearestPoi);
                    $playerKnowledge++;
                }
            }

            $this->line("  → {$playerKnowledge} knowledge records processed");
            $totalKnowledge += $playerKnowledge;
        }

        $this->newLine();
        $this->info("Hydration complete! Total knowledge records: {$totalKnowledge}");

        return Command::SUCCESS;
    }
}

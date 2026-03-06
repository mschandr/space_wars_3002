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

        // Resolve galaxy filter if provided
        $galaxyIdentifier = $this->argument('galaxy');
        if ($galaxyIdentifier) {
            $galaxy = $this->resolveGalaxy($galaxyIdentifier);
            if (! $galaxy) {
                return Command::FAILURE;
            }
        }

        // Build player query
        $query = $this->buildPlayerQuery($galaxyIdentifier ?? null);
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

            // Process each knowledge source
            $playerKnowledge += $this->processCurrentLocation($knowledgeService, $player);
            $playerKnowledge += $this->processStarCharts($knowledgeService, $player);
            $playerKnowledge += $this->processLaneKnowledge($knowledgeService, $player);
            $playerKnowledge += $this->processSystemScans($knowledgeService, $player);
            $playerKnowledge += $this->processPrecursorRumors($knowledgeService, $player);

            $this->line("  → {$playerKnowledge} knowledge records processed");
            $totalKnowledge += $playerKnowledge;
        }

        $this->displayResults($totalKnowledge);
        return Command::SUCCESS;
    }

    /**
     * Resolve galaxy from identifier (ID or name).
     */
    private function resolveGalaxy(string $galaxyIdentifier): ?Galaxy
    {
        $galaxy = is_numeric($galaxyIdentifier)
            ? Galaxy::find($galaxyIdentifier)
            : Galaxy::where('name', 'like', "%{$galaxyIdentifier}%")->first();

        if (! $galaxy) {
            $this->error("Galaxy not found: {$galaxyIdentifier}");
        } else {
            $this->info("Hydrating knowledge for galaxy: {$galaxy->name}");
        }

        return $galaxy;
    }

    /**
     * Build player query with optional filters.
     */
    private function buildPlayerQuery(?string $galaxyIdentifier): \Illuminate\Database\Eloquent\Builder
    {
        $query = Player::query();

        if ($galaxyIdentifier) {
            $galaxy = is_numeric($galaxyIdentifier)
                ? Galaxy::find($galaxyIdentifier)
                : Galaxy::where('name', 'like', "%{$galaxyIdentifier}%")->first();

            if ($galaxy) {
                $query->where('galaxy_id', $galaxy->id);
            }
        }

        if ($playerId = $this->option('player')) {
            $query->where('id', $playerId);
        }

        return $query;
    }

    /**
     * Process current location as VISITED knowledge.
     */
    private function processCurrentLocation(PlayerKnowledgeService $knowledgeService, Player $player): int
    {
        if (! $player->currentLocation) {
            return 0;
        }

        $knowledgeService->markVisited($player, $player->currentLocation);
        return 1;
    }

    /**
     * Process star charts as BASIC/SURVEYED knowledge.
     */
    private function processStarCharts(PlayerKnowledgeService $knowledgeService, Player $player): int
    {
        $chartPois = DB::table('player_star_charts')
            ->where('player_id', $player->id)
            ->pluck('revealed_poi_id');

        if ($chartPois->isEmpty()) {
            return 0;
        }

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

        if (empty($bulkEntries)) {
            return 0;
        }

        $knowledgeService->grantBulkKnowledge($player, $bulkEntries);
        return count($bulkEntries);
    }

    /**
     * Process warp lane endpoints as DETECTED knowledge.
     */
    private function processLaneKnowledge(PlayerKnowledgeService $knowledgeService, Player $player): int
    {
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
        if ($allEndpoints->isEmpty()) {
            return 0;
        }

        $bulkEntries = [];
        foreach ($allEndpoints as $poiId) {
            $bulkEntries[] = [
                'poi_id' => $poiId,
                'level' => KnowledgeLevel::DETECTED,
                'source' => 'warp_lane',
            ];
        }

        $knowledgeService->grantBulkKnowledge($player, $bulkEntries);
        return count($bulkEntries);
    }

    /**
     * Process system scans as VISITED knowledge.
     */
    private function processSystemScans(PlayerKnowledgeService $knowledgeService, Player $player): int
    {
        $scannedPois = DB::table('system_scans')
            ->where('player_id', $player->id)
            ->pluck('poi_id');

        if ($scannedPois->isEmpty()) {
            return 0;
        }

        $bulkEntries = [];
        foreach ($scannedPois as $poiId) {
            $bulkEntries[] = [
                'poi_id' => $poiId,
                'level' => KnowledgeLevel::VISITED,
                'source' => 'scan',
            ];
        }

        $knowledgeService->grantBulkKnowledge($player, $bulkEntries);
        return count($bulkEntries);
    }

    /**
     * Process precursor rumors as DETECTED knowledge.
     */
    private function processPrecursorRumors(PlayerKnowledgeService $knowledgeService, Player $player): int
    {
        $rumors = PlayerPrecursorRumor::where('player_id', $player->id)->get();
        $count = 0;

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
                $count++;
            }
        }

        return $count;
    }

    /**
     * Display final results.
     */
    private function displayResults(int $totalKnowledge): void
    {
        $this->newLine();
        $this->info("Hydration complete! Total knowledge records: {$totalKnowledge}");
    }
}

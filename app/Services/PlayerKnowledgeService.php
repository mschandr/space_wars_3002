<?php

namespace App\Services;

use App\Enums\Exploration\KnowledgeLevel;
use App\Models\Player;
use App\Models\PlayerSystemKnowledge;
use App\Models\PointOfInterest;
use App\Models\WarpGate;
use App\Support\SensorRangeCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Central service for the player knowledge / fog-of-war system.
 *
 * Manages what each player knows about the galaxy. Knowledge is accumulated
 * through travel, star charts, sensors, rumors, and scans. Once gained,
 * knowledge never drops below DETECTED (level 1).
 */
class PlayerKnowledgeService
{
    /**
     * Grant or upgrade knowledge of a system for a player.
     *
     * Never downgrades — if the player already has higher knowledge, this is a no-op.
     */
    public function grantKnowledge(
        Player $player,
        PointOfInterest $poi,
        KnowledgeLevel $level,
        string $source,
        ?PointOfInterest $sourcePoi = null,
        ?array $metadata = null,
        ?array $servicesData = null,
        bool $hasPirateWarning = false,
        ?array $pirateWarningData = null,
    ): PlayerSystemKnowledge {
        $existing = PlayerSystemKnowledge::where('player_id', $player->id)
            ->where('poi_id', $poi->id)
            ->first();

        if ($existing) {
            // Never downgrade
            if ($level->value <= $existing->knowledge_level) {
                // Still update pirate warning if new info
                if ($hasPirateWarning && ! $existing->has_pirate_warning) {
                    $existing->update([
                        'has_pirate_warning' => true,
                        'pirate_warning_data' => $pirateWarningData,
                    ]);
                }

                return $existing;
            }

            // Upgrade existing record
            $existing->update([
                'knowledge_level' => $level->value,
                'source' => $source,
                'source_poi_id' => $sourcePoi?->id,
                'acquired_at' => now(),
                'has_pirate_warning' => $hasPirateWarning || $existing->has_pirate_warning,
                'pirate_warning_data' => $pirateWarningData ?? $existing->pirate_warning_data,
                'services_data' => $servicesData ?? $existing->services_data,
                'metadata' => $metadata ?? $existing->metadata,
            ]);

            return $existing->fresh();
        }

        return PlayerSystemKnowledge::create([
            'player_id' => $player->id,
            'poi_id' => $poi->id,
            'knowledge_level' => $level->value,
            'source' => $source,
            'source_poi_id' => $sourcePoi?->id,
            'acquired_at' => now(),
            'has_pirate_warning' => $hasPirateWarning,
            'pirate_warning_data' => $pirateWarningData,
            'services_data' => $servicesData,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Batch upsert knowledge records.
     *
     * Each entry: ['poi_id' => int, 'level' => KnowledgeLevel, 'source' => string]
     *
     * @return int Number of records inserted or updated
     */
    public function grantBulkKnowledge(Player $player, array $entries, ?PointOfInterest $sourcePoi = null): int
    {
        if (empty($entries)) {
            return 0;
        }

        $now = now();

        // Load existing knowledge for this player in one query
        $existingByPoi = PlayerSystemKnowledge::where('player_id', $player->id)
            ->whereIn('poi_id', array_column($entries, 'poi_id'))
            ->pluck('knowledge_level', 'poi_id')
            ->toArray();

        $inserts = [];
        $count = 0;

        foreach ($entries as $entry) {
            $poiId = $entry['poi_id'];
            $newLevel = $entry['level'] instanceof KnowledgeLevel ? $entry['level']->value : $entry['level'];
            $existingLevel = $existingByPoi[$poiId] ?? 0;

            // Never downgrade
            if ($newLevel <= $existingLevel) {
                continue;
            }

            if (isset($existingByPoi[$poiId])) {
                // Update existing record
                PlayerSystemKnowledge::where('player_id', $player->id)
                    ->where('poi_id', $poiId)
                    ->update([
                        'knowledge_level' => $newLevel,
                        'source' => $entry['source'],
                        'source_poi_id' => $sourcePoi?->id,
                        'acquired_at' => $now,
                        'updated_at' => $now,
                    ]);
            } else {
                $inserts[] = [
                    'player_id' => $player->id,
                    'poi_id' => $poiId,
                    'knowledge_level' => $newLevel,
                    'source' => $entry['source'],
                    'source_poi_id' => $sourcePoi?->id,
                    'acquired_at' => $now,
                    'has_pirate_warning' => $entry['has_pirate_warning'] ?? false,
                    'pirate_warning_data' => isset($entry['pirate_warning_data']) ? json_encode($entry['pirate_warning_data']) : null,
                    'services_data' => isset($entry['services_data']) ? json_encode($entry['services_data']) : null,
                    'metadata' => isset($entry['metadata']) ? json_encode($entry['metadata']) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $existingByPoi[$poiId] = $newLevel;
            }

            $count++;
        }

        // Batch insert new records
        if (! empty($inserts)) {
            foreach (array_chunk($inserts, 100) as $chunk) {
                DB::table('player_system_knowledge')->insert($chunk);
            }
        }

        return $count;
    }

    /**
     * Mark a system as VISITED and discover surrounding systems.
     *
     * Called when a player arrives at a new system.
     * - Sets destination to VISITED (4)
     * - Discovers outgoing warp lane endpoints as DETECTED (1)
     * - Inhabited destination: DETECTED for systems 2 hops out
     * - Uninhabited destination: DETECTED for systems 1 hop out
     */
    public function markVisited(Player $player, PointOfInterest $destination): void
    {
        // 1. Mark the destination as VISITED
        $this->grantKnowledge(
            $player,
            $destination,
            KnowledgeLevel::VISITED,
            'visit',
            $destination,
            servicesData: $this->buildServicesData($destination),
        );

        // 2. Discover outgoing warp lane endpoints
        $outgoingGates = WarpGate::where('source_poi_id', $destination->id)
            ->where('is_hidden', false)
            ->with('destinationPoi')
            ->get();

        $bulkEntries = [];
        $firstHopPois = collect();

        foreach ($outgoingGates as $gate) {
            $destPoi = $gate->destinationPoi;
            if (! $destPoi) {
                continue;
            }

            $firstHopPois->push($destPoi);
            $bulkEntries[] = [
                'poi_id' => $destPoi->id,
                'level' => KnowledgeLevel::DETECTED,
                'source' => 'warp_lane',
            ];
        }

        // 3. Region-dependent extended reach
        $maxHops = $destination->is_inhabited
            ? config('game_config.knowledge.chart_hops_inhabited', 2)
            : config('game_config.knowledge.chart_hops_uninhabited', 1);

        // If max hops >= 2, also discover systems reachable from first-hop systems
        if ($maxHops >= 2 && $firstHopPois->isNotEmpty()) {
            $firstHopIds = $firstHopPois->pluck('id')->toArray();
            $visitedIds = array_merge([$destination->id], $firstHopIds);

            $secondHopGates = WarpGate::whereIn('source_poi_id', $firstHopIds)
                ->where('is_hidden', false)
                ->whereNotIn('destination_poi_id', $visitedIds)
                ->with('destinationPoi')
                ->get();

            foreach ($secondHopGates as $gate) {
                $destPoi = $gate->destinationPoi;
                if (! $destPoi) {
                    continue;
                }

                $bulkEntries[] = [
                    'poi_id' => $destPoi->id,
                    'level' => KnowledgeLevel::DETECTED,
                    'source' => 'warp_lane',
                ];
            }
        }

        if (! empty($bulkEntries)) {
            $this->grantBulkKnowledge($player, $bulkEntries, $destination);
        }
    }

    /**
     * Apply knowledge from a purchased star chart.
     *
     * - Inhabited target → SURVEYED (3) with services_data
     * - Uninhabited target → BASIC (2)
     *
     * @return int Count of systems knowledge was granted for
     */
    public function applyChartKnowledge(Player $player, PointOfInterest $purchaseLocation, ?int $sectorId = null): int
    {
        $chartService = app(StarChartService::class);
        $coverage = $chartService->getChartCoverage($purchaseLocation);

        $bulkEntries = [];

        foreach ($coverage as $poi) {
            $level = $poi->is_inhabited ? KnowledgeLevel::SURVEYED : KnowledgeLevel::BASIC;

            $bulkEntries[] = [
                'poi_id' => $poi->id,
                'level' => $level,
                'source' => 'chart',
                'services_data' => $poi->is_inhabited ? $this->buildServicesData($poi) : null,
            ];
        }

        if (! empty($bulkEntries)) {
            $this->grantBulkKnowledge($player, $bulkEntries, $purchaseLocation);
        }

        return count($bulkEntries);
    }

    /**
     * Apply knowledge from a precursor rumor or NPC intel.
     */
    public function applyRumorKnowledge(
        Player $player,
        PointOfInterest $targetPoi,
        ?array $pirateData = null,
    ): void {
        $this->grantKnowledge(
            $player,
            $targetPoi,
            KnowledgeLevel::DETECTED,
            'rumor',
            hasPirateWarning: $pirateData !== null,
            pirateWarningData: $pirateData,
        );
    }

    /**
     * Get real-time sensor detections from the player's current position.
     *
     * NOT stored — computed per request.
     *
     * @return Collection Collection of [poi_id, x, y, distance, type] for uncharted systems
     */
    public function getSensorDetections(Player $player, PointOfInterest $currentLocation, int $sensorLevel): Collection
    {
        $sensorRange = SensorRangeCalculator::getRangeLY($sensorLevel);
        $x = $currentLocation->x;
        $y = $currentLocation->y;

        return PointOfInterest::where('galaxy_id', $currentLocation->galaxy_id)
            ->where('id', '!=', $currentLocation->id)
            ->stars()
            ->where('is_hidden', false)
            ->where('x', '>=', $x - $sensorRange)
            ->where('x', '<=', $x + $sensorRange)
            ->where('y', '>=', $y - $sensorRange)
            ->where('y', '<=', $y + $sensorRange)
            ->whereRaw(
                'SQRT(POW(CAST(x AS SIGNED) - ?, 2) + POW(CAST(y AS SIGNED) - ?, 2)) <= ?',
                [$x, $y, $sensorRange]
            )
            ->selectRaw(
                'id as poi_id, x, y, type, SQRT(POW(CAST(x AS SIGNED) - ?, 2) + POW(CAST(y AS SIGNED) - ?, 2)) as distance',
                [$x, $y]
            )
            ->orderBy('distance')
            ->limit(50)
            ->get();
    }

    /**
     * Get the full knowledge map for a player.
     *
     * Merges: stored knowledge + core-sector baseline + real-time sensor detections.
     * Returns highest level per system.
     */
    public function getKnowledgeMap(Player $player, ?int $sectorId = null): array
    {
        // 1. Load all stored knowledge
        $query = PlayerSystemKnowledge::forPlayer($player->id)->knownSystems();
        if ($sectorId) {
            $query->whereHas('pointOfInterest', fn ($q) => $q->where('sector_id', $sectorId));
        }
        $storedKnowledge = $query->get()->keyBy('poi_id');

        // 2. Sector baseline: charted systems in player's current sector
        $baselineKnowledge = collect();
        if (config('game_config.knowledge.core_baseline_enabled', true)) {
            $currentLocation = $player->currentLocation;
            if ($currentLocation && $currentLocation->sector_id) {
                $baselineLevel = config('game_config.knowledge.core_baseline_level', 1);
                $chartedInSector = PointOfInterest::where('sector_id', $currentLocation->sector_id)
                    ->where('galaxy_id', $currentLocation->galaxy_id)
                    ->stars()
                    ->where('is_charted', true)
                    ->where('is_hidden', false)
                    ->pluck('id');

                foreach ($chartedInSector as $poiId) {
                    $baselineKnowledge[$poiId] = $baselineLevel;
                }
            }
        }

        // 3. Real-time sensor detections
        $sensorDetections = collect();
        $currentLocation = $player->currentLocation;
        $sensorLevel = $player->activeShip?->sensors ?? 1;
        if ($currentLocation) {
            $sensorDetections = $this->getSensorDetections($player, $currentLocation, $sensorLevel);
        }

        // 4. Merge all sources — take highest level per system
        $mergedMap = [];

        // Start with stored knowledge
        foreach ($storedKnowledge as $poiId => $record) {
            $effectiveLevel = $this->getEffectiveKnowledgeLevel($record);
            $mergedMap[$poiId] = [
                'knowledge_level' => $effectiveLevel,
                'source' => $record->source,
                'freshness' => $this->calculateFreshness($record),
                'has_pirate_warning' => $record->has_pirate_warning,
                'pirate_warning_data' => $record->pirate_warning_data,
                'services_data' => $record->services_data,
                'record' => $record,
            ];
        }

        // Merge baseline (only upgrades, never downgrades)
        foreach ($baselineKnowledge as $poiId => $baseLevel) {
            if (! isset($mergedMap[$poiId]) || $mergedMap[$poiId]['knowledge_level'] < $baseLevel) {
                $mergedMap[$poiId] = [
                    'knowledge_level' => $baseLevel,
                    'source' => 'baseline',
                    'freshness' => 1.0,
                    'has_pirate_warning' => $mergedMap[$poiId]['has_pirate_warning'] ?? false,
                    'pirate_warning_data' => $mergedMap[$poiId]['pirate_warning_data'] ?? null,
                    'services_data' => $mergedMap[$poiId]['services_data'] ?? null,
                    'record' => $mergedMap[$poiId]['record'] ?? null,
                ];
            }
        }

        // Merge sensor detections (DETECTED level, always fresh)
        foreach ($sensorDetections as $detection) {
            $poiId = $detection->poi_id;
            if (! isset($mergedMap[$poiId]) || $mergedMap[$poiId]['knowledge_level'] < KnowledgeLevel::DETECTED->value) {
                $mergedMap[$poiId] = [
                    'knowledge_level' => KnowledgeLevel::DETECTED->value,
                    'source' => 'sensor',
                    'freshness' => 1.0,
                    'has_pirate_warning' => $mergedMap[$poiId]['has_pirate_warning'] ?? false,
                    'pirate_warning_data' => $mergedMap[$poiId]['pirate_warning_data'] ?? null,
                    'services_data' => $mergedMap[$poiId]['services_data'] ?? null,
                    'record' => $mergedMap[$poiId]['record'] ?? null,
                ];
            }
        }

        return $mergedMap;
    }

    /**
     * Calculate freshness score for a knowledge record.
     *
     * Permanent sources always return 1.0.
     * Chart/rumor sources decay over time, minimum 0.1.
     */
    public function calculateFreshness(PlayerSystemKnowledge $knowledge): float
    {
        if ($knowledge->isPermanent()) {
            return 1.0;
        }

        $maxHours = config('game_config.knowledge.freshness_max_hours', 168);
        $hoursElapsed = $knowledge->acquired_at->diffInHours(now());

        return max(0.1, 1.0 - ($hoursElapsed / $maxHours));
    }

    /**
     * Get the effective knowledge level after applying decay.
     *
     * VISITED and permanent sources never decay.
     * Chart/rumor sources degrade: SURVEYED → BASIC → DETECTED over time.
     * Never drops below DETECTED (1).
     */
    public function getEffectiveKnowledgeLevel(PlayerSystemKnowledge $knowledge): int
    {
        $baseLevel = $knowledge->knowledge_level;
        $floor = config('game_config.knowledge.decay_floor_level', 1);

        // Permanent sources never decay
        if ($knowledge->isPermanent()) {
            return $baseLevel;
        }

        $freshness = $this->calculateFreshness($knowledge);

        if ($freshness >= 0.7) {
            return $baseLevel;                   // Full detail
        }
        if ($freshness >= 0.3) {
            return max($floor, $baseLevel - 1);  // One level down
        }

        return $floor;                           // Floor (DETECTED)
    }

    /**
     * Build services_data for a POI (for SURVEYED level).
     */
    private function buildServicesData(PointOfInterest $poi): ?array
    {
        if (! $poi->is_inhabited) {
            return null;
        }

        $tradingHub = $poi->tradingHub;

        return [
            'trading_hub' => $tradingHub !== null && $tradingHub->is_active,
            'shipyard' => $tradingHub?->has_shipyard ?? false,
            'salvage_yard' => $tradingHub?->has_salvage_yard ?? false,
            'cartographer' => $tradingHub?->stellarCartographer !== null,
        ];
    }
}

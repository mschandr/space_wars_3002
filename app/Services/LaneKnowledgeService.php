<?php

namespace App\Services;

use App\Models\PilotLaneKnowledge;
use App\Models\Player;
use App\Models\WarpGate;
use Illuminate\Support\Collection;

/**
 * Service for managing player knowledge of warp lanes.
 *
 * Implements fog of war for warp gates - players only see lanes they've
 * discovered through travel, scanning, star charts, or intel.
 */
class LaneKnowledgeService
{
    /**
     * Discover a warp lane for a player.
     *
     * @param  Player  $player  The player discovering the lane
     * @param  WarpGate  $gate  The warp gate being discovered
     * @param  string  $method  Discovery method (travel, scan, chart, intel, spawn)
     * @return PilotLaneKnowledge The knowledge record (new or existing)
     */
    public function discoverLane(Player $player, WarpGate $gate, string $method = 'travel'): PilotLaneKnowledge
    {
        $existing = PilotLaneKnowledge::where('player_id', $player->id)
            ->where('warp_gate_id', $gate->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return PilotLaneKnowledge::create([
            'player_id' => $player->id,
            'warp_gate_id' => $gate->id,
            'discovered_at' => now(),
            'discovery_method' => $method,
            'pirate_risk_known' => false,
        ]);
    }

    /**
     * Discover a warp lane bidirectionally.
     *
     * When traveling through a gate, you learn about it from both directions.
     * This finds the reverse gate (if it exists) and marks both as known.
     *
     * @param  Player  $player  The player discovering the lane
     * @param  WarpGate  $gate  The warp gate traveled through
     * @param  string  $method  Discovery method
     * @return array Array of PilotLaneKnowledge records created
     */
    public function discoverLaneBidirectional(Player $player, WarpGate $gate, string $method = 'travel'): array
    {
        $discovered = [];

        // Discover the forward direction
        $discovered[] = $this->discoverLane($player, $gate, $method);

        // Find and discover the reverse direction if it exists
        $reverseGate = WarpGate::where('source_poi_id', $gate->destination_poi_id)
            ->where('destination_poi_id', $gate->source_poi_id)
            ->where('galaxy_id', $gate->galaxy_id)
            ->first();

        if ($reverseGate) {
            $discovered[] = $this->discoverLane($player, $reverseGate, $method);
        }

        return $discovered;
    }

    /**
     * Reveal pirate risk information for a lane.
     *
     * @param  Player  $player  The player
     * @param  WarpGate  $gate  The warp gate
     */
    public function revealPirateRisk(Player $player, WarpGate $gate): void
    {
        $knowledge = PilotLaneKnowledge::where('player_id', $player->id)
            ->where('warp_gate_id', $gate->id)
            ->first();

        if ($knowledge) {
            $knowledge->markPirateRiskKnown();
        }
    }

    /**
     * Get all known lanes for a player.
     *
     * @param  Player  $player  The player
     * @return Collection Collection of PilotLaneKnowledge with warpGate eager-loaded
     */
    public function getKnownLanes(Player $player): Collection
    {
        return $player->laneKnowledge()
            ->with(['warpGate.sourcePoi', 'warpGate.destinationPoi'])
            ->get();
    }

    /**
     * Get known lanes for a player in a specific galaxy.
     *
     * @param  Player  $player  The player
     * @param  int  $galaxyId  The galaxy ID
     * @return Collection Collection of PilotLaneKnowledge
     */
    public function getKnownLanesInGalaxy(Player $player, int $galaxyId): Collection
    {
        return $player->laneKnowledge()
            ->whereHas('warpGate', function ($query) use ($galaxyId) {
                $query->where('galaxy_id', $galaxyId);
            })
            ->with(['warpGate.sourcePoi', 'warpGate.destinationPoi'])
            ->get();
    }

    /**
     * Check if player knows about a specific warp gate.
     *
     * @param  Player  $player  The player
     * @param  WarpGate  $gate  The warp gate
     * @return bool True if the player knows about this gate
     */
    public function knowsLane(Player $player, WarpGate $gate): bool
    {
        return PilotLaneKnowledge::where('player_id', $player->id)
            ->where('warp_gate_id', $gate->id)
            ->exists();
    }

    /**
     * Check if player knows the pirate risk for a lane.
     *
     * @param  Player  $player  The player
     * @param  WarpGate  $gate  The warp gate
     * @param  int  $maxAgeMinutes  Maximum age of intel in minutes (default 60)
     * @return bool True if pirate risk is known and not stale
     */
    public function knowsPirateRisk(Player $player, WarpGate $gate, int $maxAgeMinutes = 60): bool
    {
        $knowledge = PilotLaneKnowledge::where('player_id', $player->id)
            ->where('warp_gate_id', $gate->id)
            ->first();

        if (! $knowledge || ! $knowledge->pirate_risk_known) {
            return false;
        }

        return ! $knowledge->isPirateRiskStale($maxAgeMinutes);
    }

    /**
     * Get all gates from a POI that the player knows about.
     *
     * @param  Player  $player  The player
     * @param  int  $poiId  The source POI ID
     * @return Collection Collection of WarpGate models
     */
    public function getKnownGatesFromPoi(Player $player, int $poiId): Collection
    {
        $knownGateIds = $player->laneKnowledge()->pluck('warp_gate_id');

        return WarpGate::where('source_poi_id', $poiId)
            ->whereIn('id', $knownGateIds)
            ->with(['destinationPoi', 'warpLanePirate'])
            ->get();
    }

    /**
     * Discover all outgoing gates from a POI.
     *
     * Used when a player spawns or buys a star chart.
     *
     * @param  Player  $player  The player
     * @param  int  $poiId  The POI ID
     * @param  string  $method  Discovery method
     * @return int Number of gates discovered
     */
    public function discoverOutgoingGates(Player $player, int $poiId, string $method = 'spawn'): int
    {
        $gates = WarpGate::where('source_poi_id', $poiId)
            ->where('is_hidden', false)
            ->get();

        $count = 0;
        foreach ($gates as $gate) {
            if (! $this->knowsLane($player, $gate)) {
                $this->discoverLane($player, $gate, $method);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Bulk check which gates a player knows.
     *
     * @param  Player  $player  The player
     * @param  array  $gateIds  Array of gate IDs to check
     * @return array<int, bool> Array of gate_id => known status
     */
    public function bulkKnowsLane(Player $player, array $gateIds): array
    {
        $knownIds = PilotLaneKnowledge::where('player_id', $player->id)
            ->whereIn('warp_gate_id', $gateIds)
            ->pluck('warp_gate_id')
            ->toArray();

        $result = [];
        foreach ($gateIds as $gateId) {
            $result[$gateId] = in_array($gateId, $knownIds);
        }

        return $result;
    }
}

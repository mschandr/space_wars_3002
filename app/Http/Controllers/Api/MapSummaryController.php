<?php

namespace App\Http\Controllers\Api;

use App\Models\Galaxy;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Map Summary Controller
 *
 * Provides lightweight map data for hover tooltips and quick rendering.
 * Only returns systems the player has discovered (star charts + scans).
 */
class MapSummaryController extends BaseApiController
{
    /**
     * Get lightweight map summaries for known systems.
     *
     * GET /api/galaxies/{uuid}/map-summaries
     *
     * Returns minimal data for each known system, optimized for map rendering:
     * - uuid, name, x, y, type, inhabited, has_trading, gate_count
     */
    public function index(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Authentication required', 'UNAUTHENTICATED', null, 401);
        }

        $galaxy = Galaxy::where('uuid', $uuid)->first();
        if (! $galaxy) {
            return $this->notFound('Galaxy not found');
        }

        // Get player in this galaxy
        $player = Player::where('user_id', $user->id)
            ->where('galaxy_id', $galaxy->id)
            ->first();

        if (! $player) {
            return $this->error(
                'You do not have a player in this galaxy',
                'NO_PLAYER_IN_GALAXY',
                ['galaxy_uuid' => $uuid],
                404
            );
        }

        // Get known system IDs from star charts
        $chartedPoiIds = $player->starCharts()->pluck('revealed_poi_id')->toArray();

        // Get scanned system IDs
        $scannedPoiIds = $player->systemScans()->pluck('poi_id')->toArray();

        // Also include current location
        $currentPoiId = $player->current_poi_id;

        // Merge all known system IDs
        $knownPoiIds = array_unique(array_merge(
            $chartedPoiIds,
            $scannedPoiIds,
            $currentPoiId ? [$currentPoiId] : []
        ));

        if (empty($knownPoiIds)) {
            return $this->success([
                'systems' => [],
                'total' => 0,
            ], 'No known systems');
        }

        // Get lightweight system data with gate counts
        $systems = DB::table('points_of_interest as poi')
            ->select([
                'poi.id',
                'poi.uuid',
                'poi.name',
                'poi.x',
                'poi.y',
                'poi.type',
                'poi.is_inhabited',
                DB::raw('(SELECT COUNT(*) FROM warp_gates WHERE source_poi_id = poi.id AND is_hidden = false) as gate_count'),
                DB::raw('EXISTS (SELECT 1 FROM trading_hubs WHERE poi_id = poi.id AND is_active = true) as has_trading'),
            ])
            ->where('poi.galaxy_id', $galaxy->id)
            ->whereIn('poi.id', $knownPoiIds)
            ->get()
            ->map(function ($system) use ($currentPoiId) {
                return [
                    'uuid' => $system->uuid,
                    'name' => $system->name,
                    'x' => (int) $system->x,
                    'y' => (int) $system->y,
                    'type' => $system->type,
                    'is_inhabited' => (bool) $system->is_inhabited,
                    'has_trading' => (bool) $system->has_trading,
                    'gate_count' => (int) $system->gate_count,
                    'is_current_location' => $system->id === $currentPoiId,
                ];
            });

        return $this->success([
            'systems' => $systems,
            'total' => $systems->count(),
            'player_location' => $player->currentPoi ? [
                'uuid' => $player->currentPoi->uuid,
                'x' => (int) $player->currentPoi->x,
                'y' => (int) $player->currentPoi->y,
            ] : null,
        ], 'Map summaries retrieved successfully');
    }
}

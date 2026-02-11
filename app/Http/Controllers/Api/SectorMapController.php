<?php

namespace App\Http\Controllers\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SectorMapController extends BaseApiController
{
    /**
     * Get lightweight sector-grid map for a galaxy.
     *
     * Returns aggregate stats per sector instead of individual stars,
     * reducing a 3000-star galaxy from 30k+ lines to ~400 sector entries.
     *
     * GET /api/galaxies/{uuid}/sector-map
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

        $player = Player::where('user_id', $user->id)
            ->where('galaxy_id', $galaxy->id)
            ->with('currentPoi:id,x,y,sector_id')
            ->first();

        if (! $player) {
            return $this->error(
                'You do not have a player in this galaxy',
                'NO_PLAYER_IN_GALAXY',
                ['galaxy_uuid' => $uuid],
                404
            );
        }

        $starType = PointOfInterestType::STAR->value;

        // Query 1: Sector stats with correlated subqueries (single round-trip)
        // Includes s.id for player count merge, excluded from final response
        $sectors = DB::table('sectors as s')
            ->select([
                's.id',
                's.uuid',
                's.name',
                's.grid_x',
                's.grid_y',
                's.x_min',
                's.x_max',
                's.y_min',
                's.y_max',
                's.danger_level',
                DB::raw("(SELECT COUNT(*) FROM points_of_interest WHERE sector_id = s.id AND type = {$starType}) as total_systems"),
                DB::raw("(SELECT COUNT(*) FROM points_of_interest WHERE sector_id = s.id AND type = {$starType} AND is_inhabited = 1) as inhabited_systems"),
                DB::raw('EXISTS(SELECT 1 FROM trading_hubs th JOIN points_of_interest p ON th.poi_id = p.id WHERE p.sector_id = s.id AND th.is_active = 1) as has_trading'),
            ])
            ->where('s.galaxy_id', $galaxy->id)
            ->orderBy('s.grid_y')
            ->orderBy('s.grid_x')
            ->get();

        // Query 2: Active player counts per sector
        $playerCounts = DB::table('players as pl')
            ->join('points_of_interest as poi', 'pl.current_poi_id', '=', 'poi.id')
            ->where('pl.galaxy_id', $galaxy->id)
            ->where('pl.status', 'active')
            ->whereNotNull('poi.sector_id')
            ->groupBy('poi.sector_id')
            ->pluck(DB::raw('COUNT(*)'), 'poi.sector_id');

        // Determine grid dimensions
        $maxGridX = $sectors->max('grid_x') ?? 0;
        $maxGridY = $sectors->max('grid_y') ?? 0;

        $playerCurrentPoi = $player->currentPoi;
        $playerSectorId = $playerCurrentPoi?->sector_id;

        // Build response, merging player counts via sector id
        $playerSectorUuid = null;
        $sectorData = $sectors->map(function ($sector) use ($playerCounts, $playerSectorId, &$playerSectorUuid) {
            if ($playerSectorId && (int) $sector->id === (int) $playerSectorId) {
                $playerSectorUuid = $sector->uuid;
            }

            return [
                'uuid' => $sector->uuid,
                'name' => $sector->name,
                'grid_x' => (int) $sector->grid_x,
                'grid_y' => (int) $sector->grid_y,
                'bounds' => [
                    'x_min' => (float) $sector->x_min,
                    'x_max' => (float) $sector->x_max,
                    'y_min' => (float) $sector->y_min,
                    'y_max' => (float) $sector->y_max,
                ],
                'danger_level' => (int) $sector->danger_level,
                'total_systems' => (int) $sector->total_systems,
                'inhabited_systems' => (int) $sector->inhabited_systems,
                'has_trading' => (bool) $sector->has_trading,
                'player_count' => (int) ($playerCounts[$sector->id] ?? 0),
            ];
        });

        return $this->success([
            'galaxy' => [
                'uuid' => $galaxy->uuid,
                'name' => $galaxy->name,
                'width' => $galaxy->width,
                'height' => $galaxy->height,
            ],
            'grid_size' => [
                'cols' => $maxGridX + 1,
                'rows' => $maxGridY + 1,
            ],
            'sectors' => $sectorData,
            'player_sector_uuid' => $playerSectorUuid,
            'player_location' => $playerCurrentPoi ? [
                'x' => (int) $playerCurrentPoi->x,
                'y' => (int) $playerCurrentPoi->y,
            ] : null,
        ], 'Sector map retrieved successfully');
    }
}

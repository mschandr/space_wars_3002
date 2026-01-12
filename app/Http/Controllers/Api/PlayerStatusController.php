<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles player status queries and statistics
 */
class PlayerStatusController extends BaseApiController
{
    /**
     * Get real-time player status
     *
     * GET /api/players/{uuid}/status
     */
    public function status(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with(['activeShip', 'currentLocation'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $ship = $player->activeShip;
        $location = $player->currentLocation;

        return $this->success([
            'player' => [
                'uuid' => $player->uuid,
                'call_sign' => $player->call_sign,
                'level' => $player->level,
                'credits' => (float) $player->credits,
                'experience' => $player->experience,
                'status' => $player->status,
            ],
            'location' => $location ? [
                'name' => $location->name,
                'type' => $location->type,
                'coordinates' => [
                    'x' => (float) $location->x,
                    'y' => (float) $location->y,
                ],
            ] : null,
            'ship' => $ship ? [
                'name' => $ship->name,
                'fuel' => $ship->current_fuel,
                'max_fuel' => $ship->max_fuel,
                'hull' => $ship->hull,
                'max_hull' => $ship->max_hull,
                'status' => $ship->status,
            ] : null,
        ]);
    }

    /**
     * Get player statistics
     *
     * GET /api/players/{uuid}/stats
     */
    public function stats(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with(['ships', 'plans', 'starCharts'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $nextLevelXp = pow($player->level, 2) * 100;
        $progressToNextLevel = ($player->experience % $nextLevelXp) / $nextLevelXp * 100;

        return $this->success([
            'player_info' => [
                'uuid' => $player->uuid,
                'call_sign' => $player->call_sign,
                'level' => $player->level,
                'experience' => $player->experience,
                'next_level_xp' => $nextLevelXp,
                'progress_to_next_level' => round($progressToNextLevel, 2),
            ],
            'economy' => [
                'credits' => (float) $player->credits,
                'total_ships_owned' => $player->ships->count(),
                'total_plans_owned' => $player->plans->count(),
            ],
            'exploration' => [
                'systems_charted' => $player->starCharts->count(),
            ],
            'mirror_universe' => [
                'is_in_mirror' => $player->isInMirrorUniverse(),
                'can_return' => $player->canReturnFromMirror(),
                'cooldown_remaining' => $player->getMirrorCooldownRemaining()?->diffForHumans(),
            ],
        ]);
    }
}

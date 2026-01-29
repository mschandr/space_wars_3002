<?php

namespace App\Http\Middleware;

use App\Models\Player;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdatePlayerLastAccess
{
    /**
     * Handle an incoming request.
     *
     * Updates the player's last_accessed_at timestamp when they interact with a galaxy.
     * Detects galaxy context from route parameters (player_uuid, galaxy_uuid) or request body.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only update on successful requests
        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $user = $request->user();
        if (! $user) {
            return $response;
        }

        // Try to find player context from route parameters
        $playerUuid = $request->route('player_uuid') ?? $request->route('uuid');
        $galaxyUuid = $request->route('galaxy_uuid');

        $player = null;

        if ($playerUuid) {
            $player = Player::where('uuid', $playerUuid)
                ->where('user_id', $user->id)
                ->first();
        } elseif ($galaxyUuid) {
            $player = Player::whereHas('galaxy', fn ($q) => $q->where('uuid', $galaxyUuid))
                ->where('user_id', $user->id)
                ->first();
        }

        if ($player) {
            $player->update(['last_accessed_at' => now()]);
        }

        return $response;
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight galaxy resource for lists and selection UIs.
 *
 * Returns minimal data optimized for fast loading:
 * - Basic identification (uuid, name)
 * - Size tier
 * - Player count and capacity
 * - Game mode
 */
class GalaxyDehydratedResource extends JsonResource
{
    private const DEFAULT_MAX_PLAYERS = 100;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $playerCount = $this->player_count ?? $this->active_player_count ?? 0;
        $maxPlayers = $this->max_players ?? self::DEFAULT_MAX_PLAYERS;

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'size' => $this->size_tier?->value ?? $this->inferSize($this->width),
            'players' => $playerCount,
            'max_players' => $maxPlayers,
            'slots_available' => max(0, $maxPlayers - $playerCount),
            'mode' => $this->game_mode ?? 'multiplayer',
            'status' => $this->status?->value ?? $this->status,
        ];
    }

    /**
     * Infer galaxy size from dimensions for legacy galaxies.
     */
    private function inferSize(int $width): string
    {
        return match (true) {
            $width <= 500 => 'small',
            $width <= 1500 => 'medium',
            $width <= 2500 => 'large',
            default => 'massive',
        };
    }
}

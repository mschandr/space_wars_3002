<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full galaxy resource for detail views.
 *
 * Returns comprehensive data including:
 * - Full identification and metadata
 * - Dimensions and bounds
 * - Statistics (players, systems, etc.)
 * - Related data when loaded
 */
class GalaxyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'status' => $this->status?->value ?? $this->status,
            'game_mode' => $this->game_mode ?? 'multiplayer',
            'size_tier' => $this->size_tier?->value ?? null,

            'dimensions' => [
                'width' => $this->width,
                'height' => $this->height,
            ],

            'core_bounds' => $this->when($this->core_bounds, $this->core_bounds),

            'statistics' => [
                'total_players' => $this->total_players ?? null,
                'active_players' => $this->active_player_count ?? null,
                'total_systems' => $this->total_systems ?? null,
                'sectors' => $this->whenLoaded('sectors', fn () => $this->sectors->count()),
                'warp_gates' => $this->whenLoaded('warpGates', fn () => $this->warpGates->count()),
                'trading_hubs' => $this->whenLoaded('tradingHubs', fn () => $this->tradingHubs->count()),
            ],

            'players' => $this->whenLoaded('players', fn () => $this->players->map(fn ($p) => [
                'uuid' => $p->uuid,
                'call_sign' => $p->call_sign,
                'level' => $p->level,
                'status' => $p->status,
            ])),

            'owner' => $this->when(
                $this->game_mode === 'single_player' && $this->relationLoaded('owner'),
                fn () => $this->owner ? [
                    'id' => $this->owner->id,
                    'name' => $this->owner->name,
                ] : null
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'generation_completed_at' => $this->generation_completed_at?->toIso8601String(),
        ];
    }
}

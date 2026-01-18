<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'dimensions' => [
                'width' => $this->width,
                'height' => $this->height,
            ],
            'configuration' => [
                'seed' => $this->seed,
                'point_count' => $this->config['points']['total'] ?? null,
                'generator' => $this->config['galaxy']['generator'] ?? null,
                'grid_size' => $this->config['sectors']['grid_size'] ?? null,
            ],
            'statistics' => [
                'total_systems' => $this->when(isset($this->total_systems), $this->total_systems),
                'total_players' => $this->when(isset($this->total_players), $this->total_players),
                'active_players' => $this->when(isset($this->active_player_count), $this->active_player_count),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

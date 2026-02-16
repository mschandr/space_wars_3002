<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrbitalStructureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'structure_type' => $this->structure_type->value,
            'structure_label' => $this->structure_type->label(),
            'name' => $this->name,
            'level' => $this->level,
            'status' => $this->status,
            'health' => $this->health,
            'max_health' => $this->max_health,
            'construction_progress' => $this->construction_progress,
            'construction_started_at' => $this->construction_started_at?->toIso8601String(),
            'construction_completed_at' => $this->construction_completed_at?->toIso8601String(),
            'attributes' => $this->attributes,
            'operating_costs' => [
                'credits_per_cycle' => $this->credits_per_cycle,
                'minerals_per_cycle' => $this->minerals_per_cycle,
            ],
            'poi' => [
                'uuid' => $this->whenLoaded('poi', fn () => $this->poi->uuid),
                'name' => $this->whenLoaded('poi', fn () => $this->poi->name),
            ],
            'player' => [
                'uuid' => $this->whenLoaded('player', fn () => $this->player->uuid),
                'call_sign' => $this->whenLoaded('player', fn () => $this->player->call_sign),
            ],
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

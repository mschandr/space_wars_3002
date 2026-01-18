<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ColonyBuildingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'building_type' => $this->building_type,
            'name' => $this->name,
            'level' => $this->level,
            'status' => $this->status,
            'effects' => $this->effects,
            'upkeep' => [
                'quantium_per_cycle' => $this->quantium_per_cycle,
                'food_per_cycle' => $this->food_per_cycle,
                'minerals_per_cycle' => $this->minerals_per_cycle,
                'credits_per_cycle' => $this->credits_per_cycle,
            ],
            'production' => [
                'food_production' => $this->effects['food_production'] ?? 0,
                'mineral_production' => $this->effects['mineral_production'] ?? 0,
                'credits_generation' => $this->credits_generated_per_cycle ?? 0,
            ],
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

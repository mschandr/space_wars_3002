<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ColonyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'status' => $this->status,
            'status_display' => $this->getStatusDisplay(),
            'population' => $this->population,
            'max_population' => $this->max_population,
            'population_growth_rate' => $this->population_growth_rate,
            'development_level' => $this->development_level,
            'habitability_rating' => $this->habitability_rating,
            'production' => [
                'food_production' => $this->food_production,
                'food_storage' => $this->food_storage,
                'mineral_production' => $this->mineral_production,
                'mineral_storage' => $this->mineral_storage,
                'quantium_storage' => $this->quantium_storage,
                'credits_per_cycle' => $this->credits_per_cycle,
            ],
            'location' => [
                'poi_uuid' => $this->poi->uuid,
                'poi_name' => $this->poi->name,
                'poi_type' => $this->poi->type,
                'planet_class' => $this->poi->planet_class,
                'coordinates' => [
                    'x' => $this->poi->x,
                    'y' => $this->poi->y,
                ],
            ],
            'buildings_count' => $this->whenLoaded('buildings', fn () => $this->buildings->count()),
            'max_buildings' => $this->development_level * 2,
            'has_shipyard' => $this->hasShipyard(),
            'established_at' => $this->established_at->toIso8601String(),
            'last_growth_at' => $this->last_growth_at?->toIso8601String(),
            'age_in_days' => $this->getAgeInDays(),
        ];
    }
}

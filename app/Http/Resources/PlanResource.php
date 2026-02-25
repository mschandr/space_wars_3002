<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
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
            'full_name' => $this->getFullName(),
            'component' => $this->component,
            'component_display_name' => $this->getComponentDisplayName(),
            'description' => $this->description,
            'additional_levels' => $this->additional_levels,
            'price' => $this->price,
            'rarity' => $this->rarity,
            'requirements' => $this->requirements,
        ];
    }
}

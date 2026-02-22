<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TradingHubResource extends JsonResource
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
            'type' => $this->type,
            'tier' => $this->getTier(),
            'location' => $this->whenLoaded('pointOfInterest', fn () => new PointOfInterestResource($this->pointOfInterest)),
            'gate_count' => $this->gate_count,
            'tax_rate' => (float) $this->tax_rate,
            'services' => $this->services ?? [],
            'has_salvage_yard' => $this->has_salvage_yard,
            'has_plans' => $this->has_plans,
            'has_shipyard' => $this->hasShipyard(),
            'is_active' => $this->is_active,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MineralResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'description' => $this->description,
            'base_value' => (float) $this->base_value,
            'rarity' => $this->rarity->value ?? $this->rarity,
            'market_value' => $this->getMarketValue(),
        ];
    }
}

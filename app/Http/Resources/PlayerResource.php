<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerResource extends JsonResource
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
            'call_sign' => $this->call_sign,
            'credits' => (float) $this->credits,
            'experience' => $this->experience,
            'level' => $this->level,
            'status' => $this->status,
            'current_location' => $this->when(
                $this->relationLoaded('currentLocation'),
                new PointOfInterestResource($this->currentLocation)
            ),
            'active_ship' => $this->when(
                $this->relationLoaded('activeShip'),
                new ShipResource($this->activeShip)
            ),
            'galaxy' => $this->when(
                $this->relationLoaded('galaxy'),
                [
                    'id' => $this->galaxy->id,
                    'uuid' => $this->galaxy->uuid,
                    'name' => $this->galaxy->name,
                ]
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

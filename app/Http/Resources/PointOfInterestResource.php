<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PointOfInterestResource extends JsonResource
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
            'type' => $this->type,
            'x' => (float) $this->x,
            'y' => (float) $this->y,
            'is_inhabited' => $this->is_inhabited,
            'description' => $this->when($this->description, $this->description),
            'attributes' => $this->when($this->attributes, $this->attributes),
        ];
    }
}

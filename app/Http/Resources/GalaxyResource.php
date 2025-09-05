<?php

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Galaxy;

class GalaxyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid'   => $this->uuid,
            'name'   => $this->name,
            'seed'   => $this->seed,
            'width'  => $this->width,
            'height' => $this->height,
            'stars'  => $this->star_systems()->count(),
            'markets'=> $this->markets()->count(),
            'config' => $this->config,
        ];
    }
}


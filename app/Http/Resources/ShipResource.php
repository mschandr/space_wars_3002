<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipResource extends JsonResource
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
            'current_fuel' => $this->current_fuel,
            'max_fuel' => $this->max_fuel,
            'fuel_regen_rate' => (float) $this->fuel_regen_rate,
            'time_to_full_fuel' => $this->when(
                method_exists($this, 'getTimeToFullFuel'),
                function () {
                    return $this->getTimeToFullFuel();
                }
            ),
            'hull' => $this->hull,
            'max_hull' => $this->max_hull,
            'weapons' => $this->weapons,
            'cargo_hold' => $this->cargo_hold,
            'sensors' => $this->sensors,
            'warp_drive' => $this->warp_drive,
            'current_cargo' => $this->when(
                $this->relationLoaded('cargos'),
                function () {
                    return $this->cargos->sum('pivot.quantity');
                }
            ),
            'status' => $this->status ?? 'operational',
            'ship_class' => $this->when(
                $this->relationLoaded('ship'),
                [
                    'id' => $this->ship->id,
                    'name' => $this->ship->name,
                    'class' => $this->ship->class,
                ]
            ),
        ];
    }
}

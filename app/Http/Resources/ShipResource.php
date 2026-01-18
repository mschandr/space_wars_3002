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
        // Check if this is a Ship blueprint or PlayerShip instance
        $isBlueprint = $this->resource instanceof \App\Models\Ship;

        if ($isBlueprint) {
            // Ship blueprint data
            return [
                'id' => $this->id,
                'uuid' => $this->uuid,
                'name' => $this->name,
                'class' => $this->class,
                'description' => $this->description,
                'base_price' => $this->base_price,
                'cargo_capacity' => $this->cargo_capacity,
                'speed' => $this->speed,
                'hull_strength' => $this->hull_strength,
                'shield_strength' => $this->shield_strength,
                'weapon_slots' => $this->weapon_slots,
                'utility_slots' => $this->utility_slots,
                'rarity' => $this->rarity,
                'requirements' => $this->requirements,
                'attributes' => $this->attributes,
                'is_available' => $this->is_available,
            ];
        }

        // PlayerShip data
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

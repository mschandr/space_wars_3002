<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PirateEncounterResource extends JsonResource
{
    protected $fleet;

    public function __construct($resource, $fleet = null)
    {
        parent::__construct($resource);
        $this->fleet = $fleet;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'captain' => [
                'name' => $this->captain->getFullName(),
                'title' => $this->captain->title,
                'rank' => $this->captain->rank,
                'notoriety' => $this->captain->notoriety,
            ],
            'faction' => [
                'name' => $this->captain->faction->getFullName(),
                'prefix' => $this->captain->faction->prefix,
                'suffix' => $this->captain->faction->suffix,
            ],
            'difficulty_tier' => $this->difficulty_tier,
            'fleet' => $this->when($this->fleet, function () {
                return $this->fleet->map(fn ($ship) => [
                    'ship_name' => $ship->ship_name,
                    'hull' => $ship->hull,
                    'max_hull' => $ship->max_hull,
                    'weapons' => $ship->weapons,
                    'speed' => $ship->speed,
                    'warp_drive' => $ship->warp_drive,
                    'ship_class' => $ship->ship->class ?? 'Unknown',
                ]);
            }),
            'fleet_size' => $this->when($this->fleet, fn () => $this->fleet->count()),
            'is_active' => $this->is_active,
            'encounter_count' => $this->encounter_count,
        ];
    }
}

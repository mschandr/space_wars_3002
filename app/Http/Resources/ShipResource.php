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
            $attrs = $this->attributes ?? [];

            return [
                'uuid' => $this->uuid,
                'name' => $this->name,
                'class' => $this->class,
                'class_info' => self::getClassInfo($this->class),
                'description' => $this->description,
                'base_price' => $this->base_price,
                'rarity' => $this->rarity,
                'requirements' => $this->requirements,
                'is_available' => $this->is_available,

                // Core stats
                'stats' => [
                    'hull_strength' => $this->hull_strength,
                    'shield_strength' => $this->shield_strength,
                    'cargo_capacity' => $this->cargo_capacity,
                    'speed' => $this->speed,
                    'weapon_slots' => $this->weapon_slots,
                    'utility_slots' => $this->utility_slots,
                    'engine_slots' => $this->engine_slots,
                    'reactor_slots' => $this->reactor_slots,
                    'hull_plating_slots' => $this->hull_plating_slots,
                    'shield_slots' => $this->shield_slots,
                    'sensor_slots' => $this->sensor_slots,
                    'cargo_module_slots' => $this->cargo_module_slots,
                    'size_class' => $this->size_class,
                ],

                // Starting component levels
                'components' => [
                    'weapons' => $attrs['starting_weapons'] ?? 10,
                    'sensors' => $attrs['starting_sensors'] ?? 1,
                    'warp_drive' => $attrs['starting_warp_drive'] ?? 1,
                ],

                // Fuel system
                'fuel' => [
                    'max_fuel' => $attrs['max_fuel'] ?? 100,
                    'regen_rate' => $attrs['fuel_regen_rate'] ?? 1.0,
                    'consumption_rate' => $attrs['fuel_consumption_rate'] ?? 1.0,
                ],

                // Class-specific features
                'special_features' => self::getSpecialFeatures($this->class, $attrs),
            ];
        }

        // PlayerShip data
        return [
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
            'shields' => $this->shields ?? 0,
            'max_shields' => $this->max_shields ?? 0,
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
            'weapon_slots' => $this->weapon_slots,
            'utility_slots' => $this->utility_slots,
            'engine_slots' => $this->engine_slots,
            'reactor_slots' => $this->reactor_slots,
            'hull_plating_slots' => $this->hull_plating_slots,
            'shield_slots' => $this->shield_slots,
            'sensor_slots' => $this->sensor_slots,
            'cargo_module_slots' => $this->cargo_module_slots,
            'size_class' => $this->size_class,
            'status' => $this->status ?? 'operational',
            'location' => $this->when(
                $this->relationLoaded('currentLocation') && $this->currentLocation,
                fn () => [
                    'poi_uuid' => $this->currentLocation->uuid,
                    'name' => $this->currentLocation->name,
                    'x' => $this->currentLocation->x,
                    'y' => $this->currentLocation->y,
                    'type' => $this->currentLocation->type,
                    'is_inhabited' => $this->currentLocation->is_inhabited,
                ]
            ),
            'ship_class' => $this->when(
                $this->relationLoaded('ship'),
                fn () => [
                    'uuid' => $this->ship->uuid,
                    'name' => $this->ship->name,
                    'class' => $this->ship->class,
                    'class_info' => self::getClassInfo($this->ship->class),
                ]
            ),
        ];
    }

    /**
     * Get class description and role summary.
     */
    private static function getClassInfo(?string $class): ?array
    {
        if (! $class) {
            return null;
        }

        return match ($class) {
            'starter' => [
                'label' => 'Light Freighter',
                'role' => 'Balanced',
                'description' => 'A versatile entry-level vessel. Jack of all trades, master of none — reliable for new pilots learning the ropes.',
                'strengths' => ['Low maintenance', 'No requirements', 'Free starting ship'],
                'weaknesses' => ['Low combat power', 'Limited cargo', 'No special abilities'],
            ],
            'smuggler' => [
                'label' => 'Runner',
                'role' => 'Stealth Trader',
                'description' => 'A fast, stealthy ship with hidden cargo holds that pirates cannot scan. Ideal for running contraband through dangerous space.',
                'strengths' => ['Hidden cargo holds', 'High speed', 'Stealth bonus vs pirates'],
                'weaknesses' => ['Small visible cargo', 'Higher fuel consumption', 'Weak armor'],
            ],
            'battleship' => [
                'label' => 'Dreadnought',
                'role' => 'Heavy Combat',
                'description' => 'A capital warship built for dominance in combat. Heavy armor, powerful shields, and numerous weapon hardpoints make it nearly unstoppable in a fight.',
                'strengths' => ['Massive hull and shields', '8 weapon slots', '25% combat bonus', 'Armor plating'],
                'weaknesses' => ['Slow speed', 'High fuel consumption', 'Small cargo hold', 'Expensive'],
            ],
            'cargo' => [
                'label' => 'Supertanker',
                'role' => 'Heavy Hauler',
                'description' => 'A massive freighter designed to move enormous quantities of goods. Its cavernous holds can carry 100x what a starter ship manages.',
                'strengths' => ['Enormous cargo capacity', 'High hull strength', 'Bulk trading profits'],
                'weaknesses' => ['Very slow', 'Very high fuel consumption', 'Weak weapons', 'Easy pirate target'],
            ],
            'carrier' => [
                'label' => 'Command Ship',
                'role' => 'Fighter Deployment',
                'description' => 'A mobile command center that deploys and coordinates fighter squadrons. Trades raw firepower for tactical flexibility through its hangar bays.',
                'strengths' => ['12 fighter capacity', 'High utility slots', 'Command bonus to fighters', 'Strong shields'],
                'weaknesses' => ['Very slow', 'Highest fuel consumption', 'Fewer direct weapons', 'Very expensive'],
            ],
            'colony_ship' => [
                'label' => 'Ark',
                'role' => 'Colonization',
                'description' => 'A colossal generation ship carrying 10,000 colonists in cryostasis plus all supplies needed to establish a new world. The key to galactic expansion.',
                'strengths' => ['Carries 10,000 colonists', 'Pre-loaded colony supplies', 'Large cargo hold', 'Self-sufficient'],
                'weaknesses' => ['Slowest ship class', 'Highest fuel consumption', 'Minimal weapons', 'Most expensive'],
            ],
            default => [
                'label' => ucfirst($class),
                'role' => 'Unknown',
                'description' => 'An unclassified vessel type.',
                'strengths' => [],
                'weaknesses' => [],
            ],
        };
    }

    /**
     * Extract class-specific special features from attributes.
     */
    private static function getSpecialFeatures(string $class, array $attrs): array
    {
        $features = [];

        switch ($class) {
            case 'smuggler':
                if (isset($attrs['hidden_hold_capacity'])) {
                    $features[] = [
                        'name' => 'Hidden Cargo Hold',
                        'value' => $attrs['hidden_hold_capacity'],
                        'description' => 'Concealed cargo space invisible to pirate scans',
                    ];
                }
                if (isset($attrs['stealth_bonus'])) {
                    $features[] = [
                        'name' => 'Stealth Systems',
                        'value' => ($attrs['stealth_bonus'] * 100).'%',
                        'description' => 'Reduces pirate detection chance',
                    ];
                }
                break;

            case 'battleship':
                if (isset($attrs['armor_plating'])) {
                    $features[] = [
                        'name' => 'Armor Plating',
                        'value' => $attrs['armor_plating'],
                        'description' => 'Flat damage reduction on incoming attacks',
                    ];
                }
                if (isset($attrs['combat_bonus'])) {
                    $features[] = [
                        'name' => 'Combat Systems',
                        'value' => ($attrs['combat_bonus'] * 100).'%',
                        'description' => 'Bonus to overall combat effectiveness',
                    ];
                }
                break;

            case 'cargo':
                if (isset($attrs['bulk_trade_bonus'])) {
                    $features[] = [
                        'name' => 'Bulk Trading',
                        'value' => ($attrs['bulk_trade_bonus'] * 100).'%',
                        'description' => 'Better prices when trading in large quantities',
                    ];
                }
                break;

            case 'carrier':
                if (isset($attrs['fighter_capacity'])) {
                    $features[] = [
                        'name' => 'Fighter Hangar',
                        'value' => $attrs['fighter_capacity'],
                        'description' => 'Maximum fighter squadrons that can be deployed',
                    ];
                }
                if (isset($attrs['command_bonus'])) {
                    $features[] = [
                        'name' => 'Command Link',
                        'value' => ($attrs['command_bonus'] * 100).'%',
                        'description' => 'Bonus to deployed fighter effectiveness',
                    ];
                }
                break;

            case 'colony_ship':
                if (isset($attrs['colonist_capacity'])) {
                    $features[] = [
                        'name' => 'Cryogenic Bays',
                        'value' => number_format($attrs['colonist_capacity']),
                        'description' => 'Maximum colonists in cryogenic stasis',
                    ];
                }
                if (isset($attrs['starting_colonists'])) {
                    $features[] = [
                        'name' => 'Pre-loaded Colonists',
                        'value' => number_format($attrs['starting_colonists']),
                        'description' => 'Colonists ready for deployment on purchase',
                    ];
                }
                if (isset($attrs['colony_supplies'])) {
                    $features[] = [
                        'name' => 'Colony Supply Kit',
                        'value' => 'Full loadout',
                        'description' => 'Mining equipment, habitat modules, food, medical, and seed bank',
                    ];
                }
                break;
        }

        // Capital ship flag
        if ($attrs['is_capital_ship'] ?? false) {
            $features[] = [
                'name' => 'Capital Ship',
                'value' => true,
                'description' => 'Large vessel classification — restricted from certain docking facilities',
            ];
        }

        return $features;
    }
}

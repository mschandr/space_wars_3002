<?php

namespace App\Services\Flotilla;

use App\Models\Flotilla;
use App\Models\PlayerShip;
use Illuminate\Support\Facades\DB;

class FlotillaSalvageService
{
    /**
     * Get available salvage options for a flotilla after battle
     *
     * @param Flotilla $flotilla
     * @return array ['cargo_available' => int, 'components_available' => [...]]
     */
    public function getSalvageOptions(Flotilla $flotilla): array
    {
        $destroyedShips = $flotilla->ships()
            ->where('hull', '<=', 0)
            ->get();

        $cargoAvailable = 0;
        $componentsAvailable = [];

        foreach ($destroyedShips as $ship) {
            // Calculate recoverable cargo (70% of destroyed ship's cargo)
            $cargoAvailable += (int) ($ship->current_cargo * config('game_config.flotilla.cargo_recovery_rate'));

            // Get components from destroyed ship (will be salvaged with loss)
            foreach ($ship->components as $component) {
                $componentsAvailable[] = [
                    'component_id' => $component->component_id,
                    'component_name' => $component->component->name ?? 'Unknown',
                    'component_level' => $component->level ?? 1,
                    'slot_type' => $component->slot_type,
                ];
            }
        }

        return [
            'cargo_available' => $cargoAvailable,
            'components_available' => $componentsAvailable,
            'destroyed_ship_count' => $destroyedShips->count(),
        ];
    }

    /**
     * Recover cargo from destroyed ships (70% recovery rate)
     * Distributed to surviving ships by available hold space
     *
     * @param Flotilla $flotilla
     * @return array ['cargo_recovered' => int, 'cargo_lost' => int, 'distribution' => [...]]
     * @throws \Exception
     */
    public function recoverCargo(Flotilla $flotilla): array
    {
        return DB::transaction(function () use ($flotilla) {
            $survivingShips = $flotilla->ships()
                ->where('hull', '>', 0)
                ->get();

            $destroyedShips = $flotilla->ships()
                ->where('hull', '<=', 0)
                ->get();

            if ($survivingShips->count() === 0 || $destroyedShips->count() === 0) {
                return [
                    'cargo_recovered' => 0,
                    'cargo_lost' => 0,
                    'distribution' => [],
                ];
            }

            $recoveryRate = config('game_config.flotilla.cargo_recovery_rate', 0.70);
            $totalCargoRecoverable = 0;
            $distribution = [];

            // Calculate total recoverable cargo
            foreach ($destroyedShips as $ship) {
                $totalCargoRecoverable += (int) ($ship->current_cargo * $recoveryRate);
            }

            $cargoToDistribute = $totalCargoRecoverable;

            // Distribute cargo to surviving ships by available space
            foreach ($survivingShips as $ship) {
                $availableSpace = $ship->getAvailableCargoSpace();

                if ($cargoToDistribute <= 0 || $availableSpace <= 0) {
                    continue;
                }

                $cargoForThisShip = min($cargoToDistribute, $availableSpace);

                // Add cargo to ship
                $ship->addCargo((int) $cargoForThisShip);

                $distribution[] = [
                    'ship_id' => $ship->id,
                    'ship_name' => $ship->name,
                    'cargo_added' => (int) $cargoForThisShip,
                ];

                $cargoToDistribute -= $cargoForThisShip;
            }

            // Remaining cargo is lost (insufficient hold space)
            $cargoLost = max(0, $cargoToDistribute);

            return [
                'cargo_recovered' => $totalCargoRecoverable - $cargoLost,
                'cargo_lost' => $cargoLost,
                'distribution' => $distribution,
            ];
        });
    }

    /**
     * Recover components from destroyed ships with escalating loss
     *
     * Loss escalation:
     * 1st component: 10% loss
     * 2nd component: 20% loss
     * 3rd component: 30% loss
     * etc.
     *
     * @param Flotilla $flotilla
     * @return array ['components_recovered' => [...]]
     * @throws \Exception
     */
    public function recoverComponents(Flotilla $flotilla): array
    {
        return DB::transaction(function () use ($flotilla) {
            $survivingShips = $flotilla->ships()
                ->where('hull', '>', 0)
                ->with('components.component')
                ->get();

            $destroyedShips = $flotilla->ships()
                ->where('hull', '<=', 0)
                ->with('components.component')
                ->get();

            if ($destroyedShips->count() === 0) {
                return ['components_recovered' => []];
            }

            $recovered = [];
            $componentIndex = 0;
            $lossTable = config('game_config.flotilla.component_recovery_loss', []);

            foreach ($destroyedShips as $ship) {
                foreach ($ship->components as $component) {
                    $componentIndex++;

                    // Get loss percentage for this component in recovery order
                    $lossPercent = $lossTable[$componentIndex] ?? 0.90; // Default 90% loss if index exceeds config

                    // Calculate effective level after loss
                    $originalLevel = $component->level ?? 1;
                    $effectiveLevel = max(1, (int) ($originalLevel * (1 - $lossPercent)));

                    $recovered[] = [
                        'component_id' => $component->component_id,
                        'component_name' => $component->component->name ?? 'Unknown',
                        'original_level' => $originalLevel,
                        'effective_level' => $effectiveLevel,
                        'loss_percent' => (int) ($lossPercent * 100),
                        'from_ship' => $ship->name,
                    ];
                }
            }

            return ['components_recovered' => $recovered];
        });
    }

    /**
     * Recover pirate loot from defeated pirates
     * Always available, separate from cargo/component XOR choice
     * 50%+ loss on components, random % of cargo
     *
     * @param array $pirateShips Array of pirate ships with cargo/components
     * @return array ['cargo_recovered' => int, 'components_recovered' => [...]]
     */
    public function recoverPirateLoot(array $pirateShips): array
    {
        $cargoRecovered = 0;
        $componentsRecovered = [];

        $cargoRecoveryRate = config('game_config.flotilla.pirate_loot_recovery_rate', 0.50);

        foreach ($pirateShips as $pirate) {
            // Recover random percentage of pirate cargo (50% baseline)
            if (isset($pirate['cargo']) && $pirate['cargo'] > 0) {
                $randomPercent = random_int(30, 70) / 100; // 30-70% random
                $cargoRecovered += (int) ($pirate['cargo'] * $cargoRecoveryRate * $randomPercent);
            }

            // Recover pirate components with high loss (50%+)
            if (isset($pirate['components'])) {
                foreach ($pirate['components'] as $index => $component) {
                    // High loss on pirate components
                    $lossPercent = random_int(50, 90) / 100; // 50-90% loss
                    $originalLevel = $component['level'] ?? 1;
                    $effectiveLevel = max(1, (int) ($originalLevel * (1 - $lossPercent)));

                    $componentsRecovered[] = [
                        'component_name' => $component['name'] ?? 'Pirate Component',
                        'original_level' => $originalLevel,
                        'effective_level' => $effectiveLevel,
                        'loss_percent' => (int) ($lossPercent * 100),
                        'source' => 'pirate_loot',
                    ];
                }
            }
        }

        return [
            'cargo_recovered' => $cargoRecovered,
            'components_recovered' => $componentsRecovered,
        ];
    }

    /**
     * Handle XOR salvage choice: player chooses to recover EITHER cargo OR components
     * This is the main post-battle salvage flow
     *
     * @param Flotilla $flotilla
     * @param string $choice 'cargo' or 'components'
     * @return array Results of chosen salvage type
     * @throws \Exception
     */
    public function executeSalvageChoice(Flotilla $flotilla, string $choice): array
    {
        if (!in_array($choice, ['cargo', 'components'])) {
            throw new \Exception('Invalid salvage choice. Must be "cargo" or "components"');
        }

        if ($choice === 'cargo') {
            return $this->recoverCargo($flotilla);
        } else {
            return $this->recoverComponents($flotilla);
        }
    }

    /**
     * Get detailed salvage report after battle
     *
     * @param Flotilla $flotilla
     * @return array
     */
    public function getSalvageReport(Flotilla $flotilla): array
    {
        $options = $this->getSalvageOptions($flotilla);
        $survivingShips = $flotilla->ships()
            ->where('hull', '>', 0)
            ->get();

        return [
            'battle_result' => 'victory',
            'destroyed_ships_count' => $options['destroyed_ship_count'],
            'surviving_ships' => $survivingShips->map(function ($ship) {
                return [
                    'id' => $ship->id,
                    'name' => $ship->name,
                    'hull_remaining' => $ship->hull,
                    'available_cargo_space' => $ship->getAvailableCargoSpace(),
                ];
            })->all(),
            'salvage_options' => [
                'cargo' => [
                    'available' => $options['cargo_available'],
                    'recovery_rate' => config('game_config.flotilla.cargo_recovery_rate') * 100 . '%',
                ],
                'components' => [
                    'available_count' => count($options['components_available']),
                    'loss_escalation' => 'Progressive (1st=10%, 2nd=20%, etc.)',
                ],
            ],
            'xor_note' => 'Choose ONE: recover cargo OR recover components. Cannot do both.',
        ];
    }
}

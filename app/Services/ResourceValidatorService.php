<?php

namespace App\Services;

use App\Models\Colony;
use App\Models\Player;

/**
 * Service for validating resource availability.
 *
 * Consolidates the repeated pattern of checking if an entity
 * has sufficient resources (credits, minerals, etc.) before
 * performing an action.
 */
class ResourceValidatorService
{
    /**
     * Validation result indicating success.
     */
    public const SUCCESS = ['valid' => true, 'message' => null, 'code' => null];

    /**
     * Validate that a player has sufficient credits.
     *
     * @param  float|int  $amount  Required credits
     * @return array{valid: bool, message: string|null, code: string|null}
     */
    public function validateCredits(Player $player, float|int $amount): array
    {
        if ($player->credits < $amount) {
            return [
                'valid' => false,
                'message' => 'Insufficient credits',
                'code' => 'INSUFFICIENT_CREDITS',
            ];
        }

        return self::SUCCESS;
    }

    /**
     * Validate that a colony has sufficient minerals.
     *
     * @param  int  $amount  Required minerals
     * @return array{valid: bool, message: string|null, code: string|null}
     */
    public function validateColonyMinerals(Colony $colony, int $amount): array
    {
        if ($colony->mineral_storage < $amount) {
            return [
                'valid' => false,
                'message' => 'Insufficient minerals in colony storage',
                'code' => 'INSUFFICIENT_MINERALS',
            ];
        }

        return self::SUCCESS;
    }

    /**
     * Validate that a colony has sufficient population.
     *
     * @param  int  $amount  Required population
     * @return array{valid: bool, message: string|null, code: string|null}
     */
    public function validateColonyPopulation(Colony $colony, int $amount): array
    {
        if ($colony->population < $amount) {
            return [
                'valid' => false,
                'message' => 'Insufficient colony population',
                'code' => 'INSUFFICIENT_POPULATION',
            ];
        }

        return self::SUCCESS;
    }

    /**
     * Validate that a player has sufficient cargo space.
     *
     * @param  int  $amount  Required cargo space
     * @return array{valid: bool, message: string|null, code: string|null}
     */
    public function validateCargoSpace(Player $player, int $amount): array
    {
        $ship = $player->activeShip;

        if (! $ship) {
            return [
                'valid' => false,
                'message' => 'No active ship',
                'code' => 'NO_ACTIVE_SHIP',
            ];
        }

        $availableSpace = $ship->getEffectiveCargoHold() - $ship->current_cargo;

        if ($availableSpace < $amount) {
            return [
                'valid' => false,
                'message' => 'Insufficient cargo space',
                'code' => 'INSUFFICIENT_CARGO_SPACE',
            ];
        }

        return self::SUCCESS;
    }

    /**
     * Validate that a player has sufficient fuel.
     *
     * @param  float|int  $amount  Required fuel
     * @return array{valid: bool, message: string|null, code: string|null}
     */
    public function validateFuel(Player $player, float|int $amount): array
    {
        $ship = $player->activeShip;

        if (! $ship) {
            return [
                'valid' => false,
                'message' => 'No active ship',
                'code' => 'NO_ACTIVE_SHIP',
            ];
        }

        if ($ship->current_fuel < $amount) {
            return [
                'valid' => false,
                'message' => 'Insufficient fuel',
                'code' => 'INSUFFICIENT_FUEL',
            ];
        }

        return self::SUCCESS;
    }

    /**
     * Validate multiple resources at once.
     *
     * @param  array  $validations  Array of [callable, ...args] pairs
     * @return array{valid: bool, message: string|null, code: string|null}
     *
     * Example:
     * ```php
     * $result = $validator->validateMultiple([
     *     [$validator, 'validateCredits', $player, 1000],
     *     [$validator, 'validateCargoSpace', $player, 50],
     * ]);
     * ```
     */
    public function validateMultiple(array $validations): array
    {
        foreach ($validations as $validation) {
            $method = array_shift($validation);
            $args = $validation;

            if (is_array($method)) {
                $result = call_user_func_array($method, $args);
            } else {
                $result = $this->$method(...$args);
            }

            if (! $result['valid']) {
                return $result;
            }
        }

        return self::SUCCESS;
    }

    /**
     * Validate player resources using a cost array.
     *
     * @param  array  $costs  Array of ['credits' => amount, 'cargo' => amount, 'fuel' => amount]
     * @return array{valid: bool, message: string|null, code: string|null}
     */
    public function validatePlayerResources(Player $player, array $costs): array
    {
        if (isset($costs['credits'])) {
            $result = $this->validateCredits($player, $costs['credits']);
            if (! $result['valid']) {
                return $result;
            }
        }

        if (isset($costs['cargo'])) {
            $result = $this->validateCargoSpace($player, $costs['cargo']);
            if (! $result['valid']) {
                return $result;
            }
        }

        if (isset($costs['fuel'])) {
            $result = $this->validateFuel($player, $costs['fuel']);
            if (! $result['valid']) {
                return $result;
            }
        }

        return self::SUCCESS;
    }

    /**
     * Validate colony resources using a cost array.
     *
     * @param  array  $costs  Array of ['minerals' => amount, 'population' => amount]
     * @return array{valid: bool, message: string|null, code: string|null}
     */
    public function validateColonyResources(Colony $colony, array $costs): array
    {
        if (isset($costs['minerals'])) {
            $result = $this->validateColonyMinerals($colony, $costs['minerals']);
            if (! $result['valid']) {
                return $result;
            }
        }

        if (isset($costs['population'])) {
            $result = $this->validateColonyPopulation($colony, $costs['population']);
            if (! $result['valid']) {
                return $result;
            }
        }

        return self::SUCCESS;
    }
}

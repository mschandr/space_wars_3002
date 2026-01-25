<?php

namespace App\Enums\Defense;

/**
 * Types of system defenses that can be deployed at fortified POIs.
 *
 * These are pre-built defenses at core systems, not player-constructed.
 */
enum SystemDefenseType: string
{
    case ORBITAL_CANNON = 'orbital_cannon';
    case SPACE_LASER = 'space_laser';
    case GROUND_MISSILE = 'ground_missile';
    case PLANETARY_SHIELD = 'planetary_shield';
    case FIGHTER_PORT = 'fighter_port';

    /**
     * Base damage for the defense type.
     *
     * @return int The base damage value; 0 for defenses that do not deal damage (e.g., planetary shield).
     */
    public function getBaseDamage(): int
    {
        return match ($this) {
            self::ORBITAL_CANNON => 50,
            self::SPACE_LASER => 75,
            self::GROUND_MISSILE => 40,
            self::PLANETARY_SHIELD => 0,  // Shields don't deal damage
            self::FIGHTER_PORT => 25,     // Per fighter
        };
    }

    /**
     * Provide the base health (hit points) for the defense type.
     *
     * @return int The base health value for the defense type.
     */
    public function getBaseHealth(): int
    {
        return match ($this) {
            self::ORBITAL_CANNON => 500,
            self::SPACE_LASER => 300,
            self::GROUND_MISSILE => 200,
            self::PLANETARY_SHIELD => 10000,  // High health for shields
            self::FIGHTER_PORT => 1000,
        };
    }

    /**
     * Get the cooldown between attacks in combat rounds for this defense type.
     *
     * @return int The number of combat rounds to wait between attacks; `0` indicates the defense is always active.
     */
    public function getCooldown(): int
    {
        return match ($this) {
            self::ORBITAL_CANNON => 2,
            self::SPACE_LASER => 1,
            self::GROUND_MISSILE => 3,
            self::PLANETARY_SHIELD => 0,  // Always active
            self::FIGHTER_PORT => 1,
        };
    }

    /**
     * Get the attack or protection range for this defense type.
     *
     * @return int The range in units for the defense type; for PLANETARY_SHIELD this is the protection radius.
     */
    public function getRange(): int
    {
        return match ($this) {
            self::ORBITAL_CANNON => 100,
            self::SPACE_LASER => 150,
            self::GROUND_MISSILE => 200,
            self::PLANETARY_SHIELD => 50,  // Protection radius
            self::FIGHTER_PORT => 250,
        };
    }

    /**
     * Indicates whether the defense type can engage enemy ships.
     *
     * @return bool `true` if the defense can attack enemy ships, `false` otherwise.
     */
    public function canAttack(): bool
    {
        return match ($this) {
            self::ORBITAL_CANNON,
            self::SPACE_LASER,
            self::GROUND_MISSILE,
            self::FIGHTER_PORT => true,
            self::PLANETARY_SHIELD => false,
        };
    }

    / **
     * Indicates whether the defense is a passive protection system.
     *
     * @return bool `true` if this defense is a planetary shield (passive), `false` otherwise.
     */
    public function isPassiveDefense(): bool
    {
        return $this === self::PLANETARY_SHIELD;
    }

    /**
     * Get the default quantity for fortress deployments.
     */
    public function getFortressQuantity(): int
    {
        return match ($this) {
            self::ORBITAL_CANNON => 4,
            self::SPACE_LASER => 2,
            self::GROUND_MISSILE => 6,
            self::PLANETARY_SHIELD => 1,
            self::FIGHTER_PORT => 1,  // Port itself, contains fighters
        };
    }

    /**
     * Default fighter count for this defense type.
     *
     * @return int The default fighter count: 1000 for FIGHTER_PORT, 0 otherwise.
     */
    public function getDefaultFighterCount(): int
    {
        return $this === self::FIGHTER_PORT ? 1000 : 0;
    }

    /**
     * Get the human-readable label for the defense type.
     *
     * @return string The human-readable label for the defense type.
     */
    public function label(): string
    {
        return match ($this) {
            self::ORBITAL_CANNON => 'Orbital Cannon',
            self::SPACE_LASER => 'Space Laser',
            self::GROUND_MISSILE => 'Ground-to-Space Missile',
            self::PLANETARY_SHIELD => 'Planetary Shield',
            self::FIGHTER_PORT => 'Fighter Port',
        };
    }

    /**
     * Retrieve a one-line human-readable description for the defense type.
     *
     * @return string The description text for the current defense type.
     */
    public function description(): string
    {
        return match ($this) {
            self::ORBITAL_CANNON => 'Heavy orbital platform dealing sustained damage to capital ships.',
            self::SPACE_LASER => 'High-powered laser capable of rapid fire against smaller vessels.',
            self::GROUND_MISSILE => 'Surface-launched missiles with long range but slower reload.',
            self::PLANETARY_SHIELD => 'Energy shield protecting the system from bombardment.',
            self::FIGHTER_PORT => 'Launch facility housing defensive fighter squadrons.',
        };
    }

    /**
     * Compile the default attribute set for this defense type.
     *
     * The returned associative array includes base combat attributes and any
     * type-specific fields:
     * - `damage`: base damage value.
     * - `range`: attack or protection range.
     * - `cooldown`: rounds between actions.
     * - `fighter_count`: default fighters (present only for FIGHTER_PORT).
     * - `fighter_damage`: per-fighter damage (present only for FIGHTER_PORT).
     * - `fighter_health`: per-fighter health (present only for FIGHTER_PORT).
     * - `damage_reduction`: fractional damage reduction (present only for PLANETARY_SHIELD).
     * - `recharge_rate`: shield recharge amount per round (present only for PLANETARY_SHIELD).
     *
     * @return array<string, int|float> Associative map of attribute names to their numeric values.
     */
    public function getDefaultAttributes(): array
    {
        $attributes = [
            'damage' => $this->getBaseDamage(),
            'range' => $this->getRange(),
            'cooldown' => $this->getCooldown(),
        ];

        if ($this === self::FIGHTER_PORT) {
            $attributes['fighter_count'] = $this->getDefaultFighterCount();
            $attributes['fighter_damage'] = 25;
            $attributes['fighter_health'] = 50;
        }

        if ($this === self::PLANETARY_SHIELD) {
            $attributes['damage_reduction'] = 0.5;  // 50% damage reduction
            $attributes['recharge_rate'] = 100;     // Health per round
        }

        return $attributes;
    }
}
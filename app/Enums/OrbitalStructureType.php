<?php

namespace App\Enums;

enum OrbitalStructureType: string
{
    case ORBITAL_DEFENSE = 'orbital_defense';
    case MAGNETIC_MINE = 'magnetic_mine';
    case MINING_PLATFORM = 'mining_platform';
    case ORBITAL_BASE = 'orbital_base';

    public function label(): string
    {
        return match ($this) {
            self::ORBITAL_DEFENSE => 'Orbital Defense Platform',
            self::MAGNETIC_MINE => 'Magnetic Mine',
            self::MINING_PLATFORM => 'Mining Platform',
            self::ORBITAL_BASE => 'Orbital Base',
        };
    }

    public function maxPerBody(): int
    {
        return match ($this) {
            self::ORBITAL_DEFENSE => 4,
            self::MAGNETIC_MINE => 10,
            self::MINING_PLATFORM => 2,
            self::ORBITAL_BASE => 1,
        };
    }

    public function baseHealth(): int
    {
        return match ($this) {
            self::ORBITAL_DEFENSE => 500,
            self::MAGNETIC_MINE => 50,
            self::MINING_PLATFORM => 300,
            self::ORBITAL_BASE => 1000,
        };
    }

    /**
     * @return array{credits: int, minerals: int}
     */
    public function baseCost(): array
    {
        return match ($this) {
            self::ORBITAL_DEFENSE => ['credits' => 50000, 'minerals' => 10000],
            self::MAGNETIC_MINE => ['credits' => 5000, 'minerals' => 2000],
            self::MINING_PLATFORM => ['credits' => 30000, 'minerals' => 8000],
            self::ORBITAL_BASE => ['credits' => 100000, 'minerals' => 20000],
        };
    }

    /**
     * @return array{credits: int, minerals: int}
     */
    public function operatingCosts(): array
    {
        return match ($this) {
            self::ORBITAL_DEFENSE => ['credits' => 100, 'minerals' => 5],
            self::MAGNETIC_MINE => ['credits' => 0, 'minerals' => 0],
            self::MINING_PLATFORM => ['credits' => 50, 'minerals' => 0],
            self::ORBITAL_BASE => ['credits' => 200, 'minerals' => 10],
        };
    }

    public function effects(): array
    {
        return match ($this) {
            self::ORBITAL_DEFENSE => ['defense_rating' => 100, 'damage_per_round' => 25],
            self::MAGNETIC_MINE => ['mine_damage' => 150, 'decompression' => true],
            self::MINING_PLATFORM => ['extraction_rate' => 50, 'storage' => 500],
            self::ORBITAL_BASE => ['docking_slots' => 4, 'cargo_capacity' => 2000, 'repair' => true],
        };
    }
}

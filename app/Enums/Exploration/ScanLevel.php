<?php

namespace App\Enums\Exploration;

/**
 * Scan Level Enum
 *
 * Represents the depth of system scanning capability based on ship sensor level.
 * Higher scan levels reveal more detailed information about a system.
 */
enum ScanLevel: int
{
    case UNSCANNED = 0;
    case GEOGRAPHY = 1;
    case GATES = 2;
    case BASIC_RESOURCES = 3;
    case RARE_RESOURCES = 4;
    case HIDDEN_FEATURES = 5;
    case ANOMALIES = 6;
    case DEEP_SCAN = 7;
    case ADVANCED_INTEL = 8;
    case PRECURSOR_SECRETS = 9;

    /**
     * Precursor ships have this effective sensor level (see everything).
     */
    public const PRECURSOR_LEVEL = 100;

    /**
     * Get the human-readable label for this scan level.
     */
    public function label(): string
    {
        return match ($this) {
            self::UNSCANNED => 'Unscanned',
            self::GEOGRAPHY => 'Basic Geography',
            self::GATES => 'Gate Detection',
            self::BASIC_RESOURCES => 'Basic Resources',
            self::RARE_RESOURCES => 'Rare Resources',
            self::HIDDEN_FEATURES => 'Hidden Features',
            self::ANOMALIES => 'Anomaly Detection',
            self::DEEP_SCAN => 'Deep Scan',
            self::ADVANCED_INTEL => 'Advanced Intel',
            self::PRECURSOR_SECRETS => 'Precursor Secrets',
        };
    }

    /**
     * Get description of what this level reveals.
     */
    public function description(): string
    {
        return match ($this) {
            self::UNSCANNED => 'No data available',
            self::GEOGRAPHY => 'Planet count, types (rocky/gas/ice), dwarf planets, asteroid belts, basic habitability',
            self::GATES => 'Gate presence, dormant status, unknown destinations',
            self::BASIC_RESOURCES => 'Mineral deposits on rocky planets, metallic hydrogen on gas giants',
            self::RARE_RESOURCES => 'Asteroid field minerals, uncommon deposits',
            self::HIDDEN_FEATURES => 'Habitable moons, orbital mining opportunities, ring mineral deposits',
            self::ANOMALIES => 'Ancient ruins, spatial anomalies, derelict ships',
            self::DEEP_SCAN => 'Subsurface deposits, core composition, terraforming viability',
            self::ADVANCED_INTEL => 'Pirate hideouts, hidden bases, cloaked structures',
            self::PRECURSOR_SECRETS => 'Hidden ancient gates, precursor tech caches, special gate fuel locations',
        };
    }

    /**
     * Get the categories of information revealed at this level.
     *
     * @return array<string>
     */
    public function reveals(): array
    {
        return match ($this) {
            self::UNSCANNED => [],
            self::GEOGRAPHY => ['geography', 'planet_count', 'planet_types', 'habitability_basic'],
            self::GATES => ['gates_presence', 'gate_status'],
            self::BASIC_RESOURCES => ['minerals_basic', 'gas_giant_resources'],
            self::RARE_RESOURCES => ['minerals_rare', 'asteroid_resources'],
            self::HIDDEN_FEATURES => ['hidden_moons', 'orbital_mining', 'ring_deposits'],
            self::ANOMALIES => ['anomalies', 'ruins', 'derelicts'],
            self::DEEP_SCAN => ['deep_scan', 'subsurface', 'terraforming'],
            self::ADVANCED_INTEL => ['intel', 'pirate_hideouts', 'hidden_bases'],
            self::PRECURSOR_SECRETS => ['precursor_gates', 'precursor_tech', 'ancient_secrets'],
        };
    }

    /**
     * Get color for UI display (hex color).
     */
    public function color(): string
    {
        return match ($this) {
            self::UNSCANNED => '#1a1a2e',
            self::GEOGRAPHY, self::GATES => '#4a4a6a',
            self::BASIC_RESOURCES, self::RARE_RESOURCES => '#3366aa',
            self::HIDDEN_FEATURES, self::ANOMALIES => '#33aa66',
            self::DEEP_SCAN, self::ADVANCED_INTEL => '#aa9933',
            self::PRECURSOR_SECRETS => '#ff6600',
        };
    }

    /**
     * Get opacity for UI display (0.0 - 1.0).
     */
    public function opacity(): float
    {
        return match ($this) {
            self::UNSCANNED => 0.2,
            self::GEOGRAPHY, self::GATES => 0.4,
            self::BASIC_RESOURCES, self::RARE_RESOURCES => 0.6,
            self::HIDDEN_FEATURES, self::ANOMALIES => 0.8,
            self::DEEP_SCAN, self::ADVANCED_INTEL => 0.9,
            self::PRECURSOR_SECRETS => 1.0,
        };
    }

    /**
     * Get the minimum sensor level required to achieve this scan level.
     */
    public function requiredSensorLevel(): int
    {
        return $this->value;
    }

    /**
     * Check if a given sensor level can achieve this scan level.
     */
    public function canAchieveWith(int $sensorLevel): bool
    {
        return $sensorLevel >= $this->value;
    }

    /**
     * Get all categories revealed up to and including this level.
     *
     * @return array<string>
     */
    public function allRevealedCategories(): array
    {
        $categories = [];
        foreach (self::cases() as $level) {
            if ($level->value <= $this->value) {
                $categories = array_merge($categories, $level->reveals());
            }
        }

        return array_unique($categories);
    }

    /**
     * Get the next scan level (or null if at max).
     */
    public function next(): ?self
    {
        $nextValue = $this->value + 1;
        foreach (self::cases() as $case) {
            if ($case->value === $nextValue) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Check if this level can reveal a specific feature type.
     */
    public function canReveal(string $featureType): bool
    {
        return in_array($featureType, $this->allRevealedCategories(), true);
    }

    /**
     * Create ScanLevel from sensor level.
     */
    public static function fromSensorLevel(int $sensorLevel): self
    {
        // Clamp to valid range
        $clamped = max(0, min(9, $sensorLevel));

        return self::from($clamped);
    }

    /**
     * Get the highest scan level available.
     */
    public static function max(): self
    {
        return self::PRECURSOR_SECRETS;
    }
}

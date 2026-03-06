<?php

namespace App\Services\Config;

/**
 * EconomyConfigValidator
 *
 * Validates and normalizes economy configuration values.
 * Provides a single source of truth for config validation and default coercion.
 *
 * Usage:
 *   $validated = EconomyConfigValidator::validate();
 *   $unitsPerStep = $validated->unitsPerStep; // Always positive
 *   $spreadPerSide = $validated->spreadPerSide; // Always in [0, 1]
 */
class EconomyConfigValidator
{
    private int $unitsPerStep;
    private float $spreadPerSide;
    private float $minPrice;
    private float $maxPrice;

    /**
     * Validate and normalize all economy config values
     */
    public static function validate(): self
    {
        return new self();
    }

    private function __construct()
    {
        $this->validateUnitsPerStep();
        $this->validateSpreadPerSide();
        $this->validatePriceLimits();
    }

    /**
     * Validate units_per_step config
     * Must be a positive integer; coerce invalid values to 1
     */
    private function validateUnitsPerStep(): void
    {
        $value = (int) config('economy.pricing.units_per_step', 10);

        if ($value <= 0) {
            $value = 1;
        }

        $this->unitsPerStep = $value;
    }

    /**
     * Validate spread_per_side config
     * Must be in range [0, 1]; coerce to 0.05 if invalid
     */
    private function validateSpreadPerSide(): void
    {
        $value = (float) config('economy.pricing.spread_per_side', 0.05);

        // Clamp to valid range
        if ($value < 0 || $value > 1) {
            $value = 0.05;
        }

        $this->spreadPerSide = $value;
    }

    /**
     * Validate min_price and max_price configs
     * min_price must be positive; max_price must be > min_price
     */
    private function validatePriceLimits(): void
    {
        $minPrice = (float) config('economy.pricing.min_price', 1.0);
        $maxPrice = (float) config('economy.pricing.max_price', 999999.99);

        // Ensure min_price is positive
        if ($minPrice <= 0) {
            $minPrice = 1.0;
        }

        // Ensure max_price is greater than min_price
        if ($maxPrice <= $minPrice) {
            $maxPrice = $minPrice * 1000; // 1000x multiplier as fallback
        }

        $this->minPrice = $minPrice;
        $this->maxPrice = $maxPrice;
    }

    /**
     * Get validated units_per_step (always >= 1)
     */
    public function getUnitsPerStep(): int
    {
        return $this->unitsPerStep;
    }

    /**
     * Get validated spread_per_side (always in [0, 1])
     */
    public function getSpreadPerSide(): float
    {
        return $this->spreadPerSide;
    }

    /**
     * Get validated min_price (always > 0)
     */
    public function getMinPrice(): float
    {
        return $this->minPrice;
    }

    /**
     * Get validated max_price (always > min_price)
     */
    public function getMaxPrice(): float
    {
        return $this->maxPrice;
    }

    /**
     * Magic getter for property access
     * Example: $validator->unitsPerStep instead of $validator->getUnitsPerStep()
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'unitsPerStep' => $this->getUnitsPerStep(),
            'spreadPerSide' => $this->getSpreadPerSide(),
            'minPrice' => $this->getMinPrice(),
            'maxPrice' => $this->getMaxPrice(),
            default => throw new \InvalidArgumentException("Unknown property: $name"),
        };
    }
}

<?php

namespace App\Services\GalaxyGeneration\Data;

/**
 * Tracks performance metrics for a generation step.
 */
final class GenerationMetrics
{
    private float $startTime;

    private ?float $endTime = null;

    private array $counts = [];

    private array $custom = [];

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    /**
     * Mark generation as complete.
     */
    public function complete(): self
    {
        $this->endTime = microtime(true);

        return $this;
    }

    /**
     * Get elapsed time in milliseconds.
     */
    public function getElapsedMs(): float
    {
        $end = $this->endTime ?? microtime(true);

        return round(($end - $this->startTime) * 1000, 2);
    }

    /**
     * Get elapsed time in seconds.
     */
    public function getElapsedSeconds(): float
    {
        return round($this->getElapsedMs() / 1000, 3);
    }

    /**
     * Set a count metric.
     */
    public function setCount(string $key, int $value): self
    {
        $this->counts[$key] = $value;

        return $this;
    }

    /**
     * Increment a count metric.
     */
    public function increment(string $key, int $amount = 1): self
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + $amount;

        return $this;
    }

    /**
     * Get a count metric.
     */
    public function getCount(string $key): int
    {
        return $this->counts[$key] ?? 0;
    }

    /**
     * Get all count metrics.
     */
    public function getCounts(): array
    {
        return $this->counts;
    }

    /**
     * Set a custom metric.
     */
    public function setCustom(string $key, mixed $value): self
    {
        $this->custom[$key] = $value;

        return $this;
    }

    /**
     * Get a custom metric.
     */
    public function getCustom(string $key): mixed
    {
        return $this->custom[$key] ?? null;
    }

    /**
     * Convert to array for reporting.
     */
    public function toArray(): array
    {
        return [
            'elapsed_ms' => $this->getElapsedMs(),
            'elapsed_seconds' => $this->getElapsedSeconds(),
            'counts' => $this->counts,
            'custom' => $this->custom,
        ];
    }
}

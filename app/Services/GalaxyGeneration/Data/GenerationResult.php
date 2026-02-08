<?php

namespace App\Services\GalaxyGeneration\Data;

/**
 * Result from a generation step.
 */
final class GenerationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly GenerationMetrics $metrics,
        public readonly array $data = [],
        public readonly ?string $error = null,
    ) {}

    /**
     * Create a successful result.
     */
    public static function success(GenerationMetrics $metrics, array $data = []): self
    {
        return new self(
            success: true,
            metrics: $metrics->complete(),
            data: $data,
        );
    }

    /**
     * Create a failed result.
     */
    public static function failure(GenerationMetrics $metrics, string $error): self
    {
        return new self(
            success: false,
            metrics: $metrics->complete(),
            error: $error,
        );
    }

    /**
     * Get a specific data value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Convert to array for reporting.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'metrics' => $this->metrics->toArray(),
            'data' => $this->data,
            'error' => $this->error,
        ];
    }
}

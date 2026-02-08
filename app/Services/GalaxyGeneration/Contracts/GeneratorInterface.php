<?php

namespace App\Services\GalaxyGeneration\Contracts;

use App\Models\Galaxy;
use App\Services\GalaxyGeneration\Data\GenerationResult;

/**
 * Contract for all galaxy generation components.
 *
 * Each generator is:
 * - Stateless (receives all dependencies via generate())
 * - Self-contained (handles its own bulk operations)
 * - Metrics-aware (returns timing and count data)
 */
interface GeneratorInterface
{
    /**
     * Get the generator name for logging/metrics.
     */
    public function getName(): string;

    /**
     * Execute generation and return result with metrics.
     *
     * @param  Galaxy  $galaxy  The galaxy to generate for
     * @param  array  $context  Shared context from previous generators
     * @return GenerationResult Result with metrics and any output data
     */
    public function generate(Galaxy $galaxy, array $context = []): GenerationResult;

    /**
     * Get dependencies that must run before this generator.
     *
     * @return array<string> List of generator class names
     */
    public function getDependencies(): array;
}

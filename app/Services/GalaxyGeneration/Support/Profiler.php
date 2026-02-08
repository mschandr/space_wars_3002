<?php

namespace App\Services\GalaxyGeneration\Support;

/**
 * Lightweight profiler for detailed timing breakdowns.
 *
 * Tracks nested sections with microsecond precision.
 */
final class Profiler
{
    private static ?self $instance = null;

    private array $sections = [];

    private array $stack = [];

    private float $startTime;

    private bool $enabled = true;

    private function __construct()
    {
        $this->startTime = microtime(true);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = new self;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Start timing a section.
     */
    public function start(string $section): void
    {
        if (! $this->enabled) {
            return;
        }

        $key = $this->getFullKey($section);
        $this->stack[] = $section;

        if (! isset($this->sections[$key])) {
            $this->sections[$key] = [
                'calls' => 0,
                'total_ms' => 0,
                'min_ms' => PHP_FLOAT_MAX,
                'max_ms' => 0,
                'start' => null,
            ];
        }

        $this->sections[$key]['start'] = microtime(true);
        $this->sections[$key]['calls']++;
    }

    /**
     * Stop timing current section.
     */
    public function stop(): void
    {
        if (! $this->enabled || empty($this->stack)) {
            return;
        }

        $section = array_pop($this->stack);
        $key = $this->getFullKey($section);

        $this->stack[] = $section;
        array_pop($this->stack);

        if (isset($this->sections[$key]['start'])) {
            $elapsed = (microtime(true) - $this->sections[$key]['start']) * 1000;
            $this->sections[$key]['total_ms'] += $elapsed;
            $this->sections[$key]['min_ms'] = min($this->sections[$key]['min_ms'], $elapsed);
            $this->sections[$key]['max_ms'] = max($this->sections[$key]['max_ms'], $elapsed);
            $this->sections[$key]['start'] = null;
        }
    }

    /**
     * Time a callable and return its result.
     */
    public function time(string $section, callable $callback): mixed
    {
        $this->start($section);
        try {
            return $callback();
        } finally {
            $this->stop();
        }
    }

    /**
     * Get all section timings.
     */
    public function getResults(): array
    {
        $results = [];
        $totalTime = (microtime(true) - $this->startTime) * 1000;

        foreach ($this->sections as $key => $data) {
            $results[$key] = [
                'calls' => $data['calls'],
                'total_ms' => round($data['total_ms'], 2),
                'avg_ms' => $data['calls'] > 0 ? round($data['total_ms'] / $data['calls'], 3) : 0,
                'min_ms' => $data['min_ms'] === PHP_FLOAT_MAX ? 0 : round($data['min_ms'], 3),
                'max_ms' => round($data['max_ms'], 3),
                'pct' => $totalTime > 0 ? round(($data['total_ms'] / $totalTime) * 100, 1) : 0,
            ];
        }

        // Sort by total time descending
        uasort($results, fn ($a, $b) => $b['total_ms'] <=> $a['total_ms']);

        return $results;
    }

    /**
     * Get formatted report.
     */
    public function getReport(): string
    {
        $results = $this->getResults();
        $lines = ["=== Profiler Report ===\n"];

        foreach ($results as $section => $data) {
            $lines[] = sprintf(
                '%-40s %8.2fms (%5.1f%%) [%d calls, avg: %.3fms]',
                $section,
                $data['total_ms'],
                $data['pct'],
                $data['calls'],
                $data['avg_ms']
            );
        }

        $lines[] = sprintf("\nTotal elapsed: %.2fms", (microtime(true) - $this->startTime) * 1000);

        return implode("\n", $lines);
    }

    private function getFullKey(string $section): string
    {
        if (empty($this->stack)) {
            return $section;
        }

        return implode('.', array_slice($this->stack, 0, -1)).'.'.$section;
    }
}

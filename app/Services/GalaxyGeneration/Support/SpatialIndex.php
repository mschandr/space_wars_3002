<?php

namespace App\Services\GalaxyGeneration\Support;

/**
 * Spatial grid index for O(1) neighbor lookups.
 *
 * Divides 2D space into cells and groups items by cell.
 * Finding neighbors only requires checking adjacent cells.
 */
final class SpatialIndex
{
    private array $cells = [];

    private float $cellSize;

    public function __construct(float $cellSize)
    {
        $this->cellSize = $cellSize;
    }

    /**
     * Create index from items with x,y coordinates.
     *
     * @param  iterable  $items  Items with x,y properties or array keys
     */
    public static function build(iterable $items, float $cellSize): self
    {
        $index = new self($cellSize);

        foreach ($items as $item) {
            $index->add($item);
        }

        return $index;
    }

    /**
     * Add an item to the index.
     */
    public function add(mixed $item): void
    {
        $x = is_array($item) ? $item['x'] : $item->x;
        $y = is_array($item) ? $item['y'] : $item->y;

        $key = $this->cellKey($x, $y);

        if (! isset($this->cells[$key])) {
            $this->cells[$key] = [];
        }

        $this->cells[$key][] = $item;
    }

    /**
     * Find neighbors within maxDistance of a point.
     *
     * @param  float  $x  X coordinate
     * @param  float  $y  Y coordinate
     * @param  float  $maxDistance  Maximum distance
     * @param  mixed  $exclude  Item to exclude (by id comparison)
     * @return array Items within distance, sorted by distance
     */
    public function findNeighbors(float $x, float $y, float $maxDistance, mixed $exclude = null): array
    {
        $cellX = (int) floor($x / $this->cellSize);
        $cellY = (int) floor($y / $this->cellSize);

        // Calculate how many cells to check based on maxDistance
        $cellRadius = (int) ceil($maxDistance / $this->cellSize);

        $neighbors = [];
        $excludeId = $exclude ? (is_array($exclude) ? $exclude['id'] : $exclude->id) : null;

        // Check all cells in radius
        for ($dx = -$cellRadius; $dx <= $cellRadius; $dx++) {
            for ($dy = -$cellRadius; $dy <= $cellRadius; $dy++) {
                $key = ($cellX + $dx).','.($cellY + $dy);

                if (! isset($this->cells[$key])) {
                    continue;
                }

                foreach ($this->cells[$key] as $item) {
                    $itemId = is_array($item) ? $item['id'] : $item->id;

                    if ($excludeId !== null && $itemId === $excludeId) {
                        continue;
                    }

                    $itemX = is_array($item) ? $item['x'] : $item->x;
                    $itemY = is_array($item) ? $item['y'] : $item->y;

                    $distX = $itemX - $x;
                    $distY = $itemY - $y;
                    $distance = sqrt($distX * $distX + $distY * $distY);

                    if ($distance <= $maxDistance) {
                        $neighbors[] = [
                            'item' => $item,
                            'distance' => $distance,
                        ];
                    }
                }
            }
        }

        // Sort by distance
        usort($neighbors, fn ($a, $b) => $a['distance'] <=> $b['distance']);

        return $neighbors;
    }

    /**
     * Get all items in a cell.
     */
    public function getCell(int $cellX, int $cellY): array
    {
        return $this->cells["{$cellX},{$cellY}"] ?? [];
    }

    /**
     * Get cell key for coordinates.
     */
    private function cellKey(float $x, float $y): string
    {
        $cellX = (int) floor($x / $this->cellSize);
        $cellY = (int) floor($y / $this->cellSize);

        return "{$cellX},{$cellY}";
    }

    /**
     * Get total item count.
     */
    public function count(): int
    {
        return array_sum(array_map('count', $this->cells));
    }
}

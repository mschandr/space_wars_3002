<?php

namespace App\ValueObjects;

use App\Enums\Galaxy\GalaxyDistributionMethod;
use App\Enums\Galaxy\GalaxyRandomEngine;
use App\Enums\Galaxy\GalaxyStatus;
use Assert\Assertion;

class GalaxyConfig
{
    public function __construct(
        public int $width,
        public int $height,
        public int $seed,
        public GalaxyDistributionMethod $distributionMethod,
        public GalaxyRandomEngine $engine,
        public GalaxyStatus $status = GalaxyStatus::DRAFT,
        public int $turnLimit = 0,
        public ?string $description = null,
        public bool $isPublic = false,
        public array $config = [],
    ) {
        // Basic sanity checks
        Assertion::greaterThan($this->width, 0, 'Width must be positive.');
        Assertion::greaterThan($this->height, 0, 'Height must be positive.');
        Assertion::min($this->seed, 0, 'Seed must be non-negative.');

        Assertion::min($this->turnLimit, 0, 'Turn Limit must be positive.');
        Assertion::max($this->turnLimit, 1001, 'Turn Limit must be under or equal to 1000.');

        Assertion::boolean($this->isPublic, 'isPublic must be a boolean value.');

        Assertion::isArray($config, 'config must be an array.');
    }

    /**
     * Build a config from an array, validating required keys.
     */
    public static function fromArray(array $data): self
    {
        $required = ['width', 'height', 'seed', 'distribution_method', 'engine'];
        $missing = array_diff($required, array_keys($data));

        if (! empty($missing)) {
            throw new \InvalidArgumentException(
                'Missing required keys: '.implode(', ', $missing)
            );
        }

        $config = is_array($data['config'] ?? null)
            ? json_encode($data['config'])
            : ($data['config'] ?? json_encode([]));

        return new self(
            width: (int) $data['width'],
            height: (int) $data['height'],
            seed: (int) $data['seed'],
            distributionMethod: $data['distribution_method'] instanceof GalaxyDistributionMethod
                                ? $data['distribution_method']
                                : GalaxyDistributionMethod::from($data['distribution_method']),
            engine: $data['engine'] instanceof GalaxyRandomEngine
                                ? $data['engine']
                                : GalaxyRandomEngine::from($data['engine']),
            status: $data['status'] ?? GalaxyStatus::DRAFT,
            turnLimit: $data['turn_limit'] ?? 0,
            description: $data['description'] ?? null,
            isPublic: $data['is_public'] ?? false,
            config: $config,
        );
    }

    /**
     * Convert back to an array for mass assignment into models.
     */
    public function toArray(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'seed' => $this->seed,
            'distribution_method' => $this->distributionMethod,
            'engine' => $this->engine,
            'status' => $this->status,
            'turn_limit' => $this->turnLimit,
            'description' => $this->description,
            'is_public' => $this->isPublic,
            'config' => $this->config,
        ];
    }
}

<?php

namespace App\Services\GalaxyGeneration\Data;

use App\Enums\Galaxy\GalaxySizeTier;

/**
 * Configuration for galaxy generation.
 * Immutable DTO with all generation parameters.
 */
final class GenerationConfig
{
    private function __construct(
        public readonly GalaxySizeTier $tier,
        public readonly string $gameMode,
        public readonly ?string $name,
        public readonly ?int $ownerUserId,
        public readonly bool $includeMirror,
        public readonly bool $includePrecursor,
        public readonly int $npcCount,
        public readonly string $npcDifficulty,
    ) {}

    /**
     * Create config from size tier with defaults.
     */
    public static function fromTier(GalaxySizeTier $tier, array $options = []): self
    {
        $gameMode = $options['game_mode'] ?? 'multiplayer';

        return new self(
            tier: $tier,
            gameMode: $gameMode,
            name: $options['name'] ?? null,
            ownerUserId: $options['owner_user_id'] ?? null,
            includeMirror: ! ($options['skip_mirror'] ?? false) && config('game_config.mirror_universe.enabled', true),
            includePrecursor: ! ($options['skip_precursors'] ?? false),
            npcCount: $options['npc_count'] ?? 5,  // NPCs are always generated
            npcDifficulty: $options['npc_difficulty'] ?? 'medium',
        );
    }

    /**
     * Get galaxy dimensions.
     */
    public function getDimensions(): array
    {
        return [
            'width' => $this->tier->getOuterBounds(),
            'height' => $this->tier->getOuterBounds(),
        ];
    }

    /**
     * Get core region bounds.
     */
    public function getCoreBounds(): array
    {
        return $this->tier->getCoreBoundsArray();
    }

    /**
     * Get star counts.
     */
    public function getStarCounts(): array
    {
        return [
            'core' => $this->tier->getCoreStars(),
            'outer' => $this->tier->getOuterStars(),
            'total' => $this->tier->getTotalStars(),
        ];
    }

    /**
     * Get warp gate adjacency threshold.
     */
    public function getWarpGateAdjacency(): float
    {
        return (float) $this->tier->getWarpGateAdjacency();
    }

    /**
     * Should generate NPCs?
     * NPCs are generated in all game modes when npcCount > 0.
     */
    public function shouldGenerateNpcs(): bool
    {
        return $this->npcCount > 0;
    }

    /**
     * Get sector grid size based on tier.
     * Scales to maintain reasonable sectors per star ratio.
     */
    public function getGridSize(): int
    {
        return match ($this->tier) {
            GalaxySizeTier::SMALL => 5,    // 25 sectors for 250 stars
            GalaxySizeTier::MEDIUM => 10,  // 100 sectors for 750 stars
            GalaxySizeTier::LARGE => 15,   // 225 sectors for 1250 stars
            GalaxySizeTier::MASSIVE => 20, // 400 sectors for 2500 stars
        };
    }
}

<?php

namespace App\Services;

use App\Enums\Galaxy\GalaxyStatus;
use App\Enums\WarpGate\GateType;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\WarpGate;
use Exception;

class MirrorUniverseService
{
    /**
     * Create a mirror galaxy paired with a prime galaxy
     *
     * Uses the same seed to ensure identical POI structure
     */
    public function createMirrorGalaxy(Galaxy $primeGalaxy): Galaxy
    {
        // Check if mirror already exists
        $existingMirrorId = $primeGalaxy->config['mirror_galaxy_id'] ?? null;
        if ($existingMirrorId) {
            $existing = Galaxy::find($existingMirrorId);
            if ($existing) {
                return $existing;
            }
        }

        // Build mirror galaxy configuration
        $mirrorConfig = $primeGalaxy->config ?? [];
        $mirrorConfig['is_mirror'] = true;
        $mirrorConfig['prime_galaxy_id'] = $primeGalaxy->id;
        $mirrorConfig['mirror_modifiers'] = [
            'resource_multiplier' => config('game_config.mirror_universe.resource_multiplier', 2.0),
            'price_boost' => config('game_config.mirror_universe.price_boost', 1.5),
            'pirate_difficulty_boost' => config('game_config.mirror_universe.pirate_difficulty_boost', 2.0),
            'rare_mineral_spawn_rate' => config('game_config.mirror_universe.rare_mineral_spawn_rate', 3.0),
        ];

        // Create mirror galaxy with SAME SEED for identical structure
        $mirrorGalaxy = Galaxy::create([
            'name' => $primeGalaxy->name.' (Mirror)',
            'description' => 'Mirror Universe: '.$primeGalaxy->description,
            'width' => $primeGalaxy->width,
            'height' => $primeGalaxy->height,
            'seed' => $primeGalaxy->seed, // CRITICAL: Same seed = same structure
            'distribution_method' => $primeGalaxy->distribution_method,
            'spacing_factor' => $primeGalaxy->spacing_factor ?? 1.0,
            'engine' => $primeGalaxy->engine,
            'turn_limit' => $primeGalaxy->turn_limit,
            'status' => GalaxyStatus::DRAFT,
            'is_public' => false, // Mirror galaxies not directly accessible
            'config' => $mirrorConfig,
        ]);

        // Update prime galaxy to reference mirror
        $primeConfig = $primeGalaxy->config ?? [];
        $primeConfig['mirror_galaxy_id'] = $mirrorGalaxy->id;
        $primeGalaxy->config = $primeConfig;
        $primeGalaxy->save();

        return $mirrorGalaxy;
    }

    /**
     * Create a pair of mirror gates connecting prime and mirror galaxies
     *
     * @param  Galaxy  $primeGalaxy  The prime galaxy
     * @param  Galaxy  $mirrorGalaxy  The mirror galaxy
     * @param  PointOfInterest  $primePoi  Location in prime galaxy for the gate
     * @return array{entry_gate: WarpGate, return_gate: WarpGate}
     *
     * @throws Exception
     */
    public function createMirrorGatePair(
        Galaxy $primeGalaxy,
        Galaxy $mirrorGalaxy,
        PointOfInterest $primePoi
    ): array {
        // Select a random star from mirror galaxy for the return gate
        // Note: We don't require matching coordinates since the galaxies are structurally similar but not identical
        $mirrorPoi = $this->selectRandomGateLocation($mirrorGalaxy);

        if (! $mirrorPoi) {
            throw new Exception('Could not find suitable POI in mirror galaxy for gate placement');
        }

        // Create entry gate (prime -> mirror) - HIDDEN, requires sensors
        $entryGate = WarpGate::create([
            'galaxy_id' => $primeGalaxy->id,
            'source_poi_id' => $primePoi->id,
            'destination_poi_id' => $mirrorPoi->id,
            'gate_type' => GateType::MIRROR_ENTRY->value,
            'is_hidden' => true, // Ultra-rare discovery
            'distance' => 0, // Instant quantum transition
            'status' => 'active',
        ]);

        // Create return gate (mirror -> prime) - VISIBLE for escape
        $returnGate = WarpGate::create([
            'galaxy_id' => $mirrorGalaxy->id,
            'source_poi_id' => $mirrorPoi->id,
            'destination_poi_id' => $primePoi->id,
            'gate_type' => GateType::MIRROR_RETURN->value,
            'is_hidden' => false, // Always visible to enable escape
            'distance' => 0, // Instant transition
            'status' => 'active',
        ]);

        return [
            'entry_gate' => $entryGate,
            'return_gate' => $returnGate,
        ];
    }

    /**
     * Check if a player can traverse a mirror gate
     *
     * @return array{can_traverse: bool, reason?: string, cooldown_until?: \Carbon\Carbon}
     */
    public function canTraverseMirrorGate(Player $player, WarpGate $gate): array
    {
        $ship = $player->activeShip;

        if (! $ship) {
            return ['can_traverse' => false, 'reason' => 'No active ship'];
        }

        // Entry gate - check sensor level
        if ($gate->gate_type === GateType::MIRROR_ENTRY->value || $gate->gate_type === 'mirror_entry') {
            if (! $gate->canPlayerDetect($ship)) {
                $requiredLevel = config('game_config.mirror_universe.required_sensor_level', 5);

                return [
                    'can_traverse' => false,
                    'reason' => "Gate not detected - sensors level {$ship->sensors} insufficient (requires level {$requiredLevel})",
                ];
            }

            return ['can_traverse' => true];
        }

        // Return gate - check cooldown
        if ($gate->gate_type === GateType::MIRROR_RETURN->value || $gate->gate_type === 'mirror_return') {
            if (! $player->canReturnFromMirror()) {
                $remaining = $player->getMirrorCooldownRemaining();
                $cooldownHours = config('game_config.mirror_universe.return_cooldown_hours', 24);

                return [
                    'can_traverse' => false,
                    'reason' => "Return gate on cooldown for {$cooldownHours} hours",
                    'cooldown_until' => $remaining,
                ];
            }

            return ['can_traverse' => true];
        }

        return ['can_traverse' => false, 'reason' => 'Not a mirror gate'];
    }

    /**
     * Apply mirror resource modifiers to mineral distribution weights
     *
     * Boosts spawn rates for rare minerals in mirror universe
     */
    public function applyMirrorResourceModifiers(Galaxy $galaxy, array $mineralDistribution): array
    {
        if (! $galaxy->isMirrorUniverse()) {
            return $mineralDistribution;
        }

        $modifiers = $galaxy->getMirrorModifiers();
        $rareMultiplier = $modifiers['rare_mineral_spawn_rate'] ?? 3.0;

        // Boost spawn rates for rare minerals
        $rareTiers = ['rare', 'very_rare', 'epic', 'legendary', 'mythic', 'exotic'];

        foreach ($mineralDistribution as $rarity => $weight) {
            if (in_array(strtolower($rarity), $rareTiers)) {
                $mineralDistribution[$rarity] = (int) ($weight * $rareMultiplier);
            }
        }

        return $mineralDistribution;
    }

    /**
     * Select a random POI for mirror gate placement
     *
     * Chooses a star system at random from available POIs
     */
    public function selectRandomGateLocation(Galaxy $galaxy): ?PointOfInterest
    {
        // Get all star systems (primary POIs, not planets/moons)
        $stars = $galaxy->pointsOfInterest()
            ->where('type', \App\Enums\PointsOfInterest\PointOfInterestType::STAR)
            ->get();

        if ($stars->isEmpty()) {
            return null;
        }

        // Select random star
        return $stars->random();
    }

    /**
     * Check if a galaxy has a mirror universe
     */
    public function hasMirrorGalaxy(Galaxy $galaxy): bool
    {
        if ($galaxy->isMirrorUniverse()) {
            return false; // Mirrors don't have mirrors
        }

        $mirrorId = $galaxy->config['mirror_galaxy_id'] ?? null;

        return $mirrorId && Galaxy::find($mirrorId);
    }

    /**
     * Get the mirror gate for a galaxy (if it exists)
     */
    public function getMirrorGate(Galaxy $primeGalaxy): ?WarpGate
    {
        return WarpGate::where('galaxy_id', $primeGalaxy->id)
            ->where('gate_type', GateType::MIRROR_ENTRY->value)
            ->first();
    }
}

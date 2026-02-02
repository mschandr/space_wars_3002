<?php

namespace App\Services\GalaxyGeneration\Generators;

use App\Enums\Galaxy\GalaxyStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Enums\WarpGate\GateType;
use App\Models\Galaxy;
use App\Models\WarpGate;
use App\Services\GalaxyGeneration\Contracts\GeneratorInterface;
use App\Services\GalaxyGeneration\Data\GenerationConfig;
use App\Services\GalaxyGeneration\Data\GenerationMetrics;
use App\Services\GalaxyGeneration\Data\GenerationResult;
use App\Services\MirrorUniverseService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Generates a mirror universe paired with the prime galaxy.
 *
 * The mirror universe is a high-risk, high-reward parallel dimension with:
 * - 2x resource spawns
 * - 1.5x trading prices
 * - 3x rare mineral spawn rates
 * - 2x pirate difficulty
 *
 * This generator:
 * 1. Creates the mirror galaxy record with same seed
 * 2. Populates the mirror with POIs, sectors, gates, hubs
 * 3. Updates the precursor gate to link prime -> mirror
 * 4. Creates return gate in mirror -> prime
 */
final class MirrorUniverseGenerator implements GeneratorInterface
{
    private MirrorUniverseService $mirrorService;

    public function __construct()
    {
        $this->mirrorService = app(MirrorUniverseService::class);
    }

    public function getName(): string
    {
        return 'mirror_universe';
    }

    public function getDependencies(): array
    {
        return [
            StarFieldGenerator::class,
            SectorGridGenerator::class,
            WarpGateNetworkGenerator::class,
            TradingInfrastructureGenerator::class,
            PrecursorContentGenerator::class,
        ];
    }

    public function generate(Galaxy $galaxy, array $context = []): GenerationResult
    {
        $metrics = new GenerationMetrics;

        // Check if mirror creation is enabled via config
        /** @var GenerationConfig|null $config */
        $config = $context['config'] ?? null;
        if ($config && ! $config->includeMirror) {
            $metrics->setCustom('skipped', 'Mirror universe disabled via config');

            return GenerationResult::success($metrics, ['mirror_galaxy_id' => null]);
        }

        // Fallback check if no config (shouldn't happen, but be safe)
        if (! config('game_config.mirror_universe.enabled', true)) {
            $metrics->setCustom('skipped', 'Mirror universe disabled in game config');

            return GenerationResult::success($metrics, ['mirror_galaxy_id' => null]);
        }

        // Check if galaxy already has a mirror
        if ($this->mirrorService->hasMirrorGalaxy($galaxy)) {
            $existingMirror = $galaxy->getPairedGalaxy();
            $metrics->setCustom('skipped', 'Mirror already exists');

            return GenerationResult::success($metrics, [
                'mirror_galaxy_id' => $existingMirror?->id,
            ]);
        }

        try {
            // Step 1: Create mirror galaxy record
            Log::info('Creating mirror galaxy record');
            $mirrorGalaxy = $this->mirrorService->createMirrorGalaxy($galaxy);
            $metrics->setCount('mirror_galaxy_created', 1);

            // Step 2: Generate POIs in mirror (same count as prime)
            $starCount = $galaxy->pointsOfInterest()
                ->where('type', PointOfInterestType::STAR)
                ->count();

            Log::info("Generating {$starCount} stars in mirror galaxy");
            Artisan::call('galaxy:expand', [
                'galaxy' => $mirrorGalaxy->id,
                '--stars' => $starCount,
            ]);
            $metrics->setCount('mirror_stars', $starCount);

            // Step 3: Generate sectors
            Log::info('Generating sectors in mirror galaxy');
            Artisan::call('galaxy:generate-sectors', [
                'galaxy' => $mirrorGalaxy->id,
            ]);

            // Step 4: Designate inhabited systems
            $inhabitedPercentage = config('game_config.galaxy.inhabited_percentage', 0.40);
            Artisan::call('galaxy:designate-inhabited', [
                'galaxy' => $mirrorGalaxy->id,
                '--percentage' => $inhabitedPercentage,
            ]);

            // Step 5: Generate warp gates (denser network in mirror)
            Log::info('Generating warp gates in mirror galaxy');
            $adjacencyThreshold = max($mirrorGalaxy->width, $mirrorGalaxy->height) / 15;
            Artisan::call('galaxy:generate-gates', [
                'galaxy' => $mirrorGalaxy->id,
                '--adjacency' => $adjacencyThreshold,
                '--hidden-percentage' => 0.05,
                '--max-gates' => 8,
                '--regenerate' => true,
                '--incremental' => true,
            ]);

            // Step 6: Generate trading hubs
            Log::info('Generating trading hubs in mirror galaxy');
            Artisan::call('trading:generate-hubs', [
                'galaxy' => $mirrorGalaxy->id,
            ]);

            // Step 7: Distribute pirates with boosted difficulty
            Log::info('Distributing pirates in mirror galaxy');
            Artisan::call('galaxy:distribute-pirates', [
                'galaxy' => $mirrorGalaxy->id,
            ]);

            // Step 8: Link the precursor gate to mirror
            $this->linkPrecursorGate($galaxy, $mirrorGalaxy, $metrics);

            // Mark mirror galaxy as active
            $mirrorGalaxy->status = GalaxyStatus::ACTIVE;
            $mirrorGalaxy->generation_completed_at = now();
            $mirrorGalaxy->save();

            return GenerationResult::success($metrics, [
                'mirror_galaxy_id' => $mirrorGalaxy->id,
                'mirror_galaxy_uuid' => $mirrorGalaxy->uuid,
                'mirror_galaxy_name' => $mirrorGalaxy->name,
            ]);

        } catch (\Throwable $e) {
            Log::error('Mirror universe generation failed', [
                'galaxy_id' => $galaxy->id,
                'error' => $e->getMessage(),
            ]);

            return GenerationResult::failure($metrics, $e->getMessage());
        }
    }

    /**
     * Link the precursor gate (created by PrecursorContentGenerator) to the mirror galaxy.
     */
    private function linkPrecursorGate(Galaxy $primeGalaxy, Galaxy $mirrorGalaxy, GenerationMetrics $metrics): void
    {
        // Find the precursor gate (self-referencing placeholder)
        $precursorGate = WarpGate::where('galaxy_id', $primeGalaxy->id)
            ->where('gate_type', 'mirror_portal')
            ->where('status', 'precursor')
            ->first();

        if (! $precursorGate) {
            Log::warning('No precursor gate found to link to mirror');
            $metrics->setCount('precursor_gate_linked', 0);

            // Create new entry gate if none exists
            $primePoi = $this->mirrorService->selectRandomGateLocation($primeGalaxy);
            if ($primePoi) {
                $gates = $this->mirrorService->createMirrorGatePair($primeGalaxy, $mirrorGalaxy, $primePoi);
                $metrics->setCount('mirror_entry_gate_created', 1);
                $metrics->setCount('mirror_return_gate_created', 1);
            }

            return;
        }

        // Find a destination POI in the mirror galaxy
        $mirrorPoi = $this->mirrorService->selectRandomGateLocation($mirrorGalaxy);

        if (! $mirrorPoi) {
            Log::error('No suitable POI in mirror galaxy for gate destination');

            return;
        }

        // Update the precursor gate to point to mirror
        $precursorGate->update([
            'destination_poi_id' => $mirrorPoi->id,
            'dest_x' => $mirrorPoi->x,
            'dest_y' => $mirrorPoi->y,
            'status' => 'active',
            'gate_type' => GateType::MIRROR_ENTRY->value,
        ]);
        $metrics->setCount('precursor_gate_linked', 1);

        // Create return gate in mirror galaxy
        WarpGate::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'galaxy_id' => $mirrorGalaxy->id,
            'source_poi_id' => $mirrorPoi->id,
            'destination_poi_id' => $precursorGate->source_poi_id,
            'source_x' => $mirrorPoi->x,
            'source_y' => $mirrorPoi->y,
            'dest_x' => $precursorGate->source_x,
            'dest_y' => $precursorGate->source_y,
            'distance' => 0,
            'is_hidden' => false, // Return gate is always visible
            'status' => 'active',
            'gate_type' => GateType::MIRROR_RETURN->value,
        ]);
        $metrics->setCount('mirror_return_gate_created', 1);

        Log::info('Precursor gate linked to mirror universe', [
            'entry_gate_id' => $precursorGate->id,
            'mirror_poi_id' => $mirrorPoi->id,
        ]);
    }
}

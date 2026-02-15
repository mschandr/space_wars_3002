<?php

namespace App\Services\GalaxyGeneration\Generators;

use App\Enums\Galaxy\RegionType;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Services\GalaxyGeneration\Contracts\GeneratorInterface;
use App\Services\GalaxyGeneration\Data\GenerationMetrics;
use App\Services\GalaxyGeneration\Data\GenerationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates precursor content (mirror gate and precursor ship).
 *
 * - 1 Precursor Gate to Mirror Universe (hidden, requires sensor level 5)
 * - 1 Precursor Ship (derelict, discoverable)
 */
final class PrecursorContentGenerator implements GeneratorInterface
{
    public function getName(): string
    {
        return 'precursor_content';
    }

    public function getDependencies(): array
    {
        return [StarFieldGenerator::class, WarpGateNetworkGenerator::class];
    }

    public function generate(Galaxy $galaxy, array $context = []): GenerationResult
    {
        $metrics = new GenerationMetrics;

        // Place precursor gate to mirror universe
        $gateResult = $this->placePrecursorGate($galaxy);
        $metrics->setCount('precursor_gate_placed', $gateResult ? 1 : 0);

        // Place precursor ship
        $shipResult = $this->placePrecursorShip($galaxy);
        $metrics->setCount('precursor_ship_placed', $shipResult ? 1 : 0);

        return GenerationResult::success($metrics, [
            'precursor_gate_id' => $gateResult,
            'precursor_ship_id' => $shipResult,
        ]);
    }

    /**
     * Place a hidden precursor gate to the mirror universe.
     */
    private function placePrecursorGate(Galaxy $galaxy): ?int
    {
        // Select a random outer system for the precursor gate
        $outerStar = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('region', RegionType::OUTER)
            ->where('type', PointOfInterestType::STAR)
            ->inRandomOrder()
            ->first(['id', 'x', 'y', 'name']);

        if (! $outerStar) {
            return null;
        }

        // Create the precursor gate (self-referencing until mirror universe is generated)
        $gateId = DB::table('warp_gates')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'galaxy_id' => $galaxy->id,
            'source_poi_id' => $outerStar->id,
            'destination_poi_id' => $outerStar->id,  // Self-ref until mirror is created
            'source_x' => $outerStar->x,
            'source_y' => $outerStar->y,
            'dest_x' => $outerStar->x,
            'dest_y' => $outerStar->y,
            'distance' => 0,
            'is_hidden' => true,
            'status' => 'precursor',
            'gate_type' => 'mirror_portal',
            'activation_requirements' => json_encode([
                'type' => 'sensor_level',
                'value' => 5,
                'description' => 'Ancient precursor gate. Requires sensor level 5 to detect and activate.',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mark the star as having precursor content
        PointOfInterest::where('id', $outerStar->id)->update([
            'attributes' => DB::raw("JSON_SET(COALESCE(attributes, '{}'), '$.has_precursor_gate', true)"),
        ]);

        return $gateId;
    }

    /**
     * Place a derelict precursor ship in interstellar void, away from any stars or systems.
     */
    private function placePrecursorShip(Galaxy $galaxy): ?int
    {
        $minDistanceFromAnyPOI = 20;
        $maxAttempts = 100;

        // Load star system coordinates for distance checks
        // Inhabitance is a star system property, so we check against stars (not individual POIs)
        $starSystems = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('type', PointOfInterestType::STAR)
            ->select('x', 'y')
            ->get();

        // Galaxy bounds with 10% margin from edges
        $minX = (int) ($galaxy->width * 0.1);
        $maxX = (int) ($galaxy->width * 0.9);
        $minY = (int) ($galaxy->height * 0.1);
        $maxY = (int) ($galaxy->height * 0.9);

        $x = null;
        $y = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $candidateX = rand($minX, $maxX);
            $candidateY = rand($minY, $maxY);

            $tooClose = false;
            foreach ($starSystems as $system) {
                $distance = sqrt(
                    pow($candidateX - $system->x, 2) + pow($candidateY - $system->y, 2)
                );
                if ($distance < $minDistanceFromAnyPOI) {
                    $tooClose = true;
                    break;
                }
            }

            if (! $tooClose) {
                $x = $candidateX;
                $y = $candidateY;
                break;
            }
        }

        // Fallback: place at galaxy center if no isolated spot found
        if ($x === null) {
            $x = (int) ($galaxy->width / 2);
            $y = (int) ($galaxy->height / 2);
        }

        // Create a special POI in interstellar void (no parent star)
        $shipPoi = PointOfInterest::create([
            'uuid' => Str::uuid(),
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::DERELICT,
            'name' => 'Ancient Precursor Vessel',
            'x' => $x,
            'y' => $y,
            'is_hidden' => true,
            'is_inhabited' => false,
            'region' => RegionType::OUTER,
            'attributes' => [
                'precursor' => true,
                'discovery_sensor_level' => 4,
                'rewards' => [
                    'credits' => 1000000,
                    'technology' => 'precursor_drive',
                    'plans' => ['precursor_shield', 'precursor_weapon'],
                ],
                'lore' => 'A vessel from an ancient civilization that once ruled this galaxy.',
            ],
        ]);

        return $shipPoi->id;
    }
}

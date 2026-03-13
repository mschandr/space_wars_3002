<?php

namespace App\Services;

use App\Enums\Galaxy\GalaxySizeTier;
use App\Enums\Galaxy\GalaxyStatus;
use App\Enums\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\Sector;
use App\Models\User;
use App\Models\WarpGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * V2 Galaxy Generation Service
 *
 * Provides discrete, resumable phases for galaxy creation.
 * Each method is independently callable and idempotent.
 *
 * Phases:
 * 1. createGalaxyOnly() - Create empty galaxy with sectors
 * 2. createStars() - Generate core and outer stars
 * 3. createWarpLanes() - Generate warp gates with distance-based distribution
 * 4. createPlayer() - Create player and starting inventory
 * 5. createSupernodeForPlayer() - Create player's starting supernode with all services
 * 6. populateConnectedSystems() - Populate all remaining systems
 */
class GalaxyGenerationV2Service
{
    public function __construct(
        private CoreSystemGenerator $coreGenerator,
        private OuterSystemGenerator $outerGenerator,
    ) {}

    /**
     * Phase 1: Create empty galaxy record with sector grid.
     *
     * Default size is 'large' (admin can override).
     *
     * @param  GalaxySizeTier  $tier  Size tier (small, medium, large, massive)
     * @param  string  $name  Galaxy name
     * @return Galaxy
     */
    public function createGalaxyOnly(GalaxySizeTier $tier, string $name): Galaxy
    {
        return DB::transaction(function () use ($tier, $name) {
            Log::info("V2: Creating galaxy '{$name}' with size tier {$tier->value}");

            // Create galaxy record
            $galaxy = Galaxy::create([
                'name' => $name,
                'width' => $tier->getWidth(),
                'height' => $tier->getHeight(),
                'status' => GalaxyStatus::INITIALIZING,
                'is_public' => config('game_config.galaxy.is_public'),
                'game_mode' => 'single_player',
            ]);

            // Create sector grid
            $this->createSectors($galaxy);

            Log::info("V2: Galaxy created with {$tier->getWidth()}x{$tier->getHeight()} dimensions");

            return $galaxy->refresh();
        });
    }

    /**
     * Create sector grid (internal, called by createGalaxyOnly).
     *
     * @param  Galaxy  $galaxy
     * @return int Number of sectors created
     */
    private function createSectors(Galaxy $galaxy): int
    {
        $width = $galaxy->width;
        $height = $galaxy->height;
        $sectorSize = 30; // Fixed sector size in coordinates
        $cols = ceil($width / $sectorSize);
        $rows = ceil($height / $sectorSize);

        $sectors = [];
        for ($x = 0; $x < $cols; $x++) {
            for ($y = 0; $y < $rows; $y++) {
                $sectors[] = [
                    'galaxy_id' => $galaxy->id,
                    'grid_x' => $x,
                    'grid_y' => $y,
                    'min_x' => $x * $sectorSize,
                    'max_x' => ($x + 1) * $sectorSize,
                    'min_y' => $y * $sectorSize,
                    'max_y' => ($y + 1) * $sectorSize,
                ];
            }
        }

        Sector::insert($sectors);

        return count($sectors);
    }

    /**
     * Phase 2: Generate all stars (core + outer regions).
     *
     * @param  Galaxy  $galaxy
     * @return int Total POIs created
     */
    public function createStars(Galaxy $galaxy): int
    {
        return DB::transaction(function () use ($galaxy) {
            Log::info("V2: Generating stars for galaxy {$galaxy->id}");

            $tier = $galaxy->getSizeTier();

            // Generate core region
            $coreCount = $this->coreGenerator->generateCoreRegion(
                $galaxy,
                $tier->getCoreStars(),
                $tier->getCoreBoundsArray()
            );

            // Generate outer region
            $outerCount = $this->outerGenerator->generateOuterRegion(
                $galaxy,
                $tier->getOuterStars(),
                $tier->getCoreBoundsArray()
            );

            $total = $coreCount + $outerCount;
            Log::info("V2: Created {$total} stars ({$coreCount} core, {$outerCount} outer)");

            return $total;
        });
    }

    /**
     * Phase 3: Generate warp lanes with distance-based distribution.
     *
     * Gates decrease in count as you move away from the core:
     * - Core: up to 8 gates per system
     * - Middle: up to 5 gates per system
     * - Outer: up to 2 gates per system
     *
     * @param  Galaxy  $galaxy
     * @return int Total gates created
     */
    public function createWarpLanes(Galaxy $galaxy): int
    {
        return DB::transaction(function () use ($galaxy) {
            Log::info("V2: Generating warp lanes for galaxy {$galaxy->id}");

            $gateConfig = config('game_config.gate_distribution');
            $totalGates = 0;

            // Get all inhabited POIs sorted by distance from core
            $inhabited = $galaxy->pointsOfInterest()
                ->where('inhabited', true)
                ->get()
                ->sortBy(fn ($poi) => $this->distanceFromCore($galaxy, $poi));

            foreach ($inhabited as $poi) {
                $band = $this->getDistanceBand($galaxy, $poi);
                $bandConfig = $gateConfig[$band];

                // Determine gate count for this system
                if ($gateConfig['random_gates']['enabled']) {
                    $gateCount = rand(
                        $bandConfig['min_gates'],
                        $bandConfig['max_gates']
                    );
                } else {
                    $gateCount = $bandConfig['max_gates'];
                }

                // Skip low probability systems
                if (rand(1, 100) / 100 > $bandConfig['inhabited_probability']) {
                    continue;
                }

                // Find nearest inhabited systems to connect
                $nearby = $this->findNearbyInhabited($galaxy, $poi, $gateCount * 2);

                for ($i = 0; $i < min($gateCount, count($nearby)); $i++) {
                    $targetPoi = $nearby[$i];

                    // Check for hidden gate
                    $isHidden = rand(1, 100) / 100 <= $gateConfig['random_gates']['hidden_gate_chance'];
                    $isDead = $isHidden && rand(1, 100) / 100 <= $gateConfig['random_gates']['dead_end_chance'];

                    WarpGate::firstOrCreate(
                        [
                            'from_poi_id' => $poi->id,
                            'to_poi_id' => $targetPoi->id,
                        ],
                        [
                            'core' => $band === 'core_region',
                            'dormant' => $band === 'outer_region',
                            'hidden' => $isHidden,
                            'dead_end' => $isDead,
                        ]
                    );

                    $totalGates++;
                }
            }

            Log::info("V2: Created {$totalGates} warp gates");

            return $totalGates;
        });
    }

    /**
     * Phase 4: Create player in galaxy.
     *
     * @param  Galaxy  $galaxy
     * @param  User  $user
     * @return Player
     */
    public function createPlayer(Galaxy $galaxy, User $user): Player
    {
        return DB::transaction(function () use ($galaxy, $user) {
            Log::info("V2: Creating player {$user->id} in galaxy {$galaxy->id}");

            $player = Player::create([
                'user_id' => $user->id,
                'galaxy_id' => $galaxy->id,
                'credits' => config('game_config.ships.starting_credits'),
                'experience' => 0,
            ]);

            Log::info("V2: Player created with {$player->credits} starting credits");

            return $player;
        });
    }

    /**
     * Phase 5: Create supernode for player (starting location with all services).
     *
     * Attempts to find a location matching supernode requirements, falling back
     * to progressively less restrictive criteria.
     *
     * @param  Player  $player
     * @return PointOfInterest The supernode POI
     */
    public function createSupernodeForPlayer(Player $player): PointOfInterest
    {
        return DB::transaction(function () use ($player) {
            $galaxy = $player->galaxy;
            $supernodeConfig = config('game_config.supernode');
            $fallbackChain = $supernodeConfig['fallback_chain'];

            Log::info("V2: Finding supernode for player {$player->id}");

            // Try each fallback level
            foreach ($fallbackChain as $level) {
                $supernode = $this->findSupernodeByLevel($galaxy, $level);
                if ($supernode) {
                    $player->update(['current_poi_id' => $supernode->id]);
                    Log::info("V2: Player supernode created at {$supernode->name} (level: {$level})");
                    return $supernode;
                }
            }

            // If no supernode found, create one synthetically
            $supernode = $this->synthesizeSupernodeInCore($galaxy);
            $player->update(['current_poi_id' => $supernode->id]);

            Log::info("V2: Player supernode synthesized at {$supernode->name}");

            return $supernode;
        });
    }

    /**
     * Phase 6: Populate remaining systems (planets, minerals, defenses, etc).
     *
     * @param  Galaxy  $galaxy
     * @return int Number of systems populated
     */
    public function populateConnectedSystems(Galaxy $galaxy): int
    {
        return DB::transaction(function () use ($galaxy) {
            Log::info("V2: Populating connected systems in galaxy {$galaxy->id}");

            // Generate planetary systems for outer stars
            $outerPois = $galaxy->outerPointsOfInterest()->get();
            $this->outerGenerator->generatePlanetarySystems($outerPois);

            // Populate mineral deposits
            $this->outerGenerator->populateMineralDeposits($outerPois);

            // Deploy fortress defenses
            $corePois = $galaxy->corePointsOfInterest()->where('inhabited', true)->get();
            $this->coreGenerator->deployFortressDefenses($corePois);

            // Create trading posts
            $this->coreGenerator->createTradingPosts($corePois);

            Log::info("V2: Connected systems populated ({$outerPois->count()} outer systems)");

            return $outerPois->count();
        });
    }

    /**
     * Helper: Calculate distance from galaxy core.
     *
     * @param  Galaxy  $galaxy
     * @param  PointOfInterest  $poi
     * @return float
     */
    private function distanceFromCore(Galaxy $galaxy, PointOfInterest $poi): float
    {
        $centerX = $galaxy->width / 2;
        $centerY = $galaxy->height / 2;

        return sqrt(
            pow($poi->coordinate_x - $centerX, 2) +
            pow($poi->coordinate_y - $centerY, 2)
        );
    }

    /**
     * Helper: Determine distance band (core/middle/outer).
     *
     * @param  Galaxy  $galaxy
     * @param  PointOfInterest  $poi
     * @return string 'core_region', 'middle_region', or 'outer_region'
     */
    private function getDistanceBand(Galaxy $galaxy, PointOfInterest $poi): string
    {
        $distance = $this->distanceFromCore($galaxy, $poi);
        $coreBounds = $galaxy->core_bounds ?? ($galaxy->width / 2);

        if ($distance <= $coreBounds) {
            return 'core_region';
        }

        $midBounds = ($galaxy->width / 2) + ($galaxy->width / 4);
        if ($distance <= $midBounds) {
            return 'middle_region';
        }

        return 'outer_region';
    }

    /**
     * Helper: Find nearby inhabited POIs for gate connections.
     *
     * @param  Galaxy  $galaxy
     * @param  PointOfInterest  $poi
     * @param  int  $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function findNearbyInhabited(Galaxy $galaxy, PointOfInterest $poi, int $limit)
    {
        $threshold = config('game_config.galaxy.width') / 15; // From existing logic

        return $galaxy->pointsOfInterest()
            ->where('inhabited', true)
            ->where('id', '!=', $poi->id)
            ->selectRaw("*, SQRT(POW(coordinate_x - {$poi->coordinate_x}, 2) + POW(coordinate_y - {$poi->coordinate_y}, 2)) as distance")
            ->having('distance', '<=', $threshold)
            ->orderBy('distance')
            ->limit($limit)
            ->get();
    }

    /**
     * Helper: Find supernode matching specific fallback level.
     *
     * @param  Galaxy  $galaxy
     * @param  string  $level
     * @return PointOfInterest|null
     */
    private function findSupernodeByLevel(Galaxy $galaxy, string $level): ?PointOfInterest
    {
        $requirements = config("game_config.supernode.fallback_definitions.{$level}");

        $query = $galaxy->corePointsOfInterest()
            ->where('inhabited', true)
            ->whereIn('poi_type', ['star']);

        // Filter by star size
        if (isset($requirements['star_size'])) {
            $query->whereIn('stellar_class', $requirements['star_size']);
        }

        // For now, return first match (would need more sophisticated service checking)
        return $query->first();
    }

    /**
     * Helper: Synthesize a supernode in the core region.
     *
     * Creates a synthetic supernode if natural candidates don't exist.
     *
     * @param  Galaxy  $galaxy
     * @return PointOfInterest
     */
    private function synthesizeSupernodeInCore(Galaxy $galaxy): PointOfInterest
    {
        // Find or create a large star in core
        $coreBounds = $galaxy->core_bounds ?? ($galaxy->width / 2);
        $centerX = $galaxy->width / 2;
        $centerY = $galaxy->height / 2;

        $supernode = $galaxy->pointsOfInterest()
            ->where('poi_type', 'star')
            ->where('inhabited', true)
            ->whereRaw("SQRT(POW(coordinate_x - {$centerX}, 2) + POW(coordinate_y - {$centerY}, 2)) <= {$coreBounds}")
            ->orderBy('name')
            ->first();

        if (!$supernode) {
            // Create synthetic supernode at core center
            $supernode = PointOfInterest::create([
                'galaxy_id' => $galaxy->id,
                'name' => $galaxy->name . ' Prime',
                'poi_type' => PointOfInterestType::STAR->value,
                'coordinate_x' => $centerX,
                'coordinate_y' => $centerY,
                'inhabited' => true,
                'charted' => true,
                'stellar_class' => 'G',
            ]);
        }

        return $supernode;
    }
}

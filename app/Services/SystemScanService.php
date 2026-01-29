<?php

namespace App\Services;

use App\Enums\Exploration\ScanLevel;
use App\Enums\Galaxy\RegionType;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\SystemScan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * System Scan Service
 *
 * Handles progressive system scanning based on ship sensor level.
 * Higher sensor levels reveal more detailed information about a system.
 */
class SystemScanService
{
    /**
     * Request-scoped cache for player scans.
     *
     * @var array<int, array<int, SystemScan>>
     */
    protected array $scanCache = [];

    /**
     * Scan a system at the player's current sensor level.
     * Auto-triggered on arrival or manually by the player.
     *
     * @param  Player  $player  The player performing the scan
     * @param  PointOfInterest  $poi  The system to scan
     * @param  bool  $forceScan  Force a re-scan even if already scanned at this level
     * @return array Scan result with data and metadata
     */
    public function scanSystem(Player $player, PointOfInterest $poi, bool $forceScan = false): array
    {
        $ship = $player->activeShip;
        if (! $ship) {
            return [
                'success' => false,
                'message' => 'No active ship',
                'scan_level' => 0,
            ];
        }

        // Get effective sensor level (precursor ships see everything)
        $sensorLevel = $this->getEffectiveSensorLevel($ship);
        $scanLevel = ScanLevel::fromSensorLevel($sensorLevel);

        // Check existing scan
        $existingScan = $this->getScan($player, $poi);

        // If already scanned at this or higher level, return cached data unless forced
        if ($existingScan && ! $forceScan && $existingScan->scan_level >= $scanLevel->value) {
            return [
                'success' => true,
                'message' => 'Using cached scan data',
                'scan_level' => $existingScan->scan_level,
                'scan_data' => $existingScan->getAllData(),
                'cached' => true,
                'can_reveal_more' => $existingScan->canRevealMore(),
                'next_level_reveals' => $existingScan->getNextLevelReveals(),
            ];
        }

        // Generate scan data for new levels only
        $startLevel = $existingScan ? $existingScan->scan_level + 1 : 1;
        $newScanData = $this->generateScanData($poi, $startLevel, $scanLevel->value);

        // Update or create scan record
        if ($existingScan) {
            $existingData = $existingScan->scan_data ?? [];
            $mergedData = array_merge($existingData, $newScanData);

            $existingScan->update([
                'scan_level' => $scanLevel->value,
                'scan_data' => $mergedData,
                'scanned_at' => now(),
            ]);
            $scan = $existingScan->fresh();
        } else {
            $scan = SystemScan::create([
                'player_id' => $player->id,
                'poi_id' => $poi->id,
                'scan_level' => $scanLevel->value,
                'scan_data' => $newScanData,
                'scanned_at' => now(),
            ]);
        }

        // Clear cache
        $this->clearCache($player->id);

        return [
            'success' => true,
            'message' => $existingScan ? 'Scan upgraded' : 'System scanned',
            'scan_level' => $scan->scan_level,
            'scan_data' => $scan->getAllData(),
            'cached' => false,
            'can_reveal_more' => $scan->canRevealMore(),
            'next_level_reveals' => $scan->getNextLevelReveals(),
            'new_discoveries' => array_keys($newScanData),
        ];
    }

    /**
     * Get scan results for a POI, including baseline data for unscanned systems.
     *
     * @param  Player  $player  The player
     * @param  PointOfInterest  $poi  The system
     * @return array Scan data with metadata
     */
    public function getScanResults(Player $player, PointOfInterest $poi): array
    {
        $existingScan = $this->getScan($player, $poi);

        if ($existingScan) {
            return [
                'scan_level' => $existingScan->scan_level,
                'scan_data' => $existingScan->getAllData(),
                'scanned_at' => $existingScan->scanned_at,
                'can_reveal_more' => $existingScan->canRevealMore(),
                'next_level_reveals' => $existingScan->getNextLevelReveals(),
                'display' => [
                    'color' => $existingScan->getColor(),
                    'opacity' => $existingScan->getOpacity(),
                    'label' => $existingScan->getScanLevelEnum()->label(),
                ],
            ];
        }

        // Return baseline data based on region
        $baselineLevel = $this->getBaselineScanLevel($poi);
        if ($baselineLevel > 0) {
            $baselineData = $this->generateScanData($poi, 1, $baselineLevel);
            $scanLevelEnum = ScanLevel::fromSensorLevel($baselineLevel);

            return [
                'scan_level' => $baselineLevel,
                'scan_data' => $this->flattenScanData($baselineData),
                'scanned_at' => null,
                'baseline' => true,
                'can_reveal_more' => true,
                'next_level_reveals' => $scanLevelEnum->next()?->reveals(),
                'display' => [
                    'color' => $scanLevelEnum->color(),
                    'opacity' => $scanLevelEnum->opacity(),
                    'label' => 'Baseline Intel',
                ],
            ];
        }

        // Completely unscanned
        return [
            'scan_level' => 0,
            'scan_data' => [],
            'scanned_at' => null,
            'can_reveal_more' => true,
            'next_level_reveals' => ScanLevel::GEOGRAPHY->reveals(),
            'display' => [
                'color' => ScanLevel::UNSCANNED->color(),
                'opacity' => ScanLevel::UNSCANNED->opacity(),
                'label' => ScanLevel::UNSCANNED->label(),
            ],
        ];
    }

    /**
     * Get filtered system data based on scan level.
     * Used for API responses to filter what a player can see.
     *
     * @param  PointOfInterest  $poi  The system
     * @param  int  $scanLevel  The achieved scan level
     * @return array Filtered system data
     */
    public function getFilteredSystemData(PointOfInterest $poi, int $scanLevel): array
    {
        $scanLevelEnum = ScanLevel::fromSensorLevel($scanLevel);
        $revealedCategories = $scanLevelEnum->allRevealedCategories();

        $data = [
            'uuid' => $poi->uuid,
            'name' => $poi->name,
            'scan_level' => $scanLevel,
        ];

        // Always show basic coordinates
        $data['coordinates'] = ['x' => $poi->x, 'y' => $poi->y];

        // Level 1: Geography
        if (in_array('geography', $revealedCategories)) {
            $data['geography'] = $this->generateGeographyData($poi);
        }

        // Level 2: Gates
        if (in_array('gates_presence', $revealedCategories)) {
            $data['gates'] = $this->generateGateData($poi, $scanLevel);
        }

        // Level 3: Basic resources
        if (in_array('minerals_basic', $revealedCategories)) {
            $data['resources'] = $this->generateBasicResourceData($poi);
        }

        // Level 4: Rare resources
        if (in_array('minerals_rare', $revealedCategories)) {
            $data['rare_resources'] = $this->generateRareResourceData($poi);
        }

        // Level 5: Hidden features
        if (in_array('hidden_moons', $revealedCategories)) {
            $data['hidden_features'] = $this->generateHiddenFeatureData($poi);
        }

        // Level 6: Anomalies
        if (in_array('anomalies', $revealedCategories)) {
            $data['anomalies'] = $this->generateAnomalyData($poi);
        }

        // Level 7: Deep scan
        if (in_array('deep_scan', $revealedCategories)) {
            $data['deep_scan'] = $this->generateDeepScanData($poi);
        }

        // Level 8: Intel
        if (in_array('intel', $revealedCategories)) {
            $data['intel'] = $this->generateIntelData($poi);
        }

        // Level 9: Precursor secrets
        if (in_array('precursor_gates', $revealedCategories)) {
            $data['precursor'] = $this->generatePrecursorData($poi);
        }

        return $data;
    }

    /**
     * Check if a feature can be revealed at a given scan level.
     *
     * @param  string  $featureType  The feature type to check
     * @param  int  $scanLevel  The scan level
     * @return bool True if the feature can be revealed
     */
    public function canRevealFeature(string $featureType, int $scanLevel): bool
    {
        return ScanLevel::fromSensorLevel($scanLevel)->canReveal($featureType);
    }

    /**
     * Generate scan data for a system from startLevel to endLevel.
     *
     * @param  PointOfInterest  $poi  The system to scan
     * @param  int  $startLevel  Starting scan level
     * @param  int  $endLevel  Ending scan level
     * @return array Scan data organized by level
     */
    public function generateScanData(PointOfInterest $poi, int $startLevel, int $endLevel): array
    {
        $scanData = [];

        for ($level = $startLevel; $level <= $endLevel; $level++) {
            $scanData[(string) $level] = match ($level) {
                1 => $this->generateGeographyData($poi),
                2 => $this->generateGateData($poi, $level),
                3 => $this->generateBasicResourceData($poi),
                4 => $this->generateRareResourceData($poi),
                5 => $this->generateHiddenFeatureData($poi),
                6 => $this->generateAnomalyData($poi),
                7 => $this->generateDeepScanData($poi),
                8 => $this->generateIntelData($poi),
                9 => $this->generatePrecursorData($poi),
                default => [],
            };
        }

        return $scanData;
    }

    /**
     * Get all scanned systems for a player.
     *
     * @param  Player  $player  The player
     * @return Collection Collection of SystemScan models
     */
    public function getPlayerScannedSystems(Player $player): Collection
    {
        return SystemScan::where('player_id', $player->id)
            ->with('pointOfInterest')
            ->orderBy('scanned_at', 'desc')
            ->get();
    }

    /**
     * Get scan level for a specific POI.
     *
     * @param  Player  $player  The player
     * @param  PointOfInterest  $poi  The POI
     * @return int The scan level (0 if unscanned)
     */
    public function getScanLevelFor(Player $player, PointOfInterest $poi): int
    {
        $scan = $this->getScan($player, $poi);

        return $scan ? $scan->scan_level : $this->getBaselineScanLevel($poi);
    }

    /**
     * Bulk get scan levels for multiple POIs.
     *
     * @param  Player  $player  The player
     * @param  array  $poiIds  Array of POI IDs
     * @return array<int, int> Array of POI ID => scan level
     */
    public function getBulkScanLevels(Player $player, array $poiIds): array
    {
        $scans = SystemScan::where('player_id', $player->id)
            ->whereIn('poi_id', $poiIds)
            ->get()
            ->keyBy('poi_id');

        $levels = [];
        foreach ($poiIds as $poiId) {
            if (isset($scans[$poiId])) {
                $levels[$poiId] = $scans[$poiId]->scan_level;
            } else {
                // Get baseline for unscanned POIs
                $poi = PointOfInterest::find($poiId);
                $levels[$poiId] = $poi ? $this->getBaselineScanLevel($poi) : 0;
            }
        }

        return $levels;
    }

    /**
     * Get the baseline scan level for a POI based on region.
     *
     * @param  PointOfInterest  $poi  The POI
     * @return int Baseline scan level
     */
    public function getBaselineScanLevel(PointOfInterest $poi): int
    {
        // Inhabited systems have shared intel
        if ($poi->is_inhabited) {
            return config('game_config.scanning.inhabited_baseline_level', 2);
        }

        // Core region is well-documented
        if ($poi->region === RegionType::CORE) {
            return config('game_config.scanning.core_baseline_level', 3);
        }

        // Outer region is fog
        return config('game_config.scanning.outer_baseline_level', 0);
    }

    /**
     * Get effective sensor level for a ship.
     *
     * @param  \App\Models\PlayerShip  $ship  The ship
     * @return int Effective sensor level
     */
    protected function getEffectiveSensorLevel($ship): int
    {
        // Check for precursor ship (has all-seeing sensors)
        if ($ship->is_precursor ?? false) {
            return ScanLevel::PRECURSOR_LEVEL;
        }

        return $ship->sensors ?? 1;
    }

    /**
     * Get a cached scan or fetch from DB.
     */
    protected function getScan(Player $player, PointOfInterest $poi): ?SystemScan
    {
        $playerId = $player->id;
        $poiId = $poi->id;

        if (! isset($this->scanCache[$playerId])) {
            $this->scanCache[$playerId] = [];
        }

        if (! isset($this->scanCache[$playerId][$poiId])) {
            $this->scanCache[$playerId][$poiId] = SystemScan::where('player_id', $playerId)
                ->where('poi_id', $poiId)
                ->first();
        }

        return $this->scanCache[$playerId][$poiId];
    }

    /**
     * Clear the scan cache for a player.
     */
    protected function clearCache(int $playerId): void
    {
        unset($this->scanCache[$playerId]);
    }

    /**
     * Flatten multi-level scan data into a single array.
     */
    protected function flattenScanData(array $scanData): array
    {
        $flattened = [];
        foreach ($scanData as $level => $data) {
            $flattened = array_merge_recursive($flattened, $data);
        }

        return $flattened;
    }

    /**
     * Generate Level 1: Geography data.
     */
    protected function generateGeographyData(PointOfInterest $poi): array
    {
        // Load children (planets, moons, belts)
        $children = $poi->children()->get();

        $planets = $children->filter(fn ($c) => $c->type?->isSystemType());
        $asteroidBelts = $children->filter(fn ($c) => $c->type === PointOfInterestType::ASTEROID_BELT);
        $dwarfPlanets = $children->filter(fn ($c) => $c->type === PointOfInterestType::DWARF_PLANET);

        // Categorize planets by type
        $planetTypes = [
            'rocky' => 0,
            'gas' => 0,
            'ice' => 0,
            'other' => 0,
        ];

        foreach ($planets as $planet) {
            match ($planet->type) {
                PointOfInterestType::TERRESTRIAL,
                PointOfInterestType::SUPER_EARTH,
                PointOfInterestType::LAVA,
                PointOfInterestType::CHTHONIC => $planetTypes['rocky']++,
                PointOfInterestType::GAS_GIANT,
                PointOfInterestType::HOT_JUPITER => $planetTypes['gas']++,
                PointOfInterestType::ICE_GIANT,
                PointOfInterestType::OCEAN => $planetTypes['ice']++,
                default => $planetTypes['other']++,
            };
        }

        // Determine habitability
        $habitabilityNotes = [];
        $habitablePlanets = $children->filter(function ($c) {
            $attrs = $c->attributes ?? [];

            return ($attrs['habitable'] ?? false) ||
                   ($attrs['in_goldilocks_zone'] ?? false);
        });

        if ($habitablePlanets->isNotEmpty()) {
            $habitabilityNotes[] = $habitablePlanets->count().' planet(s) in habitable zone';
            foreach ($habitablePlanets as $hp) {
                $temp = $hp->attributes['temperature'] ?? 'temperate';
                $habitabilityNotes[] = $hp->name.": $temp";
            }
        }

        return [
            'star_type' => $this->getStarType($poi),
            'planet_count' => $planets->count(),
            'planet_types' => $planetTypes,
            'dwarf_planets' => $dwarfPlanets->count(),
            'asteroid_belts' => $asteroidBelts->count(),
            'habitability' => [
                'goldilocks_planets' => $habitablePlanets->count(),
                'notes' => $habitabilityNotes,
            ],
        ];
    }

    /**
     * Generate Level 2: Gate data.
     */
    protected function generateGateData(PointOfInterest $poi, int $scanLevel): array
    {
        $outgoingGates = $poi->outgoingGates()
            ->where('is_hidden', false)
            ->get();

        $dormantGates = $poi->outgoingGates()
            ->where('status', 'dormant')
            ->where('is_hidden', false)
            ->count();

        $gates = [];
        foreach ($outgoingGates as $gate) {
            $gateInfo = [
                'status' => $gate->status,
                'destination' => $gate->status === 'active' ? 'known' : 'unknown',
            ];

            if ($gate->status === 'dormant' && $gate->activation_requirements) {
                $gateInfo['activation_hint'] = $gate->getActivationDescription();
            }

            $gates[] = $gateInfo;
        }

        return [
            'gate_count' => $outgoingGates->count(),
            'active_gates' => $outgoingGates->where('status', 'active')->count(),
            'dormant_gates' => $dormantGates,
            'gates' => $gates,
        ];
    }

    /**
     * Generate Level 3: Basic resource data.
     */
    protected function generateBasicResourceData(PointOfInterest $poi): array
    {
        $children = $poi->children()->get();
        $resources = [
            'rocky_planets' => [],
            'gas_giants' => [],
        ];

        foreach ($children as $child) {
            $deposits = $child->attributes['mineral_deposits'] ?? [];
            $mineralDeposits = $child->mineral_deposits ?? [];
            $allDeposits = array_merge($deposits, $mineralDeposits);

            // Filter to common minerals only
            $commonMinerals = ['iron', 'copper', 'titanium', 'nickel', 'cobalt'];
            $filtered = array_filter($allDeposits, fn ($m) => in_array(strtolower($m), $commonMinerals));

            if (empty($filtered)) {
                continue;
            }

            $typeGroup = match ($child->type) {
                PointOfInterestType::TERRESTRIAL,
                PointOfInterestType::SUPER_EARTH,
                PointOfInterestType::LAVA => 'rocky_planets',
                PointOfInterestType::GAS_GIANT,
                PointOfInterestType::HOT_JUPITER => 'gas_giants',
                default => null,
            };

            if ($typeGroup) {
                $resources[$typeGroup] = array_merge($resources[$typeGroup], $filtered);
            }
        }

        // Add gas giant resources
        $gasGiants = $children->filter(fn ($c) => in_array($c->type, [
            PointOfInterestType::GAS_GIANT,
            PointOfInterestType::HOT_JUPITER,
        ]));

        if ($gasGiants->isNotEmpty()) {
            $resources['gas_giants'] = array_unique(array_merge(
                $resources['gas_giants'],
                ['metallic_hydrogen', 'helium-3']
            ));
        }

        return $resources;
    }

    /**
     * Generate Level 4: Rare resource data.
     */
    protected function generateRareResourceData(PointOfInterest $poi): array
    {
        $children = $poi->children()->get();
        $resources = [
            'asteroid_minerals' => [],
            'rare_deposits' => [],
        ];

        // Check asteroid belts
        $asteroidBelts = $children->filter(fn ($c) => $c->type === PointOfInterestType::ASTEROID_BELT);
        foreach ($asteroidBelts as $belt) {
            $deposits = $belt->attributes['mineral_deposits'] ?? [];
            $mineralDeposits = $belt->mineral_deposits ?? [];
            $resources['asteroid_minerals'] = array_merge(
                $resources['asteroid_minerals'],
                $deposits,
                $mineralDeposits
            );
        }

        // Check for rare minerals
        $rareMinerals = ['platinum', 'iridium', 'palladium', 'uranium', 'exotic_matter', 'quantium'];
        foreach ($children as $child) {
            $deposits = $child->attributes['mineral_deposits'] ?? [];
            $mineralDeposits = $child->mineral_deposits ?? [];
            $allDeposits = array_merge($deposits, $mineralDeposits);

            $rareFound = array_filter($allDeposits, fn ($m) => in_array(strtolower($m), $rareMinerals));
            if (! empty($rareFound)) {
                $resources['rare_deposits'][] = [
                    'location' => $child->name,
                    'minerals' => array_values($rareFound),
                ];
            }
        }

        $resources['asteroid_minerals'] = array_unique($resources['asteroid_minerals']);

        return $resources;
    }

    /**
     * Generate Level 5: Hidden feature data.
     */
    protected function generateHiddenFeatureData(PointOfInterest $poi): array
    {
        $children = $poi->children()->get();
        $features = [
            'habitable_moons' => [],
            'orbital_mining' => [],
            'ring_deposits' => [],
        ];

        // Check moons
        foreach ($children as $child) {
            if ($child->type === PointOfInterestType::MOON) {
                $attrs = $child->attributes ?? [];
                if ($attrs['habitable'] ?? false) {
                    $features['habitable_moons'][] = [
                        'name' => $child->name,
                        'parent' => $child->parent?->name ?? 'Unknown',
                        'climate' => $attrs['climate'] ?? 'temperate',
                    ];
                }
            }

            // Check for ring systems with deposits
            $hasRings = ($child->attributes['has_rings'] ?? false);
            if ($hasRings) {
                $ringDeposits = $child->attributes['ring_deposits'] ?? ['ice', 'dust'];
                $features['ring_deposits'][] = [
                    'planet' => $child->name,
                    'deposits' => $ringDeposits,
                ];
            }
        }

        // Check for orbital mining opportunities
        $asteroidBelts = $children->filter(fn ($c) => $c->type === PointOfInterestType::ASTEROID_BELT);
        foreach ($asteroidBelts as $belt) {
            $richness = $belt->attributes['mineral_richness'] ?? 'moderate';
            if (in_array($richness, ['rich', 'exceptional'])) {
                $features['orbital_mining'][] = [
                    'location' => $belt->name,
                    'richness' => $richness,
                ];
            }
        }

        return $features;
    }

    /**
     * Generate Level 6: Anomaly data.
     */
    protected function generateAnomalyData(PointOfInterest $poi): array
    {
        $children = $poi->children()->get();
        $anomalies = [
            'ruins' => [],
            'spatial_anomalies' => [],
            'derelicts' => [],
        ];

        foreach ($children as $child) {
            $attrs = $child->attributes ?? [];

            // Check for ruins
            if ($attrs['has_ruins'] ?? false) {
                $anomalies['ruins'][] = [
                    'location' => $child->name,
                    'type' => $attrs['ruin_type'] ?? 'ancient',
                    'age_estimate' => $attrs['ruin_age'] ?? 'unknown',
                ];
            }

            // Check for anomalies
            if ($child->type === PointOfInterestType::ANOMALY) {
                $anomalies['spatial_anomalies'][] = [
                    'name' => $child->name,
                    'type' => $attrs['anomaly_type'] ?? 'unknown',
                    'danger_level' => $attrs['danger_level'] ?? 'unknown',
                ];
            }

            // Check for derelicts
            if ($child->type === PointOfInterestType::DERELICT) {
                $anomalies['derelicts'][] = [
                    'name' => $child->name,
                    'ship_class' => $attrs['ship_class'] ?? 'unknown',
                    'salvageable' => $attrs['salvageable'] ?? true,
                ];
            }
        }

        // Check POI itself for anomalies
        $poiAttrs = $poi->attributes ?? [];
        if ($poiAttrs['has_anomaly'] ?? false) {
            $anomalies['spatial_anomalies'][] = [
                'name' => 'System-wide anomaly',
                'type' => $poiAttrs['anomaly_type'] ?? 'unknown',
                'danger_level' => $poiAttrs['anomaly_danger'] ?? 'low',
            ];
        }

        return $anomalies;
    }

    /**
     * Generate Level 7: Deep scan data.
     */
    protected function generateDeepScanData(PointOfInterest $poi): array
    {
        $children = $poi->children()->get();
        $deepData = [
            'subsurface_deposits' => [],
            'core_composition' => [],
            'terraforming' => [],
        ];

        foreach ($children as $child) {
            if (! $child->type?->isSystemType()) {
                continue;
            }

            $attrs = $child->attributes ?? [];

            // Subsurface deposits
            $subsurface = $attrs['subsurface_minerals'] ?? [];
            if (! empty($subsurface)) {
                $deepData['subsurface_deposits'][] = [
                    'planet' => $child->name,
                    'minerals' => $subsurface,
                    'depth' => $attrs['deposit_depth'] ?? 'deep',
                ];
            }

            // Core composition
            $core = $attrs['core_composition'] ?? null;
            if ($core) {
                $deepData['core_composition'][] = [
                    'planet' => $child->name,
                    'type' => $core,
                    'stability' => $attrs['core_stability'] ?? 'stable',
                ];
            }

            // Terraforming viability
            $terraformable = $attrs['terraformable'] ?? null;
            if ($terraformable !== null) {
                $deepData['terraforming'][] = [
                    'planet' => $child->name,
                    'viable' => $terraformable,
                    'difficulty' => $attrs['terraform_difficulty'] ?? 'moderate',
                    'time_estimate' => $attrs['terraform_time'] ?? 'decades',
                ];
            }
        }

        return $deepData;
    }

    /**
     * Generate Level 8: Intel data.
     */
    protected function generateIntelData(PointOfInterest $poi): array
    {
        $intel = [
            'pirate_hideouts' => [],
            'hidden_bases' => [],
            'cloaked_structures' => [],
        ];

        // Check for pirate presence on warp lanes
        $pirateGates = DB::table('warp_lane_pirates')
            ->join('warp_gates', 'warp_lane_pirates.warp_gate_id', '=', 'warp_gates.id')
            ->where(function ($query) use ($poi) {
                $query->where('warp_gates.source_poi_id', $poi->id)
                    ->orWhere('warp_gates.destination_poi_id', $poi->id);
            })
            ->select('warp_lane_pirates.*', 'warp_gates.uuid as gate_uuid')
            ->get();

        foreach ($pirateGates as $pirate) {
            $intel['pirate_hideouts'][] = [
                'gate_id' => $pirate->gate_uuid,
                'threat_level' => $pirate->fleet_strength ?? 'unknown',
            ];
        }

        // Check POI attributes for hidden bases
        $poiAttrs = $poi->attributes ?? [];
        if ($poiAttrs['has_hidden_base'] ?? false) {
            $intel['hidden_bases'][] = [
                'type' => $poiAttrs['base_type'] ?? 'unknown',
                'faction' => $poiAttrs['base_faction'] ?? 'unknown',
            ];
        }

        // Check children for cloaked structures
        $children = $poi->children()->where('is_hidden', true)->get();
        foreach ($children as $child) {
            $intel['cloaked_structures'][] = [
                'name' => $child->name,
                'type' => $child->type->label(),
            ];
        }

        return $intel;
    }

    /**
     * Generate Level 9: Precursor data.
     */
    protected function generatePrecursorData(PointOfInterest $poi): array
    {
        $precursor = [
            'hidden_gates' => [],
            'tech_caches' => [],
            'ancient_secrets' => [],
        ];

        // Check for hidden gates
        $hiddenGates = $poi->outgoingGates()->where('is_hidden', true)->get();
        foreach ($hiddenGates as $gate) {
            $precursor['hidden_gates'][] = [
                'type' => $gate->gate_type?->label() ?? 'unknown',
                'status' => $gate->status,
                'requires' => $gate->activation_requirements,
            ];
        }

        // Check for precursor content
        $children = $poi->children()->get();
        foreach ($children as $child) {
            $attrs = $child->attributes ?? [];

            if ($attrs['has_precursor_cache'] ?? false) {
                $precursor['tech_caches'][] = [
                    'location' => $child->name,
                    'contents' => $attrs['cache_contents'] ?? ['unknown technology'],
                    'danger_level' => $attrs['cache_danger'] ?? 'moderate',
                ];
            }

            if ($attrs['ancient_secret'] ?? false) {
                $precursor['ancient_secrets'][] = [
                    'location' => $child->name,
                    'type' => $attrs['secret_type'] ?? 'unknown',
                    'hint' => $attrs['secret_hint'] ?? 'An ancient mystery awaits...',
                ];
            }
        }

        // Check POI itself for precursor elements
        $poiAttrs = $poi->attributes ?? [];
        if ($poiAttrs['precursor_origin'] ?? false) {
            $precursor['ancient_secrets'][] = [
                'location' => 'System origin',
                'type' => 'precursor homeworld',
                'hint' => $poiAttrs['precursor_hint'] ?? 'This system was significant to the ancients.',
            ];
        }

        return $precursor;
    }

    /**
     * Get star type description.
     */
    protected function getStarType(PointOfInterest $poi): string
    {
        $attrs = $poi->attributes ?? [];
        $stellarClass = $attrs['stellar_class'] ?? null;

        if ($stellarClass) {
            return match ($stellarClass) {
                'O' => 'O-class blue supergiant',
                'B' => 'B-class blue giant',
                'A' => 'A-class white star',
                'F' => 'F-class yellow-white star',
                'G' => 'G-class yellow dwarf',
                'K' => 'K-class orange dwarf',
                'M' => 'M-class red dwarf',
                default => "$stellarClass-class star",
            };
        }

        return $poi->type?->label() ?? 'Unknown stellar object';
    }
}

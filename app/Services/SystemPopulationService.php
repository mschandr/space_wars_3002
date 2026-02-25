<?php

namespace App\Services;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\PointOfInterest;
use Illuminate\Support\Facades\DB;

/**
 * System Population Service
 *
 * Lazily populates star system details on first access.
 * This includes planetary attributes, moons, anomalies, and other features
 * that are not generated during initial galaxy creation for performance.
 */
class SystemPopulationService
{
    /**
     * Attribute key that marks a system as fully populated.
     */
    protected const POPULATED_FLAG = 'system_populated';

    /**
     * Ensure a star system is fully populated with all details.
     * Called on first access (view or travel).
     *
     * @param  PointOfInterest  $system  The star system POI
     * @return bool True if population was performed, false if already populated
     */
    public function ensurePopulated(PointOfInterest $system): bool
    {
        // Only populate stars (not planets, moons, etc.)
        if (! $this->isStarType($system)) {
            return false;
        }

        $wasPopulated = false;

        // Check if already populated
        if (! $this->isPopulated($system)) {
            // Populate the system
            DB::transaction(function () use ($system) {
                $this->populateSystem($system);
            });
            $wasPopulated = true;
        }

        // Ensure inhabited systems have infrastructure (even if already populated)
        if ($system->is_inhabited) {
            $attrs = $system->attributes ?? [];
            if (! isset($attrs['system_defenses'])) {
                DB::transaction(function () use ($system) {
                    $children = $system->children()->get();
                    $this->addInhabitedSystemInfrastructure($system, $children);
                });
                $wasPopulated = true;
            }
        }

        return $wasPopulated;
    }

    /**
     * Check if a system has been fully populated.
     * Detects existing data even if the flag wasn't set.
     */
    public function isPopulated(PointOfInterest $system): bool
    {
        $attrs = $system->attributes ?? [];

        // Explicit flag takes precedence
        if ($attrs[self::POPULATED_FLAG] ?? false) {
            return true;
        }

        // Check if star already has goldilocks zone (indicates population)
        if (isset($attrs['goldilocks_zone'])) {
            return true;
        }

        // Check if children have substantive attributes
        $children = $system->children()->limit(3)->get();

        foreach ($children as $child) {
            $childAttrs = $child->attributes ?? [];

            // If any child has temperature or habitability data, system is populated
            if (isset($childAttrs['temperature']) ||
                isset($childAttrs['temperature_kelvin']) ||
                isset($childAttrs['habitability_score']) ||
                isset($childAttrs['atmosphere']) ||
                isset($childAttrs['body_populated'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a system needs population.
     * Returns true if this is a star that hasn't been populated yet.
     */
    public function needsPopulation(PointOfInterest $system): bool
    {
        // Only stars need population
        if (! $this->isStarType($system)) {
            return false;
        }

        // Check if already populated
        if ($this->isPopulated($system)) {
            return false;
        }

        // Also check if inhabited system needs infrastructure
        if ($system->is_inhabited) {
            $attrs = $system->attributes ?? [];
            if (! isset($attrs['system_defenses'])) {
                return true;
            }
        }

        return true;
    }

    /**
     * Populate all system details.
     */
    protected function populateSystem(PointOfInterest $system): void
    {
        // Get or generate stellar properties
        $stellarClass = $this->ensureStellarClass($system);

        // Calculate goldilocks zone based on stellar class
        $goldilocksZone = $this->calculateGoldilocksZone($stellarClass);

        // Update star attributes
        $starAttrs = $system->attributes ?? [];
        $starAttrs['goldilocks_zone'] = $goldilocksZone;
        $starAttrs['luminosity'] = $this->getStellarLuminosity($stellarClass);
        $starAttrs['temperature'] = $this->getStellarTemperature($stellarClass);
        $starAttrs[self::POPULATED_FLAG] = true;
        $starAttrs['populated_at'] = now()->toIso8601String();

        $system->attributes = $starAttrs;
        $system->save();

        // Populate all children (planets, asteroid belts)
        $children = $system->children()->get();

        foreach ($children as $child) {
            $this->populateOrbitalBody($child, $stellarClass, $goldilocksZone);
        }

        // Generate moons if they were deferred
        $this->generateDeferredMoons($system, $children);

        // Reload children to include new moons
        $children = $system->children()->get();

        // Inhabited systems must have at least one habitable planet
        if ($system->is_inhabited) {
            $this->ensureHabitablePlanet($system, $children);
            // Reload children in case a planet was created or modified
            $children = $system->children()->get();
        }

        // For inhabited systems: add mining infrastructure and defenses
        if ($system->is_inhabited) {
            $this->addInhabitedSystemInfrastructure($system, $children);
        }

        // Generate anomalies and special features (not for inhabited - too developed)
        if (! $system->is_inhabited) {
            $this->generateAnomalies($system, $children);
        }

        // Generate ruins and precursor content (rare)
        $this->generateRuinsAndSecrets($system, $children);
    }

    /**
     * Add infrastructure to inhabited systems.
     * These are established, developed systems with full mining operations and strong defenses.
     */
    protected function addInhabitedSystemInfrastructure(PointOfInterest $system, $children): void
    {
        // Add orbital stations (trading, shipyard, salvage yard)
        $this->addOrbitalStations($system);

        // Add mining facilities to all mineable bodies
        $this->addMiningFacilities($system, $children);

        // Add defense platforms
        $this->addDefensePlatforms($system);

        // Add system-wide defense data
        $this->addSystemDefenses($system);
    }

    /**
     * Add orbital stations to an inhabited core system.
     */
    protected function addOrbitalStations(PointOfInterest $system): void
    {
        $stations = [];

        // Trading Station (1 per system)
        $stations[] = $this->createStation(
            $system,
            PointOfInterestType::TRADING_STATION,
            $system->name.' Commerce Hub',
            [
                'station_class' => $this->weightedRandom([
                    'outpost' => 10,
                    'standard' => 40,
                    'major' => 35,
                    'hub' => 15,
                ]),
                'docking_bays' => mt_rand(8, 24),
                'cargo_capacity' => mt_rand(50000, 200000),
                'services' => ['trading', 'refueling', 'repairs', 'crew_quarters'],
                'market_specialization' => $this->weightedRandom([
                    'general' => 40,
                    'minerals' => 20,
                    'technology' => 15,
                    'luxury_goods' => 10,
                    'industrial' => 15,
                ]),
            ]
        );

        // Shipyard (1 per system)
        $stations[] = $this->createStation(
            $system,
            PointOfInterestType::SHIPYARD,
            $system->name.' Shipyard',
            [
                'shipyard_class' => $this->weightedRandom([
                    'light' => 20,
                    'standard' => 40,
                    'heavy' => 30,
                    'capital' => 10,
                ]),
                'dry_docks' => mt_rand(4, 12),
                'construction_bays' => mt_rand(2, 6),
                'max_ship_class' => $this->weightedRandom([
                    'frigate' => 15,
                    'cruiser' => 35,
                    'battleship' => 35,
                    'dreadnought' => 15,
                ]),
                'services' => ['construction', 'repairs', 'upgrades', 'refitting'],
                'specialization' => $this->weightedRandom([
                    'military' => 30,
                    'civilian' => 30,
                    'industrial' => 20,
                    'mixed' => 20,
                ]),
            ]
        );

        // Salvage Yard (1 per system)
        $stations[] = $this->createStation(
            $system,
            PointOfInterestType::SALVAGE_YARD,
            $system->name.' Salvage & Reclamation',
            [
                'yard_size' => $this->weightedRandom([
                    'small' => 20,
                    'medium' => 45,
                    'large' => 30,
                    'massive' => 5,
                ]),
                'processing_capacity' => mt_rand(5, 20),
                'inventory_quality' => $this->weightedRandom([
                    'scrap' => 10,
                    'salvage' => 30,
                    'refurbished' => 40,
                    'premium' => 20,
                ]),
                'services' => ['buying', 'selling', 'component_extraction', 'hull_recycling'],
                'specialization' => $this->weightedRandom([
                    'weapons' => 20,
                    'shields' => 15,
                    'engines' => 20,
                    'electronics' => 15,
                    'general' => 30,
                ]),
            ]
        );

        // Bulk insert all stations
        if (! empty($stations)) {
            PointOfInterest::insert($stations);
        }
    }

    /**
     * Create a station data array for bulk insert.
     */
    protected function createStation(PointOfInterest $system, PointOfInterestType $type, string $name, array $attrs): array
    {
        $attrs['body_populated'] = true;
        $attrs['operational_since'] = now()->subYears(mt_rand(10, 500))->year;
        $attrs['crew_complement'] = match ($type) {
            PointOfInterestType::TRADING_STATION => mt_rand(500, 5000),
            PointOfInterestType::SHIPYARD => mt_rand(1000, 10000),
            PointOfInterestType::SALVAGE_YARD => mt_rand(100, 1000),
            PointOfInterestType::DEFENSE_PLATFORM => mt_rand(50, 200),
            default => mt_rand(100, 500),
        };

        return [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'galaxy_id' => $system->galaxy_id,
            'sector_id' => $system->sector_id,
            'parent_poi_id' => $system->id,
            'type' => $type->value,
            'name' => $name,
            'x' => $system->x,
            'y' => $system->y,
            'orbital_index' => 100 + mt_rand(1, 50), // High orbital index for stations
            'attributes' => json_encode($attrs),
            'is_inhabited' => true,
            'is_hidden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Add defense platforms to the system.
     */
    protected function addDefensePlatforms(PointOfInterest $system): void
    {
        $platformCount = mt_rand(3, 8);
        $platforms = [];

        for ($i = 1; $i <= $platformCount; $i++) {
            $platforms[] = $this->createStation(
                $system,
                PointOfInterestType::DEFENSE_PLATFORM,
                $system->name.' Defense Platform '.$this->romanNumeral($i),
                [
                    'platform_class' => $this->weightedRandom([
                        'sentry' => 30,
                        'guardian' => 40,
                        'fortress' => 25,
                        'citadel' => 5,
                    ]),
                    'weapons' => [
                        'heavy_lasers' => mt_rand(4, 12),
                        'missile_batteries' => mt_rand(2, 8),
                        'point_defense' => mt_rand(8, 24),
                    ],
                    'shields' => $this->weightedRandom([
                        'standard' => 30,
                        'reinforced' => 40,
                        'heavy' => 25,
                        'impenetrable' => 5,
                    ]),
                    'fighter_capacity' => mt_rand(0, 24),
                    'sensor_range' => mt_rand(50, 150),
                    'status' => 'active',
                ]
            );
        }

        if (! empty($platforms)) {
            PointOfInterest::insert($platforms);
        }
    }

    /**
     * Convert number to Roman numeral.
     */
    protected function romanNumeral(int $num): string
    {
        $map = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];

        return $map[$num - 1] ?? (string) $num;
    }

    /**
     * Add mining facilities to all mineable locations in the system.
     */
    protected function addMiningFacilities(PointOfInterest $system, $children): void
    {
        foreach ($children as $body) {
            $attrs = $body->attributes ?? [];

            // Check if body is mineable (has mineral deposits or richness)
            $hasDeposits = ! empty($attrs['mineral_deposits'] ?? []) ||
                           ! empty($body->mineral_deposits ?? []) ||
                           isset($attrs['mineral_richness']);

            // Asteroid belts are always mineable
            $isAsteroidBelt = $body->type === PointOfInterestType::ASTEROID_BELT ||
                              $body->type === PointOfInterestType::ASTEROID;

            // Gas giants have helium-3 extraction
            $isGasGiant = $body->type === PointOfInterestType::GAS_GIANT ||
                          $body->type === PointOfInterestType::HOT_JUPITER ||
                          $body->type === PointOfInterestType::ICE_GIANT;

            if ($hasDeposits || $isAsteroidBelt || $isGasGiant) {
                $attrs['has_mining_facility'] = true;
                $attrs['mining_facility'] = [
                    'type' => $isGasGiant ? 'atmospheric_extraction' : ($isAsteroidBelt ? 'belt_mining_station' : 'surface_mining'),
                    'status' => 'operational',
                    'output' => $this->weightedRandom([
                        'low' => 10,
                        'moderate' => 40,
                        'high' => 35,
                        'maximum' => 15,
                    ]),
                    'automated' => mt_rand(1, 100) <= 70,
                ];

                $body->attributes = $attrs;
                $body->save();
            }

            // Also check moons
            $moons = $body->children()->get();
            foreach ($moons as $moon) {
                $moonAttrs = $moon->attributes ?? [];
                $moonHasDeposits = ! empty($moonAttrs['mineral_deposits'] ?? []) ||
                                   isset($moonAttrs['mineral_richness']);

                if ($moonHasDeposits) {
                    $moonAttrs['has_mining_facility'] = true;
                    $moonAttrs['mining_facility'] = [
                        'type' => 'lunar_mining',
                        'status' => 'operational',
                        'output' => $this->weightedRandom([
                            'low' => 20,
                            'moderate' => 50,
                            'high' => 25,
                            'maximum' => 5,
                        ]),
                        'automated' => true,
                    ];

                    $moon->attributes = $moonAttrs;
                    $moon->save();
                }
            }
        }
    }

    /**
     * Add defensive infrastructure to an inhabited core system.
     */
    protected function addSystemDefenses(PointOfInterest $system): void
    {
        $attrs = $system->attributes ?? [];

        // Core inhabited systems have comprehensive defenses
        $attrs['system_defenses'] = [
            'defense_level' => 'fortress',
            'orbital_platforms' => mt_rand(8, 15),
            'defense_satellites' => mt_rand(50, 100),
            'planetary_shields' => true,
            'shield_strength' => $this->weightedRandom([
                'standard' => 20,
                'reinforced' => 40,
                'heavy' => 30,
                'impenetrable' => 10,
            ]),
            'fighter_squadrons' => mt_rand(4, 8),
            'fighters_per_squadron' => 12,
            'patrol_frigates' => mt_rand(2, 5),
            'defense_stations' => mt_rand(1, 3),
            'early_warning_range' => mt_rand(150, 300),
            'response_time' => 'immediate',
            'garrison_strength' => $this->weightedRandom([
                'standard' => 15,
                'reinforced' => 35,
                'heavy' => 35,
                'elite' => 15,
            ]),
        ];

        // Mark as fortified
        $attrs['is_fortified'] = true;
        $attrs['threat_response'] = 'Only the largest and most heavily armed pirate fleets would dare attack this system.';

        $system->attributes = $attrs;
        $system->save();
    }

    /**
     * Ensure an inhabited system has at least one habitable planet.
     *
     * 1. Check if any child has habitable = true
     * 2. If not, find best terrestrial/ocean/super_earth candidate and upgrade it
     * 3. If no candidates exist, create a new habitable planet
     */
    protected function ensureHabitablePlanet(PointOfInterest $system, $children): void
    {
        // Check if any child is already habitable
        foreach ($children as $child) {
            $attrs = $child->attributes ?? [];
            if ($attrs['habitable'] ?? false) {
                return;
            }
        }

        // Find best terrestrial/ocean/super_earth candidate to upgrade
        $candidate = $children->first(function ($child) {
            return in_array($child->type, [
                PointOfInterestType::TERRESTRIAL,
                PointOfInterestType::OCEAN,
                PointOfInterestType::SUPER_EARTH,
            ]);
        });

        if ($candidate) {
            // Force-upgrade the candidate to be habitable
            $attrs = $candidate->attributes ?? [];
            $attrs['atmosphere'] = 'nitrogen-oxygen';
            $attrs['atmosphere_density'] = $attrs['atmosphere_density'] ?? 'normal';
            $attrs['temperature'] = 'temperate';
            $attrs['temperature_kelvin'] = mt_rand(280, 300);
            $attrs['has_magnetic_field'] = true;
            $attrs['radiation_level'] = 'low';
            $attrs['water_coverage'] = mt_rand(30, 70);
            $attrs['in_goldilocks_zone'] = true;
            $attrs['habitability_score'] = round(mt_rand(60, 90) / 100, 2);
            $attrs['habitable'] = true;
            $candidate->attributes = $attrs;
            $candidate->save();

            return;
        }

        // No terrestrial candidates — create one
        $starAttrs = $system->attributes ?? [];
        $goldilocksZone = $this->calculateGoldilocksZone($starAttrs['stellar_class'] ?? 'G');
        $orbitalDistance = ($goldilocksZone['inner'] + $goldilocksZone['outer']) / 2;

        PointOfInterest::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'galaxy_id' => $system->galaxy_id,
            'sector_id' => $system->sector_id,
            'parent_poi_id' => $system->id,
            'orbital_index' => 50, // High index to avoid collisions
            'type' => PointOfInterestType::TERRESTRIAL,
            'name' => $system->name.' Prime',
            'x' => $system->x,
            'y' => $system->y,
            'attributes' => [
                'orbital_distance' => $orbitalDistance,
                'size' => 'medium',
                'atmosphere' => 'nitrogen-oxygen',
                'atmosphere_density' => 'normal',
                'temperature' => 'temperate',
                'temperature_kelvin' => mt_rand(280, 300),
                'has_magnetic_field' => true,
                'radiation_level' => 'low',
                'water_coverage' => mt_rand(30, 70),
                'in_goldilocks_zone' => true,
                'habitability_score' => round(mt_rand(70, 90) / 100, 2),
                'habitable' => true,
                'gravity' => round(mt_rand(80, 120) / 100, 2),
                'core_composition' => 'iron-nickel',
                'body_populated' => true,
            ],
            'is_inhabited' => false,
            'is_charted' => true,
        ]);
    }

    /**
     * Populate an orbital body (planet, asteroid belt, etc.).
     */
    protected function populateOrbitalBody(
        PointOfInterest $body,
        string $stellarClass,
        array $goldilocksZone
    ): void {
        $attrs = $body->attributes ?? [];
        $orbitalDistance = $attrs['orbital_distance'] ?? ($body->orbital_index * 10 + 5);

        // Determine if in goldilocks zone
        $inGoldilocks = $orbitalDistance >= $goldilocksZone['inner'] &&
                        $orbitalDistance <= $goldilocksZone['outer'];

        // Generate attributes based on body type
        match ($body->type) {
            PointOfInterestType::TERRESTRIAL,
            PointOfInterestType::SUPER_EARTH,
            PointOfInterestType::PLANET => $this->populateTerrestrialPlanet($body, $inGoldilocks, $orbitalDistance, $stellarClass),

            PointOfInterestType::GAS_GIANT,
            PointOfInterestType::HOT_JUPITER => $this->populateGasGiant($body, $orbitalDistance),

            PointOfInterestType::ICE_GIANT => $this->populateIceGiant($body, $orbitalDistance),

            PointOfInterestType::OCEAN => $this->populateOceanWorld($body, $inGoldilocks, $orbitalDistance),

            PointOfInterestType::LAVA => $this->populateLavaWorld($body, $orbitalDistance),

            PointOfInterestType::CHTHONIC => $this->populateChthonic($body, $orbitalDistance),

            PointOfInterestType::DWARF_PLANET => $this->populateDwarfPlanet($body, $orbitalDistance),

            PointOfInterestType::ASTEROID_BELT,
            PointOfInterestType::ASTEROID => $this->populateAsteroidBelt($body, $orbitalDistance),

            default => null,
        };

        // Mark body as populated
        $attrs = $body->attributes ?? [];
        $attrs['body_populated'] = true;
        $body->attributes = $attrs;
        $body->save();
    }

    /**
     * Populate a terrestrial planet.
     */
    protected function populateTerrestrialPlanet(
        PointOfInterest $body,
        bool $inGoldilocks,
        float $orbitalDistance,
        string $stellarClass
    ): void {
        $attrs = $body->attributes ?? [];

        // Temperature based on orbital distance and stellar class
        $baseTemp = $this->calculateTemperature($orbitalDistance, $stellarClass);
        $attrs['temperature'] = $this->getTemperatureLabel($baseTemp);
        $attrs['temperature_kelvin'] = $baseTemp;

        // Atmosphere (random but influenced by size and temperature)
        $size = $attrs['size'] ?? 'medium';
        $atmosphereChance = match ($size) {
            'tiny' => 0.1,
            'small' => 0.3,
            'medium' => 0.6,
            'large' => 0.8,
            'massive' => 0.95,
            default => 0.5,
        };

        if (mt_rand(1, 100) / 100 <= $atmosphereChance) {
            $attrs['atmosphere'] = $this->generateAtmosphere($baseTemp, $inGoldilocks);
            $attrs['atmosphere_density'] = $this->weightedRandom([
                'thin' => 30,
                'normal' => 40,
                'dense' => 20,
                'very_dense' => 10,
            ]);
        } else {
            $attrs['atmosphere'] = 'none';
            $attrs['atmosphere_density'] = 'vacuum';
        }

        // Gravity based on size
        $attrs['gravity'] = match ($size) {
            'tiny' => round(mt_rand(5, 20) / 100, 2),
            'small' => round(mt_rand(20, 50) / 100, 2),
            'medium' => round(mt_rand(70, 130) / 100, 2),
            'large' => round(mt_rand(120, 200) / 100, 2),
            'massive' => round(mt_rand(180, 300) / 100, 2),
            default => 1.0,
        };

        // Habitability
        $attrs['in_goldilocks_zone'] = $inGoldilocks;
        $habitabilityScore = $this->calculateHabitability($attrs, $inGoldilocks);
        $attrs['habitability_score'] = $habitabilityScore;
        $attrs['habitable'] = $habitabilityScore >= 0.5;

        // Water coverage (only if conditions allow)
        if ($inGoldilocks && $attrs['atmosphere'] !== 'none' && $baseTemp > 273 && $baseTemp < 373) {
            $attrs['water_coverage'] = mt_rand(0, 80);
        } else {
            $attrs['water_coverage'] = 0;
        }

        // Magnetic field (protects from radiation)
        $attrs['has_magnetic_field'] = mt_rand(1, 100) <= 40;
        $attrs['radiation_level'] = $attrs['has_magnetic_field'] ? 'low' : $this->weightedRandom([
            'low' => 20,
            'moderate' => 40,
            'high' => 30,
            'extreme' => 10,
        ]);

        // Core composition
        $attrs['core_composition'] = $this->weightedRandom([
            'iron-nickel' => 50,
            'silicate' => 30,
            'mixed' => 15,
            'exotic' => 5,
        ]);

        // Terraforming potential
        if (! $attrs['habitable'] && $habitabilityScore >= 0.2) {
            $attrs['terraformable'] = true;
            $attrs['terraform_difficulty'] = match (true) {
                $habitabilityScore >= 0.4 => 'easy',
                $habitabilityScore >= 0.3 => 'moderate',
                default => 'difficult',
            };
            $attrs['terraform_time'] = match ($attrs['terraform_difficulty']) {
                'easy' => 'years',
                'moderate' => 'decades',
                'difficult' => 'centuries',
                default => 'millennia',
            };
        }

        // Has rings (rare for terrestrials)
        $attrs['has_rings'] = mt_rand(1, 100) <= 5;
        if ($attrs['has_rings']) {
            $attrs['ring_deposits'] = $this->generateRingDeposits();
        }

        $body->attributes = $attrs;
    }

    /**
     * Populate a gas giant.
     */
    protected function populateGasGiant(PointOfInterest $body, float $orbitalDistance): void
    {
        $attrs = $body->attributes ?? [];

        $attrs['atmosphere'] = 'hydrogen-helium';
        $attrs['atmosphere_density'] = 'crushing';
        $attrs['temperature'] = $orbitalDistance < 30 ? 'hot' : 'cold';
        $attrs['temperature_kelvin'] = $orbitalDistance < 30 ? mt_rand(400, 1500) : mt_rand(80, 200);
        $attrs['gravity'] = round(mt_rand(200, 500) / 100, 2);
        $attrs['habitable'] = false;
        $attrs['habitability_score'] = 0.0;
        $attrs['has_magnetic_field'] = true;
        $attrs['radiation_level'] = 'extreme';

        // Gas giants often have rings
        $attrs['has_rings'] = mt_rand(1, 100) <= 70;
        if ($attrs['has_rings']) {
            $attrs['ring_deposits'] = $this->generateRingDeposits();
        }

        // Core
        $attrs['core_composition'] = $this->weightedRandom([
            'rocky' => 40,
            'metallic' => 30,
            'exotic' => 20,
            'none' => 10,
        ]);

        // Special resources
        $attrs['metallic_hydrogen'] = true;
        $attrs['helium3_abundance'] = $this->weightedRandom([
            'trace' => 20,
            'moderate' => 40,
            'rich' => 30,
            'exceptional' => 10,
        ]);

        $body->attributes = $attrs;
    }

    /**
     * Populate an ice giant.
     */
    protected function populateIceGiant(PointOfInterest $body, float $orbitalDistance): void
    {
        $attrs = $body->attributes ?? [];

        $attrs['atmosphere'] = 'methane-ammonia';
        $attrs['atmosphere_density'] = 'dense';
        $attrs['temperature'] = 'frozen';
        $attrs['temperature_kelvin'] = mt_rand(50, 100);
        $attrs['gravity'] = round(mt_rand(80, 180) / 100, 2);
        $attrs['habitable'] = false;
        $attrs['habitability_score'] = 0.0;
        $attrs['has_magnetic_field'] = mt_rand(1, 100) <= 80;
        $attrs['radiation_level'] = 'high';

        // Ice giants often have rings
        $attrs['has_rings'] = mt_rand(1, 100) <= 60;
        if ($attrs['has_rings']) {
            $attrs['ring_deposits'] = ['ice', 'dust', 'silicates'];
        }

        $attrs['core_composition'] = 'ice-rock';

        $body->attributes = $attrs;
    }

    /**
     * Populate an ocean world.
     */
    protected function populateOceanWorld(PointOfInterest $body, bool $inGoldilocks, float $orbitalDistance): void
    {
        $attrs = $body->attributes ?? [];

        $attrs['atmosphere'] = $inGoldilocks ? 'nitrogen-oxygen' : 'nitrogen-methane';
        $attrs['atmosphere_density'] = 'normal';
        $attrs['temperature'] = $inGoldilocks ? 'temperate' : 'cold';
        $attrs['temperature_kelvin'] = $inGoldilocks ? mt_rand(273, 310) : mt_rand(200, 273);
        $attrs['gravity'] = round(mt_rand(60, 140) / 100, 2);
        $attrs['water_coverage'] = mt_rand(85, 100);
        $attrs['in_goldilocks_zone'] = $inGoldilocks;

        // Ocean worlds can be habitable
        $attrs['habitable'] = $inGoldilocks && mt_rand(1, 100) <= 70;
        $attrs['habitability_score'] = $inGoldilocks ? round(mt_rand(40, 80) / 100, 2) : 0.2;

        $attrs['has_magnetic_field'] = mt_rand(1, 100) <= 60;
        $attrs['radiation_level'] = $attrs['has_magnetic_field'] ? 'low' : 'moderate';
        $attrs['core_composition'] = 'silicate';
        $attrs['has_rings'] = false;

        // Subsurface possibilities
        if (! $inGoldilocks) {
            $attrs['subsurface_ocean'] = true;
            $attrs['potential_life'] = mt_rand(1, 100) <= 20;
        }

        $body->attributes = $attrs;
    }

    /**
     * Populate a lava world.
     */
    protected function populateLavaWorld(PointOfInterest $body, float $orbitalDistance): void
    {
        $attrs = $body->attributes ?? [];

        $attrs['atmosphere'] = 'sulfur-dioxide';
        $attrs['atmosphere_density'] = 'thin';
        $attrs['temperature'] = 'infernal';
        $attrs['temperature_kelvin'] = mt_rand(700, 2000);
        $attrs['gravity'] = round(mt_rand(80, 200) / 100, 2);
        $attrs['habitable'] = false;
        $attrs['habitability_score'] = 0.0;
        $attrs['water_coverage'] = 0;
        $attrs['has_magnetic_field'] = mt_rand(1, 100) <= 30;
        $attrs['radiation_level'] = 'extreme';
        $attrs['core_composition'] = 'molten-iron';
        $attrs['has_rings'] = false;

        // Lava worlds are rich in minerals
        $attrs['mineral_richness'] = $this->weightedRandom([
            'rich' => 40,
            'exceptional' => 40,
            'legendary' => 20,
        ]);

        // Subsurface minerals
        $attrs['subsurface_minerals'] = ['iron', 'nickel', 'titanium', 'platinum'];

        $body->attributes = $attrs;
    }

    /**
     * Populate a chthonic planet (stripped gas giant core).
     */
    protected function populateChthonic(PointOfInterest $body, float $orbitalDistance): void
    {
        $attrs = $body->attributes ?? [];

        $attrs['atmosphere'] = 'trace';
        $attrs['atmosphere_density'] = 'negligible';
        $attrs['temperature'] = 'hot';
        $attrs['temperature_kelvin'] = mt_rand(500, 1200);
        $attrs['gravity'] = round(mt_rand(150, 400) / 100, 2);
        $attrs['habitable'] = false;
        $attrs['habitability_score'] = 0.0;
        $attrs['has_magnetic_field'] = true;
        $attrs['radiation_level'] = 'high';
        $attrs['core_composition'] = 'exotic';
        $attrs['has_rings'] = false;

        // Chthonics are extremely mineral-rich (exposed cores)
        $attrs['mineral_richness'] = 'legendary';
        $attrs['subsurface_minerals'] = ['platinum', 'iridium', 'exotic_matter', 'quantium'];

        $body->attributes = $attrs;
    }

    /**
     * Populate a dwarf planet.
     */
    protected function populateDwarfPlanet(PointOfInterest $body, float $orbitalDistance): void
    {
        $attrs = $body->attributes ?? [];

        $attrs['atmosphere'] = mt_rand(1, 100) <= 20 ? 'trace' : 'none';
        $attrs['atmosphere_density'] = $attrs['atmosphere'] === 'trace' ? 'negligible' : 'vacuum';
        $attrs['temperature'] = 'frozen';
        $attrs['temperature_kelvin'] = mt_rand(30, 80);
        $attrs['gravity'] = round(mt_rand(1, 15) / 100, 2);
        $attrs['habitable'] = false;
        $attrs['habitability_score'] = 0.0;
        $attrs['has_magnetic_field'] = false;
        $attrs['radiation_level'] = 'high';
        $attrs['core_composition'] = $this->weightedRandom([
            'ice-rock' => 60,
            'silicate' => 30,
            'metallic' => 10,
        ]);
        $attrs['has_rings'] = false;

        $body->attributes = $attrs;
    }

    /**
     * Populate an asteroid belt.
     */
    protected function populateAsteroidBelt(PointOfInterest $body, float $orbitalDistance): void
    {
        $attrs = $body->attributes ?? [];

        $attrs['density'] = $attrs['density'] ?? $this->weightedRandom([
            'sparse' => 30,
            'moderate' => 40,
            'dense' => 25,
            'very_dense' => 5,
        ]);

        $attrs['mineral_richness'] = $attrs['mineral_richness'] ?? $this->weightedRandom([
            'poor' => 10,
            'moderate' => 30,
            'rich' => 40,
            'exceptional' => 20,
        ]);

        // Composition
        $attrs['composition'] = $this->weightedRandom([
            'carbonaceous' => 30,
            'siliceous' => 35,
            'metallic' => 25,
            'mixed' => 10,
        ]);

        // Danger level based on density
        $attrs['navigation_hazard'] = match ($attrs['density']) {
            'sparse' => 'low',
            'moderate' => 'moderate',
            'dense' => 'high',
            'very_dense' => 'extreme',
            default => 'moderate',
        };

        $body->attributes = $attrs;
    }

    /**
     * Generate deferred moons for planets.
     */
    protected function generateDeferredMoons(PointOfInterest $system, $children): void
    {
        foreach ($children as $planet) {
            // Skip if planet already has moons or isn't a planet type
            if ($planet->children()->exists()) {
                continue;
            }

            // Determine moon count based on planet type
            $moonCount = match ($planet->type) {
                PointOfInterestType::GAS_GIANT => mt_rand(3, 8),
                PointOfInterestType::HOT_JUPITER => mt_rand(0, 2),
                PointOfInterestType::ICE_GIANT => mt_rand(2, 5),
                PointOfInterestType::SUPER_EARTH => mt_rand(0, 2),
                PointOfInterestType::TERRESTRIAL => mt_rand(0, 2),
                PointOfInterestType::OCEAN => mt_rand(0, 1),
                default => 0,
            };

            if ($moonCount === 0) {
                continue;
            }

            // Create moons
            $moons = [];
            for ($i = 1; $i <= $moonCount; $i++) {
                $moonAttrs = $this->generateMoonAttributes($planet, $i);

                $moons[] = [
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'galaxy_id' => $system->galaxy_id,
                    'sector_id' => $system->sector_id,
                    'parent_poi_id' => $planet->id,
                    'type' => PointOfInterestType::MOON->value,
                    'name' => $planet->name.' '.chr(96 + $i), // Planet a, Planet b, etc.
                    'x' => $planet->x,
                    'y' => $planet->y,
                    'orbital_index' => $i,
                    'attributes' => json_encode($moonAttrs),
                    'is_inhabited' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($moons)) {
                PointOfInterest::insert($moons);
            }
        }
    }

    /**
     * Generate attributes for a moon.
     */
    protected function generateMoonAttributes(PointOfInterest $planet, int $orbitalIndex): array
    {
        $planetAttrs = $planet->attributes ?? [];

        // Size based on parent
        $size = match ($planet->type) {
            PointOfInterestType::GAS_GIANT,
            PointOfInterestType::ICE_GIANT => $this->weightedRandom([
                'tiny' => 20,
                'small' => 40,
                'medium' => 30,
                'large' => 10,
            ]),
            default => $this->weightedRandom([
                'tiny' => 50,
                'small' => 40,
                'medium' => 10,
            ]),
        };

        $attrs = [
            'size' => $size,
            'orbital_distance' => $orbitalIndex * 2,
            'body_populated' => true,
        ];

        // Basic properties
        $attrs['atmosphere'] = mt_rand(1, 100) <= 15 ? 'trace' : 'none';
        $attrs['gravity'] = match ($size) {
            'tiny' => round(mt_rand(1, 5) / 100, 2),
            'small' => round(mt_rand(5, 15) / 100, 2),
            'medium' => round(mt_rand(10, 30) / 100, 2),
            'large' => round(mt_rand(20, 50) / 100, 2),
            default => 0.05,
        };

        // Habitability (rare but possible for large moons of gas giants in goldilocks)
        $parentInGoldilocks = $planetAttrs['in_goldilocks_zone'] ?? false;
        $isLargeMoon = in_array($size, ['medium', 'large']);

        if ($parentInGoldilocks && $isLargeMoon && mt_rand(1, 100) <= 20) {
            $attrs['habitable'] = true;
            $attrs['habitability_score'] = round(mt_rand(50, 70) / 100, 2);
            $attrs['atmosphere'] = 'nitrogen-oxygen';
            $attrs['temperature'] = 'temperate';
            $attrs['water_coverage'] = mt_rand(20, 60);
            $attrs['climate'] = $this->weightedRandom([
                'temperate' => 40,
                'cold' => 30,
                'arid' => 20,
                'tropical' => 10,
            ]);
        } else {
            $attrs['habitable'] = false;
            $attrs['habitability_score'] = 0.0;
            $attrs['temperature'] = 'frozen';
        }

        // Mineral deposits (moons can have resources)
        if (mt_rand(1, 100) <= 40) {
            $attrs['mineral_deposits'] = $this->generateMoonMinerals();
            $attrs['mineral_richness'] = $this->weightedRandom([
                'trace' => 30,
                'moderate' => 40,
                'rich' => 25,
                'exceptional' => 5,
            ]);
        }

        // Tidal heating (for close-in moons of gas giants)
        if ($orbitalIndex <= 2 && in_array($planet->type, [
            PointOfInterestType::GAS_GIANT,
            PointOfInterestType::ICE_GIANT,
        ])) {
            $attrs['tidal_heating'] = true;
            $attrs['subsurface_ocean'] = mt_rand(1, 100) <= 60;
            if ($attrs['subsurface_ocean']) {
                $attrs['potential_life'] = mt_rand(1, 100) <= 10;
            }
        }

        return $attrs;
    }

    /**
     * Generate anomalies and special features.
     */
    protected function generateAnomalies(PointOfInterest $system, $children): void
    {
        // 10% chance of system-wide anomaly
        if (mt_rand(1, 100) <= 10) {
            $attrs = $system->attributes ?? [];
            $attrs['has_anomaly'] = true;
            $attrs['anomaly_type'] = $this->weightedRandom([
                'gravitational_lens' => 20,
                'radiation_burst' => 20,
                'magnetic_storm' => 20,
                'temporal_distortion' => 15,
                'dark_matter_concentration' => 15,
                'subspace_rift' => 10,
            ]);
            $attrs['anomaly_danger'] = $this->weightedRandom([
                'low' => 30,
                'moderate' => 40,
                'high' => 25,
                'extreme' => 5,
            ]);
            $system->attributes = $attrs;
            $system->save();
        }

        // Check each body for derelicts (rare)
        foreach ($children as $body) {
            // 2% chance of derelict near any body
            if (mt_rand(1, 100) <= 2) {
                $this->createDerelict($system, $body);
            }
        }
    }

    /**
     * Create a derelict ship near a body.
     */
    protected function createDerelict(PointOfInterest $system, PointOfInterest $nearBody): void
    {
        $shipClass = $this->weightedRandom([
            'scout' => 30,
            'freighter' => 30,
            'warship' => 20,
            'research' => 15,
            'unknown' => 5,
        ]);

        $attrs = [
            'ship_class' => $shipClass,
            'salvageable' => mt_rand(1, 100) <= 80,
            'danger_level' => $this->weightedRandom([
                'safe' => 40,
                'moderate' => 35,
                'dangerous' => 20,
                'extreme' => 5,
            ]),
            'origin' => $this->weightedRandom([
                'terran' => 40,
                'colonial' => 30,
                'pirate' => 20,
                'unknown' => 10,
            ]),
            'age_estimate' => $this->weightedRandom([
                'recent' => 20,
                'decades' => 30,
                'centuries' => 35,
                'ancient' => 15,
            ]),
            'body_populated' => true,
        ];

        PointOfInterest::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'galaxy_id' => $system->galaxy_id,
            'sector_id' => $system->sector_id,
            'parent_poi_id' => $nearBody->id,
            'type' => PointOfInterestType::DERELICT,
            'name' => 'Derelict '.$shipClass.' '.$nearBody->name,
            'x' => $nearBody->x,
            'y' => $nearBody->y,
            'orbital_index' => 99,
            'attributes' => $attrs,
            'is_inhabited' => false,
        ]);
    }

    /**
     * Generate ruins and precursor secrets (very rare).
     */
    protected function generateRuinsAndSecrets(PointOfInterest $system, $children): void
    {
        // Only process habitable or terrestrial bodies
        $candidates = $children->filter(function ($body) {
            return in_array($body->type, [
                PointOfInterestType::TERRESTRIAL,
                PointOfInterestType::SUPER_EARTH,
                PointOfInterestType::OCEAN,
                PointOfInterestType::PLANET,
            ]);
        });

        foreach ($candidates as $body) {
            $attrs = $body->attributes ?? [];

            // 5% chance of ruins on suitable planets
            if (mt_rand(1, 100) <= 5) {
                $attrs['has_ruins'] = true;
                $attrs['ruin_type'] = $this->weightedRandom([
                    'ancient_city' => 25,
                    'research_facility' => 20,
                    'military_base' => 20,
                    'temple' => 15,
                    'colony' => 15,
                    'unknown' => 5,
                ]);
                $attrs['ruin_age'] = $this->weightedRandom([
                    'centuries' => 30,
                    'millennia' => 40,
                    'eons' => 25,
                    'primordial' => 5,
                ]);
                $attrs['ruin_condition'] = $this->weightedRandom([
                    'well_preserved' => 15,
                    'damaged' => 35,
                    'ruined' => 35,
                    'fragmentary' => 15,
                ]);
            }

            // 1% chance of precursor cache
            if (mt_rand(1, 100) <= 1) {
                $attrs['has_precursor_cache'] = true;
                $attrs['cache_contents'] = $this->generatePrecursorContents();
                $attrs['cache_danger'] = $this->weightedRandom([
                    'safe' => 20,
                    'moderate' => 40,
                    'high' => 30,
                    'extreme' => 10,
                ]);
            }

            // 0.5% chance of ancient secret
            if (mt_rand(1, 200) <= 1) {
                $attrs['ancient_secret'] = true;
                $attrs['secret_type'] = $this->weightedRandom([
                    'star_map' => 30,
                    'technology' => 25,
                    'coordinates' => 20,
                    'warning' => 15,
                    'origin_story' => 10,
                ]);
                $attrs['secret_hint'] = $this->generateSecretHint($attrs['secret_type']);
            }

            if (isset($attrs['has_ruins']) || isset($attrs['has_precursor_cache']) || isset($attrs['ancient_secret'])) {
                $body->attributes = $attrs;
                $body->save();
            }
        }
    }

    /**
     * Ensure stellar class is set.
     */
    protected function ensureStellarClass(PointOfInterest $system): string
    {
        $attrs = $system->attributes ?? [];

        if (isset($attrs['stellar_class'])) {
            return $attrs['stellar_class'];
        }

        // Generate based on region
        $isCore = $system->region?->value === 'core';

        $class = $isCore
            ? $this->weightedRandom(['G' => 40, 'K' => 30, 'F' => 15, 'M' => 10, 'A' => 5])
            : $this->weightedRandom(['M' => 30, 'K' => 25, 'G' => 20, 'F' => 10, 'A' => 8, 'B' => 5, 'O' => 2]);

        $attrs['stellar_class'] = $class;
        $system->attributes = $attrs;
        $system->save();

        return $class;
    }

    /**
     * Calculate goldilocks zone based on stellar class.
     */
    protected function calculateGoldilocksZone(string $stellarClass): array
    {
        // Approximate habitable zone distances (AU equivalent scaled to game units)
        return match ($stellarClass) {
            'O' => ['inner' => 200, 'outer' => 400],
            'B' => ['inner' => 100, 'outer' => 250],
            'A' => ['inner' => 50, 'outer' => 150],
            'F' => ['inner' => 30, 'outer' => 80],
            'G' => ['inner' => 20, 'outer' => 50],
            'K' => ['inner' => 10, 'outer' => 30],
            'M' => ['inner' => 3, 'outer' => 15],
            default => ['inner' => 20, 'outer' => 50],
        };
    }

    /**
     * Get stellar luminosity factor.
     */
    protected function getStellarLuminosity(string $class): string
    {
        return match ($class) {
            'O' => 'extremely_high',
            'B' => 'very_high',
            'A' => 'high',
            'F' => 'above_average',
            'G' => 'average',
            'K' => 'below_average',
            'M' => 'low',
            default => 'average',
        };
    }

    /**
     * Get stellar temperature.
     */
    protected function getStellarTemperature(string $class): int
    {
        return match ($class) {
            'O' => mt_rand(30000, 50000),
            'B' => mt_rand(10000, 30000),
            'A' => mt_rand(7500, 10000),
            'F' => mt_rand(6000, 7500),
            'G' => mt_rand(5200, 6000),
            'K' => mt_rand(3700, 5200),
            'M' => mt_rand(2400, 3700),
            default => 5500,
        };
    }

    /**
     * Calculate planet temperature based on orbital distance and stellar class.
     */
    protected function calculateTemperature(float $orbitalDistance, string $stellarClass): int
    {
        $baseStellarTemp = $this->getStellarTemperature($stellarClass);
        $luminosityFactor = $baseStellarTemp / 5500;

        // Simplified blackbody calculation
        $equilibriumTemp = 278 * pow($luminosityFactor, 0.25) * pow(1 / max(1, $orbitalDistance / 10), 0.5);

        // Add some variance
        return (int) ($equilibriumTemp * (mt_rand(80, 120) / 100));
    }

    /**
     * Get temperature label from Kelvin.
     */
    protected function getTemperatureLabel(int $kelvin): string
    {
        return match (true) {
            $kelvin < 100 => 'frozen',
            $kelvin < 200 => 'frigid',
            $kelvin < 273 => 'cold',
            $kelvin < 290 => 'cool',
            $kelvin < 310 => 'temperate',
            $kelvin < 330 => 'warm',
            $kelvin < 400 => 'hot',
            $kelvin < 600 => 'scorching',
            default => 'infernal',
        };
    }

    /**
     * Generate atmosphere type.
     */
    protected function generateAtmosphere(int $temperature, bool $inGoldilocks): string
    {
        if ($inGoldilocks && $temperature > 250 && $temperature < 350) {
            return $this->weightedRandom([
                'nitrogen-oxygen' => 30,
                'nitrogen' => 25,
                'carbon-dioxide' => 25,
                'methane' => 10,
                'ammonia' => 10,
            ]);
        }

        if ($temperature > 400) {
            return $this->weightedRandom([
                'sulfur-dioxide' => 40,
                'carbon-dioxide' => 30,
                'trace' => 30,
            ]);
        }

        return $this->weightedRandom([
            'nitrogen' => 30,
            'carbon-dioxide' => 30,
            'methane' => 20,
            'ammonia' => 10,
            'trace' => 10,
        ]);
    }

    /**
     * Calculate habitability score.
     */
    protected function calculateHabitability(array $attrs, bool $inGoldilocks): float
    {
        $score = 0.0;

        if ($inGoldilocks) {
            $score += 0.3;
        }

        $atmosphere = $attrs['atmosphere'] ?? 'none';
        if ($atmosphere === 'nitrogen-oxygen') {
            $score += 0.3;
        } elseif (in_array($atmosphere, ['nitrogen', 'carbon-dioxide'])) {
            $score += 0.1;
        }

        $temp = $attrs['temperature_kelvin'] ?? 0;
        if ($temp >= 273 && $temp <= 310) {
            $score += 0.2;
        } elseif ($temp >= 250 && $temp <= 330) {
            $score += 0.1;
        }

        if ($attrs['has_magnetic_field'] ?? false) {
            $score += 0.1;
        }

        $waterCoverage = $attrs['water_coverage'] ?? 0;
        if ($waterCoverage > 20 && $waterCoverage < 80) {
            $score += 0.1;
        }

        return min(1.0, $score);
    }

    /**
     * Generate ring deposits.
     */
    protected function generateRingDeposits(): array
    {
        $deposits = ['ice', 'dust'];

        if (mt_rand(1, 100) <= 40) {
            $deposits[] = 'silicates';
        }
        if (mt_rand(1, 100) <= 20) {
            $deposits[] = 'iron';
        }
        if (mt_rand(1, 100) <= 10) {
            $deposits[] = 'platinum';
        }

        return $deposits;
    }

    /**
     * Generate moon minerals.
     */
    protected function generateMoonMinerals(): array
    {
        $possible = ['iron', 'titanium', 'helium3', 'water_ice', 'silicates', 'rare_earth'];
        $count = mt_rand(1, 3);

        shuffle($possible);

        return array_slice($possible, 0, $count);
    }

    /**
     * Generate precursor cache contents.
     */
    protected function generatePrecursorContents(): array
    {
        $contents = [];

        $options = [
            'ancient_data_core',
            'precursor_alloy',
            'stasis_pod',
            'unknown_device',
            'star_map_fragment',
            'energy_crystal',
            'genetic_sample',
            'weapon_prototype',
        ];

        $count = mt_rand(1, 3);
        shuffle($options);

        return array_slice($options, 0, $count);
    }

    /**
     * Generate a hint for an ancient secret.
     */
    protected function generateSecretHint(string $secretType): string
    {
        return match ($secretType) {
            'star_map' => 'Ancient star charts reveal paths to forgotten worlds...',
            'technology' => 'Lost technology awaits those who can decipher the past...',
            'coordinates' => 'Hidden coordinates point to something significant...',
            'warning' => 'A dire warning from those who came before...',
            'origin_story' => 'The origins of the ancients are recorded here...',
            default => 'An ancient mystery awaits discovery...',
        };
    }

    /**
     * Check if POI is a star type.
     */
    protected function isStarType(PointOfInterest $poi): bool
    {
        return in_array($poi->type, [
            PointOfInterestType::STAR,
            PointOfInterestType::BLACK_HOLE,
            PointOfInterestType::SUPER_MASSIVE_BLACK_HOLE,
        ]);
    }

    /**
     * Weighted random selection.
     */
    protected function weightedRandom(array $weights): string
    {
        $total = array_sum($weights);
        $rand = mt_rand(1, $total);
        $cumulative = 0;

        foreach ($weights as $item => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return (string) $item;
            }
        }

        return (string) array_key_first($weights);
    }
}

<?php

namespace App\Http\Controllers\Api\Builders;

use App\Enums\Exploration\ScanLevel;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Colony;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Services\BarRumorService;
use App\Services\SystemScanService;

/**
 * Builds comprehensive star system response data.
 *
 * Extracted from StarSystemController to reduce file size and improve
 * maintainability. Uses the Builder pattern to construct complex nested
 * response structures based on visibility levels.
 */
class StarSystemResponseBuilder
{
    protected PointOfInterest $system;

    protected Player $player;

    protected int $visibilityLevel;

    protected bool $isFullyVisible;

    protected ScanLevel $scanLevelEnum;

    public function __construct(
        protected SystemScanService $scanService,
        protected BarRumorService $barRumorService
    ) {}

    /**
     * Initialize the builder with system and player context.
     */
    public function for(PointOfInterest $system, Player $player, int $visibilityLevel): self
    {
        $this->system = $system;
        $this->player = $player;
        $this->visibilityLevel = $visibilityLevel;
        $this->scanLevelEnum = ScanLevel::fromSensorLevel($visibilityLevel);
        $this->isFullyVisible = $visibilityLevel >= ScanLevel::FULL_VISIBILITY || $system->is_inhabited;

        return $this;
    }

    /**
     * Build the complete system response.
     */
    public function build(): array
    {
        $response = [
            'system' => $this->buildSystemInfo(),
            'visibility' => $this->buildVisibilityInfo(),
        ];

        // Sector info
        if ($this->system->sector) {
            $response['sector'] = $this->buildSectorInfo();
        }

        // Warp gates (visible at level 2+)
        if ($this->isFullyVisible || $this->visibilityLevel >= 2) {
            $response['warp_gates'] = $this->buildWarpGateData();
        }

        // Trading hub info
        if ($this->system->tradingHub && ($this->isFullyVisible || $this->visibilityLevel >= 3)) {
            $response['trading_hub'] = $this->buildTradingHubInfo();
        }

        // Orbital bodies
        $response['bodies'] = $this->buildBodiesData();

        // Star attributes
        if ($this->isFullyVisible || $this->visibilityLevel >= 1) {
            $response['star'] = $this->buildStarData();
        }

        // System defenses
        $systemAttrs = $this->system->attributes ?? [];
        if (isset($systemAttrs['system_defenses']) && ($this->isFullyVisible || $this->visibilityLevel >= 2)) {
            $response['defenses'] = $this->buildDefensesData($systemAttrs);
        }

        // Scan-specific data for uninhabited systems
        if (! $this->system->is_inhabited && $this->visibilityLevel > 0) {
            $response['scan_data'] = $this->scanService->getFilteredSystemData($this->system, $this->visibilityLevel);
        }

        // Facilities summary
        if ($this->isFullyVisible || $this->visibilityLevel >= 2) {
            $response['facilities'] = $this->buildFacilitiesData();
        }

        return $response;
    }

    /**
     * Build basic system info (always visible).
     */
    protected function buildSystemInfo(): array
    {
        return [
            'uuid' => $this->system->uuid,
            'name' => $this->system->name,
            'type' => $this->system->type?->value,
            'type_label' => $this->system->type?->label(),
            'x' => (float) $this->system->x,
            'y' => (float) $this->system->y,
            'is_inhabited' => $this->system->is_inhabited,
            'region' => $this->system->region?->value,
        ];
    }

    /**
     * Build visibility info.
     */
    protected function buildVisibilityInfo(): array
    {
        return [
            'level' => $this->visibilityLevel,
            'label' => $this->isFullyVisible ? 'Full Visibility' : $this->scanLevelEnum->label(),
            'is_inhabited_system' => $this->system->is_inhabited,
            'can_reveal_more' => ! $this->isFullyVisible && $this->visibilityLevel < 9,
        ];
    }

    /**
     * Build sector info.
     */
    protected function buildSectorInfo(): array
    {
        return [
            'uuid' => $this->system->sector->uuid,
            'name' => $this->system->sector->name,
            'grid' => [
                'x' => $this->system->sector->grid_x,
                'y' => $this->system->sector->grid_y,
            ],
        ];
    }

    /**
     * Build trading hub info.
     */
    protected function buildTradingHubInfo(): array
    {
        return [
            'uuid' => $this->system->tradingHub->uuid,
            'name' => $this->system->tradingHub->name,
            'type' => $this->system->tradingHub->type,
            'has_salvage_yard' => $this->system->tradingHub->has_salvage_yard ?? false,
            'has_cartographer' => $this->system->tradingHub->has_cartographer ?? false,
            'services' => $this->system->tradingHub->services ?? [],
        ];
    }

    /**
     * Build defenses data.
     */
    protected function buildDefensesData(array $systemAttrs): array
    {
        $defenses = $systemAttrs['system_defenses'];
        $defenses['is_fortified'] = $systemAttrs['is_fortified'] ?? false;
        $defenses['threat_assessment'] = $systemAttrs['threat_response'] ?? null;

        return $defenses;
    }

    /**
     * Build warp gate data based on visibility.
     */
    protected function buildWarpGateData(): array
    {
        $outgoing = $this->system->outgoingGates()
            ->where('is_hidden', false)
            ->with(['destinationPoi'])
            ->get();

        $incoming = $this->system->incomingGates()
            ->where('is_hidden', false)
            ->with(['sourcePoi'])
            ->get();

        $gates = $outgoing->merge($incoming);

        return [
            'count' => $gates->count(),
            'gates' => $gates->map(function ($gate) {
                $gateData = [
                    'uuid' => $gate->uuid,
                    'status' => $gate->status,
                ];

                $isOutgoing = $gate->source_poi_id === $this->system->id;
                $otherEnd = $isOutgoing ? $gate->destinationPoi : $gate->sourcePoi;

                if ($gate->status === 'active' && ($this->isFullyVisible || $this->visibilityLevel >= 2)) {
                    $gateData['destination'] = $otherEnd ? [
                        'uuid' => $otherEnd->uuid,
                        'name' => $this->isFullyVisible ? $otherEnd->name : 'Uncharted System',
                        'is_inhabited' => $otherEnd->is_inhabited,
                    ] : null;
                }

                if ($this->isFullyVisible || $this->visibilityLevel >= 8) {
                    $gateData['has_pirates'] = method_exists($gate, 'pirates')
                        ? $gate->pirates()->exists()
                        : false;
                }

                return $gateData;
            })->toArray(),
        ];
    }

    /**
     * Build orbital bodies data based on visibility.
     */
    protected function buildBodiesData(): array
    {
        $children = $this->system->children()
            ->with(['children', 'tradingHub'])
            ->orderBy('orbital_index')
            ->get();

        $bodies = [
            'planets' => [],
            'moons' => [],
            'asteroid_belts' => [],
            'stations' => [],
            'defense_platforms' => [],
            'derelicts' => [],
            'anomalies' => [],
            'other' => [],
        ];

        foreach ($children as $child) {
            $bodyData = $this->buildBodyData($child);
            $category = PoiCategorizer::categorize($child->type);

            // Add moons to planet data
            if ($category === 'planets') {
                $moons = $child->children()
                    ->where('type', PointOfInterestType::MOON)
                    ->get();

                if ($moons->isNotEmpty() && ($this->isFullyVisible || $this->visibilityLevel >= 5)) {
                    $bodyData['moons'] = $moons->map(fn ($moon) => $this->buildBodyData($moon))->toArray();
                }
            }

            $bodies[$category][] = $bodyData;
        }

        $bodies['summary'] = [
            'total_bodies' => $children->count(),
            'planets' => count($bodies['planets']),
            'moons' => count($bodies['moons']),
            'asteroid_belts' => count($bodies['asteroid_belts']),
            'stations' => count($bodies['stations']),
            'defense_platforms' => count($bodies['defense_platforms']),
            'derelicts' => count($bodies['derelicts']),
            'anomalies' => count($bodies['anomalies']),
        ];

        return $bodies;
    }

    /**
     * Build data for a single orbital body.
     */
    protected function buildBodyData(PointOfInterest $body): array
    {
        $attrs = $body->attributes ?? [];

        $data = [
            'uuid' => $body->uuid,
            'name' => $body->name,
            'type' => $body->type?->value,
            'type_label' => $body->type?->label(),
            'orbital_index' => $body->orbital_index,
        ];

        // Basic attributes (always visible for inhabited, level 1+ otherwise)
        if ($this->isFullyVisible || $this->visibilityLevel >= 1) {
            $data['is_inhabited'] = $body->is_inhabited ?? false;
            $data['has_colony'] = ($attrs['has_colony'] ?? false) ||
                Colony::where('poi_id', $body->id)->exists();

            if (isset($attrs['in_goldilocks_zone'])) {
                $data['in_goldilocks_zone'] = $attrs['in_goldilocks_zone'];
            }

            if (isset($attrs['temperature'])) {
                $data['temperature'] = $attrs['temperature'];
            }
            if (isset($attrs['atmosphere'])) {
                $data['atmosphere'] = $attrs['atmosphere'];
            }
            if (isset($attrs['has_rings'])) {
                $data['has_rings'] = $attrs['has_rings'];
            }

            $data['habitable'] = $attrs['habitable'] ?? false;
        }

        // Mining potential (level 3+)
        if ($this->isFullyVisible || $this->visibilityLevel >= 3) {
            if (isset($attrs['mineral_richness'])) {
                $data['mineral_richness'] = $attrs['mineral_richness'];
            }

            $deposits = array_merge(
                $attrs['mineral_deposits'] ?? [],
                $body->mineral_deposits ?? []
            );

            if (! empty($deposits)) {
                if ($this->visibilityLevel >= 4 || $this->isFullyVisible) {
                    $data['mineral_deposits'] = array_values(array_unique($deposits));
                } else {
                    $commonMinerals = ['iron', 'copper', 'titanium', 'nickel', 'cobalt', 'Fe', 'Cu', 'Ti', 'Ni', 'Co'];
                    $data['mineral_deposits'] = array_values(array_filter(
                        $deposits,
                        fn ($m) => in_array(strtolower($m), array_map('strtolower', $commonMinerals))
                    ));
                }
            }
        }

        // Deep scan data (level 7+)
        if ($this->isFullyVisible || $this->visibilityLevel >= 7) {
            if (isset($attrs['core_composition'])) {
                $data['core_composition'] = $attrs['core_composition'];
            }
            if (isset($attrs['terraformable'])) {
                $data['terraformable'] = $attrs['terraformable'];
                if (isset($attrs['terraform_difficulty'])) {
                    $data['terraform_difficulty'] = $attrs['terraform_difficulty'];
                }
            }
        }

        // Anomaly data (level 6+)
        if (($this->isFullyVisible || $this->visibilityLevel >= 6) && $body->type === PointOfInterestType::ANOMALY) {
            if (isset($attrs['anomaly_type'])) {
                $data['anomaly_type'] = $attrs['anomaly_type'];
            }
            if (isset($attrs['danger_level'])) {
                $data['danger_level'] = $attrs['danger_level'];
            }
        }

        // Ruins/ancient sites (level 6+)
        if ($this->isFullyVisible || $this->visibilityLevel >= 6) {
            if ($attrs['has_ruins'] ?? false) {
                $data['has_ruins'] = true;
                $data['ruin_type'] = $attrs['ruin_type'] ?? 'ancient';
            }
        }

        // Mining facilities (visible for inhabited systems or level 3+)
        if (($this->isFullyVisible || $this->visibilityLevel >= 3) && ($attrs['has_mining_facility'] ?? false)) {
            $data['mining_facility'] = $attrs['mining_facility'];
        }

        // Trading hub
        if ($body->tradingHub && ($this->isFullyVisible || $this->visibilityLevel >= 2)) {
            $data['trading_hub'] = [
                'uuid' => $body->tradingHub->uuid,
                'name' => $body->tradingHub->name,
            ];
        }

        return $data;
    }

    /**
     * Build star-specific data.
     */
    protected function buildStarData(): array
    {
        $attrs = $this->system->attributes ?? [];

        $starData = [
            'type' => $this->system->type?->label(),
        ];

        if (isset($attrs['stellar_class'])) {
            $starData['stellar_class'] = $attrs['stellar_class'];
            $starData['stellar_description'] = $this->getStellarDescription($attrs['stellar_class']);
        }

        if (isset($attrs['luminosity'])) {
            $starData['luminosity'] = $attrs['luminosity'];
        }

        if (isset($attrs['temperature'])) {
            $starData['temperature'] = $attrs['temperature'];
        }

        if (($this->isFullyVisible || $this->visibilityLevel >= 3) && isset($attrs['goldilocks_zone'])) {
            $starData['goldilocks_zone'] = $attrs['goldilocks_zone'];
        }

        return $starData;
    }

    /**
     * Build facilities data including bars and rumors.
     */
    protected function buildFacilitiesData(): array
    {
        $facilities = [
            'summary' => [
                'has_trading_hub' => $this->system->tradingHub !== null,
                'has_shipyard' => false,
                'has_salvage_yard' => false,
                'has_cartographer' => false,
                'has_bars' => $this->system->is_inhabited,
                'trading_stations_count' => 0,
                'defense_platforms_count' => 0,
            ],
            'services' => [],
        ];

        // Count station types from children
        $children = $this->system->children()->get();
        foreach ($children as $child) {
            if ($child->type === PointOfInterestType::SHIPYARD) {
                $facilities['summary']['has_shipyard'] = true;
            } elseif ($child->type === PointOfInterestType::SALVAGE_YARD) {
                $facilities['summary']['has_salvage_yard'] = true;
            } elseif ($child->type === PointOfInterestType::TRADING_STATION) {
                $facilities['summary']['trading_stations_count']++;
            } elseif ($child->type === PointOfInterestType::DEFENSE_PLATFORM) {
                $facilities['summary']['defense_platforms_count']++;
            }
        }

        // Check trading hub services
        if ($this->system->tradingHub) {
            $facilities['summary']['has_cartographer'] = $this->system->tradingHub->has_cartographer ?? false;
            $facilities['services'] = $this->system->tradingHub->services ?? [];
        }

        // Bars section
        if ($this->system->is_inhabited && $this->isFullyVisible) {
            $facilities['bars'] = $this->buildBarsData();
        }

        return $facilities;
    }

    /**
     * Build bars data with available rumors.
     */
    protected function buildBarsData(): array
    {
        $barNames = BarNameGenerator::generate($this->system);

        $bars = [];
        foreach ($barNames as $index => $name) {
            $bars[] = [
                'name' => $name,
                'location' => $index === 0 ? 'Main Trading Hub' : 'Orbital Station '.$index,
                'atmosphere' => BarNameGenerator::randomAtmosphere(),
            ];
        }

        $rumors = $this->barRumorService->getRumors($this->player, $this->system);

        return [
            'count' => count($bars),
            'establishments' => $bars,
            'rumors' => $rumors,
            'tip' => 'Visit the bar to overhear local gossip and potentially valuable intel.',
        ];
    }

    /**
     * Get human-readable stellar class description.
     */
    protected function getStellarDescription(string $class): string
    {
        return match ($class) {
            'O' => 'O-class blue supergiant',
            'B' => 'B-class blue giant',
            'A' => 'A-class white star',
            'F' => 'F-class yellow-white star',
            'G' => 'G-class yellow dwarf (Sun-like)',
            'K' => 'K-class orange dwarf',
            'M' => 'M-class red dwarf',
            default => "{$class}-class star",
        };
    }
}

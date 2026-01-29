<?php

namespace App\Models;

use App\Enums\Exploration\ScanLevel;
use App\Enums\Galaxy\RegionType;
use App\Enums\PointsOfInterest\PointOfInterestStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Faker\Providers\AnomalyNameProvider;
use App\Faker\Providers\BlackHoleNameProvider;
use App\Faker\Providers\NebulaNameProvider;
use App\Faker\Providers\PlanetNameProvider;
use App\Faker\Providers\StarNameProvider;
use App\Services\SystemScanService;
use App\Traits\HasUuidAndVersion;
use Assert\AssertionFailedException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;

class PointOfInterest extends Model
{
    use HasFactory, HasUuidAndVersion;

    /**
     * @var string
     */
    protected $table = 'points_of_interest';

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'sector_id',
        'parent_poi_id',
        'orbital_index',
        'type',
        'status',
        'x',
        'y',
        'name',
        'attributes',
        'is_hidden',
        'is_inhabited',
        'region',
        'is_fortified',
        'owner_id',
        'version',
    ];

    protected $casts = [
        'attributes' => 'array',
        'mineral_deposits' => 'array',
        'is_hidden' => 'boolean',
        'is_inhabited' => 'boolean',
        'is_fortified' => 'boolean',
        'status' => PointOfInterestStatus::class,
        'type' => PointOfInterestType::class,
        'region' => RegionType::class,
    ];

    /**
     * Bulk create POIs for a galaxy from a list of points.
     * OPTIMIZED: Uses batch insert instead of individual creates.
     *
     * @throws AssertionFailedException|\Random\RandomException
     */
    public static function createPointsForGalaxy(Galaxy $galaxy, array $points): void
    {
        if (empty($points)) {
            return;
        }

        $now = now();
        $version = config('game_config.feature.stamp_version', true) && file_exists(base_path('VERSION'))
            ? trim(file_get_contents(base_path('VERSION')))
            : null;

        // Pre-generate all POI data in memory
        $batchData = [];
        foreach ($points as $point) {
            $type = self::setPOIType();
            $isHidden = self::setHiddenPOI();

            // Generate name based on type
            $name = match ($type) {
                PointOfInterestType::STAR->value => StarNameProvider::generateStarName(),
                PointOfInterestType::NEBULA->value => NebulaNameProvider::generateNebulaName(),
                PointOfInterestType::ROGUE_PLANET->value => PlanetNameProvider::generatePlanetName(),
                PointOfInterestType::BLACK_HOLE->value => BlackHoleNameProvider::generateBlackHoleName(),
                PointOfInterestType::ANOMALY->value => AnomalyNameProvider::generateAnomalyName(),
            };

            $batchData[] = [
                'uuid' => (string) Str::uuid(),
                'galaxy_id' => $galaxy->id,
                'type' => $type,
                'status' => PointOfInterestStatus::DRAFT->value,
                'x' => $point[0],
                'y' => $point[1],
                'name' => $name,
                'attributes' => json_encode([]),
                'is_hidden' => $isHidden,
                'version' => $version,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Batch insert in chunks of 500 to avoid query size limits
        $chunks = array_chunk($batchData, 500);
        foreach ($chunks as $chunk) {
            DB::table('points_of_interest')->insert($chunk);
        }
    }

    /**
     * @return mixed
     *
     * @throws AssertionFailedException
     */
    private static function setPOIType(): int
    {
        $typeChooser = new WeightedRandomGenerator;
        $typeChooser->registerValues([
            PointOfInterestType::STAR->value => 60,
            PointOfInterestType::NEBULA->value => 20,
            PointOfInterestType::ROGUE_PLANET->value => 10,
            PointOfInterestType::BLACK_HOLE->value => 5,
            PointOfInterestType::ANOMALY->value => 5,
        ]);

        return $typeChooser->generate();
    }

    /**
     * @throws AssertionFailedException
     */
    private static function setHiddenPOI(): bool
    {
        $hiddenChooser = new WeightedRandomGenerator;
        $hiddenChooser->registerValues([
            true => 10,
            false => 90,
        ]);

        return $hiddenChooser->generate();
    }

    /**
     *--------------------------------------------------------------------------
     * Relationships
     *--------------------------------------------------------------------------
     */
    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    /**
     * Sector this POI belongs to
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * Parent POI (star for planet, planet for moon)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'parent_poi_id');
    }

    /**
     * Child POIs (planets for star, moons for planet)
     */
    public function children(): HasMany
    {
        return $this->hasMany(PointOfInterest::class, 'parent_poi_id')
            ->orderBy('orbital_index');
    }

    /**
     * Outgoing warp gates from this POI
     */
    public function outgoingGates(): HasMany
    {
        return $this->hasMany(WarpGate::class, 'source_poi_id');
    }

    /**
     * Incoming warp gates to this POI
     */
    public function incomingGates(): HasMany
    {
        return $this->hasMany(WarpGate::class, 'destination_poi_id');
    }

    /**
     * Trading hub at this POI (if any)
     */
    public function tradingHub()
    {
        return $this->hasOne(TradingHub::class, 'poi_id');
    }

    /**
     * Stellar Cartographer shop at this POI (if any)
     */
    public function stellarCartographer()
    {
        return $this->hasOne(StellarCartographer::class, 'poi_id');
    }

    /**
     * System defenses at this POI (for fortified systems)
     */
    public function systemDefenses()
    {
        return $this->hasMany(SystemDefense::class, 'poi_id');
    }

    /**
     * Owner of this POI (for mining restrictions)
     */
    public function owner()
    {
        return $this->belongsTo(Player::class, 'owner_id');
    }

    /**
     *--------------------------------------------------------------------------
     * Query Scopes
     *--------------------------------------------------------------------------
     */

    /**
     * Scope to filter inhabited systems
     */
    public function scopeInhabited($query)
    {
        return $query->where('is_inhabited', true);
    }

    /**
     * Scope to filter uninhabited systems
     */
    public function scopeUninhabited($query)
    {
        return $query->where('is_inhabited', false);
    }

    /**
     * Scope to filter by star systems only
     */
    public function scopeStars($query)
    {
        return $query->where('type', PointOfInterestType::STAR);
    }

    /**
     * Scope to filter by core region
     */
    public function scopeCore($query)
    {
        return $query->where('region', RegionType::CORE);
    }

    /**
     * Scope to filter by outer region
     */
    public function scopeOuter($query)
    {
        return $query->where('region', RegionType::OUTER);
    }

    /**
     * Scope to filter fortified systems
     */
    public function scopeFortified($query)
    {
        return $query->where('is_fortified', true);
    }

    /**
     *--------------------------------------------------------------------------
     * Helpers
     *--------------------------------------------------------------------------
     */

    /**
     * Get the root star of this POI's system
     */
    public function getRootStar(): ?PointOfInterest
    {
        if ($this->type === PointOfInterestType::STAR) {
            return $this;
        }

        if ($this->parent && $this->parent->type === PointOfInterestType::STAR) {
            return $this->parent;
        }

        if ($this->parent && $this->parent->parent) {
            return $this->parent->parent;
        }

        return null;
    }

    /**
     * Check if this POI has child objects
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Get display icon for this POI type
     */
    public function getDisplayIcon(): string
    {
        return match ($this->type) {
            PointOfInterestType::STAR => '★',
            PointOfInterestType::GAS_GIANT, PointOfInterestType::HOT_JUPITER => '◉',
            PointOfInterestType::ICE_GIANT => '◎',
            PointOfInterestType::TERRESTRIAL, PointOfInterestType::SUPER_EARTH => '●',
            PointOfInterestType::LAVA => '◆',
            PointOfInterestType::OCEAN => '◐',
            PointOfInterestType::MOON => '○',
            PointOfInterestType::ASTEROID_BELT => '∴',
            PointOfInterestType::ASTEROID => '·',
            PointOfInterestType::BLACK_HOLE => '◯',
            PointOfInterestType::SUPER_MASSIVE_BLACK_HOLE => '◉',
            PointOfInterestType::NEBULA => '∞',
            PointOfInterestType::ANOMALY => '?',
            PointOfInterestType::ROGUE_PLANET => '●',
            PointOfInterestType::COMET => '☄',
            default => '•',
        };
    }

    /**
     * Get display color for this POI type (for console rendering)
     */
    public function getDisplayColor(): string
    {
        return match ($this->type) {
            PointOfInterestType::GAS_GIANT, PointOfInterestType::HOT_JUPITER, PointOfInterestType::ICE_GIANT => 'gas_giant',
            PointOfInterestType::MOON => 'moon',
            PointOfInterestType::BLACK_HOLE, PointOfInterestType::SUPER_MASSIVE_BLACK_HOLE => 'black_hole',
            PointOfInterestType::NEBULA => 'nebula',
            PointOfInterestType::ANOMALY => 'anomaly',
            default => 'planet',
        };
    }

    /**
     * Get celestial color for universe-level objects
     */
    public function getCelestialColor(): string
    {
        if ($this->type === PointOfInterestType::STAR) {
            $stellarClass = $this->attributes['stellar_class'] ?? null;
            if ($stellarClass) {
                return $stellarClass;
            }

            return $this->children()->exists() ? 'star_with_planets' : 'star_no_planets';
        }

        return match ($this->type) {
            PointOfInterestType::BLACK_HOLE, PointOfInterestType::SUPER_MASSIVE_BLACK_HOLE => 'black_hole',
            PointOfInterestType::NEBULA => 'nebula',
            PointOfInterestType::ANOMALY => 'anomaly',
            PointOfInterestType::ROGUE_PLANET => 'planet',
            PointOfInterestType::COMET => 'highlight',
            default => 'star_no_planets',
        };
    }

    /**
     * Check if this POI is in the core region
     */
    public function isInCoreRegion(): bool
    {
        return $this->region === RegionType::CORE;
    }

    /**
     * Check if this POI is in the outer region
     */
    public function isInOuterRegion(): bool
    {
        return $this->region === RegionType::OUTER;
    }

    /**
     * Get active defenses at this POI
     */
    public function getActiveDefenses()
    {
        return $this->systemDefenses()->active()->get();
    }

    /**
     * Calculate total defense strength at this POI
     */
    public function getTotalDefenseStrength(): int
    {
        return $this->getActiveDefenses()->sum(fn ($defense) => $defense->calculateDamage());
    }

    /**
     * Check if player can mine at this POI
     */
    public function canPlayerMine(Player $player): bool
    {
        // Outer region is always mineable
        if ($this->isInOuterRegion()) {
            return true;
        }

        // Core region requires ownership or no owner
        if ($this->owner_id === null) {
            return true;
        }

        return $this->owner_id === $player->id;
    }

    /**
     *--------------------------------------------------------------------------
     * Scan-related Methods
     *--------------------------------------------------------------------------
     */

    /**
     * Get scan data for this POI at a specific level.
     * Uses SystemScanService to generate filtered data.
     *
     * @param  int  $level  The scan level (1-9)
     * @return array Scan data visible at this level
     */
    public function getScanDataForLevel(int $level): array
    {
        return app(SystemScanService::class)->getFilteredSystemData($this, $level);
    }

    /**
     * Get the baseline scan level for this POI based on region.
     *
     * @return int Baseline scan level
     */
    public function getBaselineScanLevel(): int
    {
        return app(SystemScanService::class)->getBaselineScanLevel($this);
    }

    /**
     * System scans for this POI.
     */
    public function systemScans(): HasMany
    {
        return $this->hasMany(\App\Models\SystemScan::class, 'poi_id');
    }

    /**
     * Get scan display color for baseline level.
     *
     * @return string Hex color
     */
    public function getScanColor(): string
    {
        $level = $this->getBaselineScanLevel();

        return ScanLevel::fromSensorLevel($level)->color();
    }

    /**
     * Get scan display opacity for baseline level.
     *
     * @return float Opacity (0.0-1.0)
     */
    public function getScanOpacity(): float
    {
        $level = $this->getBaselineScanLevel();

        return ScanLevel::fromSensorLevel($level)->opacity();
    }
}

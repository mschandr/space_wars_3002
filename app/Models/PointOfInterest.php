<?php

namespace App\Models;

use App\Enums\PointsOfInterest\PointOfInterestStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Faker\Providers\AnomalyNameProvider;
use App\Faker\Providers\BlackHoleNameProvider;
use App\Faker\Providers\NebulaNameProvider;
use App\Faker\Providers\PlanetNameProvider;
use App\Faker\Providers\StarNameProvider;
use App\Traits\HasUuidAndVersion;
use Assert\AssertionFailedException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;

class PointOfInterest extends Model
{
    use HasUuidAndVersion;

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
        'version',
    ];

    protected $casts = [
        'attributes' => 'array',
        'is_hidden' => 'boolean',
        'status' => PointOfInterestStatus::class,
        'type' => PointOfInterestType::class,
    ];

    /**
     * Bulk create POIs for a galaxy from a list of points.
     *
     * @throws AssertionFailedException|\Random\RandomException
     */
    public static function createPointsForGalaxy(Galaxy $galaxy, array $points): void
    {
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

            self::create([
                'galaxy_id' => $galaxy->id,
                'type' => $type,
                'status' => PointOfInterestStatus::DRAFT,
                'x' => $point[0],
                'y' => $point[1],
                'name' => $name,
                'attributes' => [],
                'is_hidden' => $isHidden,
            ]);
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
}

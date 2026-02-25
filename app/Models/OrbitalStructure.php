<?php

namespace App\Models;

use App\Enums\OrbitalStructureType;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrbitalStructure extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'poi_id',
        'player_id',
        'structure_type',
        'level',
        'status',
        'name',
        'construction_progress',
        'construction_started_at',
        'construction_completed_at',
        'health',
        'max_health',
        'attributes',
        'credits_per_cycle',
        'minerals_per_cycle',
    ];

    protected $casts = [
        'structure_type' => OrbitalStructureType::class,
        'level' => 'integer',
        'construction_progress' => 'integer',
        'construction_started_at' => 'datetime',
        'construction_completed_at' => 'datetime',
        'health' => 'integer',
        'max_health' => 'integer',
        'attributes' => 'array',
        'credits_per_cycle' => 'integer',
        'minerals_per_cycle' => 'integer',
    ];

    public function poi(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'poi_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function isOperational(): bool
    {
        return $this->status === 'operational';
    }

    /**
     * Apply damage to this structure.
     */
    public function takeDamage(int $damage): void
    {
        $this->health = max(0, $this->health - $damage);

        if ($this->health <= 0) {
            $this->status = 'destroyed';
        } elseif ($this->health < $this->max_health * 0.5) {
            $this->status = 'damaged';
        }

        $this->save();
    }

    /**
     * Calculate defense damage output per round.
     */
    public function calculateDamage(): int
    {
        if (! $this->isOperational() || $this->structure_type !== OrbitalStructureType::ORBITAL_DEFENSE) {
            return 0;
        }

        $baseDamage = $this->structure_type->effects()['damage_per_round'];
        $levelMultiplier = 1 + (($this->level - 1) * 0.3);

        return (int) ($baseDamage * $levelMultiplier);
    }

    /**
     * Calculate mining extraction rate per cycle.
     */
    public function calculateExtraction(): int
    {
        if (! $this->isOperational() || $this->structure_type !== OrbitalStructureType::MINING_PLATFORM) {
            return 0;
        }

        $baseRate = $this->structure_type->effects()['extraction_rate'];
        $levelMultiplier = 1 + (($this->level - 1) * 0.3);

        return (int) ($baseRate * $levelMultiplier);
    }

    /**
     * Advance construction progress.
     */
    public function advanceConstruction(int $amount = 10): void
    {
        $this->construction_progress = min(100, $this->construction_progress + $amount);

        if ($this->construction_progress >= 100) {
            $this->status = 'operational';
            $this->construction_completed_at = now();

            $operatingCosts = $this->structure_type->operatingCosts();
            $levelMultiplier = 1 + (($this->level - 1) * 0.2);
            $this->credits_per_cycle = (int) ($operatingCosts['credits'] * $levelMultiplier);
            $this->minerals_per_cycle = (int) ($operatingCosts['minerals'] * $levelMultiplier);
        }

        $this->save();
    }

    /**
     * Get comprehensive structure definitions (mirrors ColonyBuilding pattern).
     */
    public static function getStructureDefinitions(): array
    {
        return [
            'orbital_defense' => [
                'name' => 'Orbital Defense Platform',
                'description' => 'Armed platform that defends the orbital body from hostile ships',
                'base_cost' => ['credits' => 50000, 'minerals' => 10000],
                'base_health' => 500,
                'max_per_body' => 4,
                'effects' => ['defense_rating' => 100, 'damage_per_round' => 25],
                'operating_costs' => ['credits' => 100, 'minerals' => 5],
            ],
            'magnetic_mine' => [
                'name' => 'Magnetic Mine',
                'description' => 'Defensive deterrent that attaches to hostile ship hulls causing explosive decompression',
                'base_cost' => ['credits' => 5000, 'minerals' => 2000],
                'base_health' => 50,
                'max_per_body' => 10,
                'effects' => ['mine_damage' => 150, 'decompression' => true],
                'operating_costs' => ['credits' => 0, 'minerals' => 0],
            ],
            'mining_platform' => [
                'name' => 'Mining Platform',
                'description' => 'Orbital platform that passively extracts minerals from the body below',
                'base_cost' => ['credits' => 30000, 'minerals' => 8000],
                'base_health' => 300,
                'max_per_body' => 2,
                'effects' => ['extraction_rate' => 50, 'storage' => 500],
                'operating_costs' => ['credits' => 50, 'minerals' => 0],
            ],
            'orbital_base' => [
                'name' => 'Orbital Base',
                'description' => 'Full orbital station with docking, cargo storage, and repair facilities',
                'base_cost' => ['credits' => 100000, 'minerals' => 20000],
                'base_health' => 1000,
                'max_per_body' => 1,
                'effects' => ['docking_slots' => 4, 'cargo_capacity' => 2000, 'repair' => true],
                'operating_costs' => ['credits' => 200, 'minerals' => 10],
            ],
        ];
    }
}

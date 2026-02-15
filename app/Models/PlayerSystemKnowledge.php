<?php

namespace App\Models;

use App\Enums\Exploration\KnowledgeLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks what a player knows about each star system.
 *
 * Knowledge levels range from UNKNOWN (0) to VISITED (4).
 * Once a system is discovered, it never drops below DETECTED (1).
 * VISITED knowledge is permanent and never decays.
 */
class PlayerSystemKnowledge extends Model
{
    use HasFactory;

    protected $table = 'player_system_knowledge';

    protected $fillable = [
        'player_id',
        'poi_id',
        'knowledge_level',
        'source',
        'source_poi_id',
        'acquired_at',
        'has_pirate_warning',
        'pirate_warning_data',
        'services_data',
        'metadata',
    ];

    protected $casts = [
        'knowledge_level' => 'integer',
        'acquired_at' => 'datetime',
        'has_pirate_warning' => 'boolean',
        'pirate_warning_data' => 'array',
        'services_data' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Valid knowledge sources.
     */
    public const SOURCES = [
        'sensor',    // Real-time sensor detection
        'warp_lane', // Discovered as endpoint of known warp lane
        'chart',     // Purchased from stellar cartographer
        'rumor',     // Heard from precursor rumor / NPC intel
        'visit',     // Player physically visited
        'scan',      // System scan data
        'spawn',     // Initial knowledge at player spawn
    ];

    /**
     * Sources that never decay.
     */
    public const PERMANENT_SOURCES = ['visit', 'spawn', 'warp_lane'];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function pointOfInterest(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'poi_id');
    }

    public function sourcePointOfInterest(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'source_poi_id');
    }

    /**
     * Get the knowledge level as an enum.
     */
    public function getKnowledgeLevelEnum(): KnowledgeLevel
    {
        return KnowledgeLevel::from($this->knowledge_level);
    }

    /**
     * Check if this knowledge record has a permanent source.
     */
    public function isPermanent(): bool
    {
        return in_array($this->source, self::PERMANENT_SOURCES, true);
    }

    /**
     * Scope to filter records for a specific player.
     */
    public function scopeForPlayer($query, int $playerId)
    {
        return $query->where('player_id', $playerId);
    }

    /**
     * Scope to filter known systems (level > 0).
     */
    public function scopeKnownSystems($query)
    {
        return $query->where('knowledge_level', '>', KnowledgeLevel::UNKNOWN->value);
    }

    /**
     * Scope to filter systems with pirate warnings.
     */
    public function scopeWithPirateWarnings($query)
    {
        return $query->where('has_pirate_warning', true);
    }
}

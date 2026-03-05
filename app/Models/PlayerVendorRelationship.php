<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerVendorRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'vendor_profile_id',
        'goodwill',
        'shady_dealings',
        'visit_count',
        'markup_modifier',
        'is_locked_out',
        'last_interaction_at',
    ];

    protected $casts = [
        'goodwill' => 'integer',
        'shady_dealings' => 'integer',
        'visit_count' => 'integer',
        'markup_modifier' => 'decimal:4',
        'is_locked_out' => 'boolean',
        'last_interaction_at' => 'datetime',
    ];

    /**
     * Get the player for this relationship
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * Get the vendor profile for this relationship
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_profile_id');
    }

    /**
     * Record a successful trade (increases goodwill)
     */
    public function recordTrade(int $goodwillAmount = 1): void
    {
        $this->increment('goodwill', $goodwillAmount);
        $this->increment('visit_count');
        $this->update(['last_interaction_at' => now()]);
    }

    /**
     * Record a shady trade
     */
    public function recordShadyTrade(int $shadyAmount = 1): void
    {
        $this->increment('shady_dealings', $shadyAmount);
        $this->increment('visit_count');
        $this->update(['last_interaction_at' => now()]);
    }

    /**
     * Lock out the player from this vendor
     */
    public function lockOut(): void
    {
        $this->update(['is_locked_out' => true]);
    }

    /**
     * Unlock the player at this vendor
     */
    public function unlock(): void
    {
        $this->update(['is_locked_out' => false]);
    }
}

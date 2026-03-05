<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalaxyVendorState extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'vendor_profile_id',
        'markup_modifier',
        'interaction_count',
        'average_satisfaction',
        'price_multiplier_base',
    ];

    protected $casts = [
        'markup_modifier' => 'decimal:4',
        'average_satisfaction' => 'decimal:2',
    ];

    /**
     * Get the galaxy this vendor state belongs to
     */
    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    /**
     * Get the vendor profile template
     */
    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    /**
     * Calculate effective markup for this vendor in this galaxy
     * Combines template base markup with galaxy-specific modifications
     */
    public function getEffectiveMarkup(): float
    {
        $baseMarkup = $this->vendorProfile->markup_base ?? 0;
        return $baseMarkup + (float)$this->markup_modifier;
    }
}

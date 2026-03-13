<?php

namespace App\Models;

use App\Enums\Vendor\DialogueLineType;
use App\Enums\Vendor\InteractionBucket;
use App\Enums\Vendor\InventoryContext;
use App\Enums\Vendor\TransactionContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDialogue extends Model
{
    protected $table = 'vendor_dialogue';

    protected $fillable = [
        'galaxy_vendor_profile_id',
        'line_type',
        'interaction_bucket',
        'transaction_context',
        'inventory_context',
        'line_text',
        'weight',
        'generation_version',
    ];

    protected $casts = [
        'line_type' => DialogueLineType::class,
        'interaction_bucket' => InteractionBucket::class,
        'transaction_context' => TransactionContext::class,
        'inventory_context' => InventoryContext::class,
        'weight' => 'decimal:4',
        'generation_version' => 'integer',
    ];

    /**
     * Relationship to GalaxyVendorProfile.
     */
    public function galaxyVendorProfile(): BelongsTo
    {
        return $this->belongsTo(GalaxyVendorProfile::class, 'galaxy_vendor_profile_id');
    }

    /**
     * Scope: Filter by vendor.
     */
    public function scopeForVendor($query, $galaxyVendorProfileId)
    {
        return $query->where('galaxy_vendor_profile_id', $galaxyVendorProfileId);
    }

    /**
     * Scope: Filter by line type.
     */
    public function scopeByLineType($query, DialogueLineType $lineType)
    {
        return $query->where('line_type', $lineType->value);
    }

    /**
     * Scope: Filter by interaction bucket.
     */
    public function scopeByBucket($query, InteractionBucket $bucket)
    {
        return $query->where('interaction_bucket', $bucket->value);
    }

    /**
     * Scope: Filter by generation version.
     */
    public function scopeByVersion($query, int $version)
    {
        return $query->where('generation_version', $version);
    }

    /**
     * Get dialogue for a specific vendor, line type, and interaction bucket.
     * Returns weighted random selection.
     */
    public static function getDialogue(
        int $galaxyVendorProfileId,
        DialogueLineType $lineType,
        InteractionBucket $bucket,
        TransactionContext $transactionContext = TransactionContext::NEUTRAL,
        InventoryContext $inventoryContext = InventoryContext::NONE,
    ): ?self {
        $lines = self::forVendor($galaxyVendorProfileId)
            ->byLineType($lineType)
            ->byBucket($bucket)
            ->where('transaction_context', $transactionContext->value)
            ->whereIn('inventory_context', [$inventoryContext->value, InventoryContext::NONE->value])
            ->orderBy('id')
            ->get();

        if ($lines->isEmpty()) {
            return null;
        }

        // Weighted random selection
        $totalWeight = $lines->sum('weight');
        $random = rand(0, (int)($totalWeight * 10000)) / 10000;

        $cumulative = 0;
        foreach ($lines as $line) {
            $cumulative += $line->weight;
            if ($random <= $cumulative) {
                return $line;
            }
        }

        return $lines->last();
    }

    /**
     * Get all dialogue for a vendor with optional filters.
     */
    public static function getVendorDialogue(
        int $galaxyVendorProfileId,
        ?DialogueLineType $lineType = null,
        ?InteractionBucket $bucket = null
    ) {
        $query = self::forVendor($galaxyVendorProfileId);

        if ($lineType) {
            $query->byLineType($lineType);
        }

        if ($bucket) {
            $query->byBucket($bucket);
        }

        return $query->orderBy('line_type')
            ->orderBy('interaction_bucket')
            ->get();
    }
}

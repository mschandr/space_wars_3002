<?php

namespace App\Services;

use App\Enums\Vendor\InteractionBucket;
use App\Enums\Vendor\InventoryContext;
use App\Enums\Vendor\TransactionContext;
use App\Models\GalaxyVendorProfile;
use App\Models\VendorDialogue;
use Illuminate\Support\Collection;

/**
 * Vendor Dialogue Service
 *
 * Handles:
 * - Retrieval of generated dialogue lines
 * - Deterministic selection (not random)
 * - Fallback dialogue system
 * - Interaction bucket mapping
 */
class VendorDialogueService
{
    /**
     * Static fallback dialogue by service type.
     * Used when generated dialogue is unavailable.
     */
    private const STATIC_FALLBACKS = [
        'salvage_yard' => [
            'greeting' => 'Welcome. See anything worth salvaging?',
            'inventory_pitch' => 'Got some quality merchandise in stock.',
            'deal_accepted' => 'Not a bad deal.',
            'deal_rejected' => 'Your loss.',
            'farewell' => 'Try not to get yourself spaced.',
        ],
        'shipyard' => [
            'greeting' => 'Looking for a new hull?',
            'inventory_pitch' => 'This vessel is built to last.',
            'deal_accepted' => 'Fine choice. You won\'t regret it.',
            'deal_rejected' => 'Come back when you\'re ready.',
            'farewell' => 'Come back when you want something faster.',
        ],
        'trading_hub' => [
            'greeting' => 'Looking to trade?',
            'inventory_pitch' => 'I have exactly what you need.',
            'deal_accepted' => 'Pleasure doing business.',
            'deal_rejected' => 'Your decision.',
            'farewell' => 'Safe travels.',
        ],
        'market' => [
            'greeting' => 'Take a look around.',
            'inventory_pitch' => 'Everything here is quality goods.',
            'deal_accepted' => 'Good choice.',
            'deal_rejected' => 'Suit yourself.',
            'farewell' => 'Come again.',
        ],
        'repair_yard' => [
            'greeting'         => 'What needs fixing?',
            'inventory_pitch'  => 'I have the parts you need.',
            'deal_accepted'    => 'I\'ll have it running.',
            'deal_rejected'    => 'Your ship, your problem.',
            'farewell'         => 'Try not to break anything else.',
        ],
        'bartender' => [
            'greeting'         => 'What are you having?',
            'inventory_pitch'  => 'I might know something about that.',
            'deal_accepted'    => 'Enjoy.',
            'deal_rejected'    => 'Suit yourself.',
            'farewell'         => 'Safe travels.',
        ],
        'information_broker' => [
            'greeting'         => 'Information has a price.',
            'inventory_pitch'  => 'I have what you\'re looking for.',
            'deal_accepted'    => 'Use it wisely.',
            'deal_rejected'    => 'You\'ll wish you had bought it.',
            'farewell'         => 'Watch your back.',
        ],
    ];

    /**
     * Get dialogue with automatic fallback chain (§10.5).
     *
     * Fallback order:
     * 1. exact line type + exact bucket + exact transaction context + inventory_context = none
     * 2. exact line type + exact bucket + transaction_context = neutral
     * 3. exact line type + repeat_customer + inventory_context = none
     * 4. static hardcoded fallback by service type
     */
    public function getDialogueWithFallback(
        GalaxyVendorProfile $vendor,
        string $lineType,
        int $playerId,
        int $interactionCount,
        TransactionContext $transactionContext = TransactionContext::NEUTRAL,
        InventoryContext $inventoryContext = InventoryContext::NONE,
    ): string {
        $bucket = $this->mapInteractionBucket($interactionCount);

        // Tier 1: exact bucket + exact transaction context + inventory_context IN (requested, none)
        $lines = $this->getDialogueLines($vendor, $lineType, $bucket, $transactionContext, $inventoryContext);
        if (!$lines->isEmpty()) {
            return $this->selectDeterministicLine($lines, $playerId, $vendor->id, $interactionCount, $lineType)->line_text;
        }

        // Tier 2: exact bucket + neutral transaction context
        if ($transactionContext !== TransactionContext::NEUTRAL) {
            $lines = $this->getDialogueLines($vendor, $lineType, $bucket, TransactionContext::NEUTRAL, $inventoryContext);
            if (!$lines->isEmpty()) {
                return $this->selectDeterministicLine($lines, $playerId, $vendor->id, $interactionCount, $lineType)->line_text;
            }
        }

        // Tier 3: repeat_customer + inventory_context = none (broadest stored fallback)
        $lines = $this->getDialogueLines($vendor, $lineType, 'repeat_customer', $transactionContext, InventoryContext::NONE);
        if (!$lines->isEmpty()) {
            return $this->selectDeterministicLine($lines, $playerId, $vendor->id, 4, $lineType)->line_text;
        }

        // Tier 4: static fallback by service type
        return self::STATIC_FALLBACKS[$vendor->service_type][$lineType] ?? 'Hmph.';
    }

    /**
     * Get dialogue lines for a vendor using the §10.2 query shape.
     * inventory_context falls back to 'none' automatically.
     */
    public function getDialogueLines(
        GalaxyVendorProfile $vendor,
        string $lineType,
        string $interactionBucket,
        TransactionContext $transactionContext = TransactionContext::NEUTRAL,
        InventoryContext $inventoryContext = InventoryContext::NONE,
    ): Collection {
        return $vendor->dialogueLines()
            ->where('line_type', $lineType)
            ->where('interaction_bucket', $interactionBucket)
            ->where('transaction_context', $transactionContext->value)
            ->whereIn('inventory_context', [$inventoryContext->value, InventoryContext::NONE->value])
            ->orderBy('id')
            ->get();
    }

    /**
     * Select a dialogue line deterministically.
     *
     * Uses CRC32 hash of (playerID:vendorID:bucket:lineType) to ensure
     * the same player gets the same dialogue from the same vendor.
     *
     * @param  Collection  $lines
     * @param  int  $playerId
     * @param  int  $vendorId
     * @param  int  $interactionCount
     * @param  string  $lineType
     * @return VendorDialogue|null
     */
    private function selectDeterministicLine(
        Collection $lines,
        int $playerId,
        int $vendorId,
        int $interactionCount,
        string $lineType
    ): ?VendorDialogue {
        if ($lines->isEmpty()) {
            return null;
        }

        // Create deterministic seed from player + vendor + interaction + line type
        $seed = crc32("{$playerId}:{$vendorId}:{$interactionCount}:{$lineType}");
        $index = abs($seed) % $lines->count();

        return $lines->values()->get($index);
    }

    /**
     * Map interaction count to dialogue bucket.
     *
     * @param  int  $interactionCount
     * @return string
     */
    public function mapInteractionBucketPublic(int $interactionCount): string
    {
        return $this->mapInteractionBucket($interactionCount);
    }

    private function mapInteractionBucket(int $interactionCount): string
    {
        return match (true) {
            $interactionCount <= 1 => 'first_visit',
            $interactionCount === 2 => 'second_visit',
            $interactionCount === 3 => 'third_visit',
            default => 'repeat_customer',
        };
    }

    /**
     * Get dialogue for multiple line types at once.
     *
     * Useful for API responses that need multiple dialogue lines.
     *
     * @param  GalaxyVendorProfile  $vendor
     * @param  array  $lineTypes
     * @param  int  $playerId
     * @param  int  $interactionCount
     * @param  string|null  $inventoryContext
     * @return array
     */
    public function getMultipleDialogue(
        GalaxyVendorProfile $vendor,
        array $lineTypes,
        int $playerId,
        int $interactionCount,
        TransactionContext $transactionContext = TransactionContext::NEUTRAL,
        InventoryContext $inventoryContext = InventoryContext::NONE,
    ): array {
        $dialogue = [];

        foreach ($lineTypes as $lineType) {
            $dialogue[$lineType] = $this->getDialogueWithFallback(
                $vendor,
                $lineType,
                $playerId,
                $interactionCount,
                $transactionContext,
                $inventoryContext,
            );
        }

        return $dialogue;
    }

    /**
     * Check if vendor has generated dialogue.
     *
     * @param  GalaxyVendorProfile  $vendor
     * @return bool
     */
    public function hasGeneratedDialogue(GalaxyVendorProfile $vendor): bool
    {
        return $vendor->dialogue_generation_status === 'complete'
            && $vendor->dialogueLines()->exists();
    }

    /**
     * Get dialogue generation status.
     *
     * @param  GalaxyVendorProfile  $vendor
     * @return array
     */
    public function getGenerationStatus(GalaxyVendorProfile $vendor): array
    {
        return [
            'status' => $vendor->dialogue_generation_status,
            'version' => $vendor->dialogue_generation_version,
            'generated_at' => $vendor->dialogue_generated_at,
            'line_count' => $vendor->dialogueLines()->count(),
        ];
    }
}

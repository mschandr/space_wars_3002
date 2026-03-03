<?php

namespace App\Http\Controllers\Api;

use App\Models\Player;
use App\Models\VendorProfile;
use App\Services\VendorProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorController extends BaseApiController
{
    public function __construct(
        private readonly VendorProfileService $vendorService
    ) {}

    /**
     * Get vendor profile and player's relationship with them
     *
     * GET /api/vendors/{vendorUuid}?player_uuid=xxx
     */
    public function show(Request $request, string $vendorUuid): JsonResponse
    {
        // Find vendor
        $vendor = VendorProfile::where('uuid', $vendorUuid)
            ->with('pointOfInterest')
            ->first();

        if (!$vendor) {
            return $this->notFound('Vendor not found');
        }

        $player = $request->user()->player ?? null;

        // Get relationship
        $relationship = null;
        $effectiveMarkup = null;

        if ($player) {
            $relationship = $this->vendorService->getOrCreateRelationship($player, $vendor);
            $effectiveMarkup = $this->vendorService->getEffectiveMarkup($vendor, $player);
        }

        return $this->success([
            'vendor' => [
                'uuid' => $vendor->uuid,
                'name' => $vendor->name,
                'archetype' => $vendor->archetype->value,
                'archetype_label' => $vendor->archetype->label(),
                'description' => $vendor->archetype->description(),
                'personality' => $vendor->personality ?? [],
                'location' => [
                    'uuid' => $vendor->pointOfInterest->uuid,
                    'name' => $vendor->pointOfInterest->name,
                ],
            ],
            'relationship' => $relationship ? [
                'goodwill' => $relationship->goodwill,
                'shady_dealings' => $relationship->shady_dealings,
                'visit_count' => $relationship->visit_count,
                'is_locked_out' => $relationship->is_locked_out,
                'last_interaction_at' => $relationship->last_interaction_at?->toIso8601String(),
            ] : null,
            'effective_markup' => $effectiveMarkup,
            'greeting' => $this->vendorService->getDialogueLine($vendor, 'greeting', $player),
        ]);
    }

    /**
     * Record an interaction with a vendor
     *
     * POST /api/vendors/{vendorUuid}/interact
     * Body: { type: 'trade' | 'shady_trade' | 'browse' }
     */
    public function interact(Request $request, string $vendorUuid): JsonResponse
    {
        $vendor = VendorProfile::where('uuid', $vendorUuid)->first();

        if (!$vendor) {
            return $this->notFound('Vendor not found');
        }

        $player = $request->user()->player;
        if (!$player) {
            return $this->error('Player not found', 404);
        }

        $type = $request->input('type', 'browse');

        // Record the interaction
        if (in_array($type, ['trade', 'shady_trade'])) {
            $this->vendorService->recordInteraction($vendor, $player, $type);
        }

        // Get dialogue response based on type
        $dialogueContext = match ($type) {
            'trade' => 'deal_accepted',
            'shady_trade' => 'deal_accepted',
            default => 'greeting',
        };

        return $this->success([
            'message' => 'Interaction recorded',
            'dialogue' => $this->vendorService->getDialogueLine($vendor, $dialogueContext, $player),
            'relationship_updated' => in_array($type, ['trade', 'shady_trade']),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Enums\Vendor\InventoryContext;
use App\Enums\Vendor\TransactionContext;
use App\Models\GalaxyVendorProfile;
use App\Models\PlayerVendorRelationship;
use App\Services\VendorDialogueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorDialogueController extends BaseApiController
{
    public function __construct(
        private readonly VendorDialogueService $dialogueService,
    ) {}

    /**
     * GET /api/players/{playerUuid}/vendors/{vendorUuid}/dialogue
     *
     * Returns greeting and current dialogue context for a player visiting a vendor.
     * Does not increment visit count — use the interaction controller for that.
     */
    public function index(Request $request, string $playerUuid, string $vendorUuid): JsonResponse
    {
        $player = $this->findAuthenticatedPlayerOrFail($playerUuid, $request);
        if ($player instanceof JsonResponse) {
            return $player;
        }

        $vendor = GalaxyVendorProfile::where('uuid', $vendorUuid)->first();
        if (! $vendor) {
            return $this->notFound('Vendor not found');
        }

        $relationship = PlayerVendorRelationship::firstOrCreate(
            ['player_id' => $player->id, 'vendor_profile_id' => $vendor->vendor_profile_id],
            ['goodwill' => 0, 'shady_dealings' => 0, 'visit_count' => 0]
        );

        $greeting = $this->dialogueService->getDialogueWithFallback(
            $vendor,
            'greeting',
            $player->id,
            $relationship->visit_count,
            TransactionContext::NEUTRAL,
            InventoryContext::NONE,
        );

        return $this->success([
            'vendor' => [
                'uuid'         => $vendor->uuid,
                'service_type' => $vendor->service_type,
                'criminality'  => $vendor->criminality,
            ],
            'dialogue' => [
                'greeting' => $greeting,
            ],
            'interaction_bucket'  => $this->dialogueService->mapInteractionBucketPublic($relationship->visit_count),
            'dialogue_status'     => $vendor->dialogue_generation_status,
        ]);
    }
}

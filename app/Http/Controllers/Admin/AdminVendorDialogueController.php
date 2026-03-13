<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\GalaxyVendorProfile;
use App\Services\VendorDialogueService;
use Illuminate\Http\JsonResponse;

class AdminVendorDialogueController extends BaseApiController
{
    public function __construct(
        private VendorDialogueService $dialogueService,
    ) {}

    /**
     * List galaxy vendor instances needing dialogue generation.
     *
     * GET /api/admin/vendors/dialogue/pending
     *
     * @return JsonResponse
     */
    public function pendingDialogue(): JsonResponse
    {
        $vendors = GalaxyVendorProfile::needingDialogueGeneration()
            ->with('galaxy:id,name', 'pointOfInterest:id,name', 'vendorProfile:id,uuid,name,archetype')
            ->select('id', 'uuid', 'galaxy_id', 'poi_id', 'vendor_profile_id', 'service_type', 'dialogue_generation_status', 'dialogue_generation_version')
            ->get();

        return $this->success([
            'count' => $vendors->count(),
            'vendors' => $vendors->map(fn ($v) => [
                'id' => $v->id,
                'uuid' => $v->uuid,
                'galaxy_name' => $v->galaxy->name,
                'poi_name' => $v->pointOfInterest->name,
                'vendor_name' => $v->vendorProfile->name,
                'vendor_archetype' => $v->vendorProfile->archetype,
                'service_type' => $v->service_type,
                'status' => $v->dialogue_generation_status,
                'version' => $v->dialogue_generation_version,
            ]),
        ]);
    }

    /**
     * Force regeneration of vendor dialogue.
     *
     * POST /api/admin/vendors/{uuid}/dialogue/regenerate
     *
     * @param  string  $uuid
     * @return JsonResponse
     */
    public function regenerateDialogue(string $uuid): JsonResponse
    {
        $vendor = GalaxyVendorProfile::findByUuid($uuid);

        if (!$vendor) {
            return $this->error('Vendor not found', 'VENDOR_NOT_FOUND', null, 404);
        }

        $vendor->markDialogueStale();

        return $this->success([
            'message' => 'Dialogue regeneration scheduled',
            'vendor_uuid' => $vendor->uuid,
            'status' => $vendor->dialogue_generation_status,
            'version' => $vendor->dialogue_generation_version,
        ]);
    }

    /**
     * Inspect dialogue for a vendor.
     *
     * GET /api/admin/vendors/{uuid}/dialogue
     *
     * @param  string  $uuid
     * @return JsonResponse
     */
    public function inspectDialogue(string $uuid): JsonResponse
    {
        $vendor = GalaxyVendorProfile::findByUuid($uuid);

        if (!$vendor) {
            return $this->error('Vendor not found', 'VENDOR_NOT_FOUND', null, 404);
        }

        $dialogue = $vendor->dialogueLines()
            ->orderBy('line_type')
            ->orderBy('interaction_bucket')
            ->get()
            ->groupBy('line_type')
            ->map(fn ($lines) => $lines->groupBy('interaction_bucket'));

        $status = $this->dialogueService->getGenerationStatus($vendor);

        return $this->success([
            'vendor' => [
                'uuid' => $vendor->uuid,
                'galaxy_name' => $vendor->galaxy->name,
                'poi_name' => $vendor->pointOfInterest->name,
                'profile_name' => $vendor->vendorProfile->name,
                'service_type' => $vendor->service_type,
                'criminality' => $vendor->criminality,
            ],
            'generation' => $status,
            'dialogue_by_type_bucket' => $dialogue->map(fn ($buckets) => $buckets->map(fn ($lines) => $lines->map(fn ($line) => [
                'id' => $line->id,
                'text' => $line->line_text,
                'weight' => $line->weight,
                'inventory_context' => $line->inventory_context,
                'generation_version' => $line->generation_version,
            ]))),
        ]);
    }
}

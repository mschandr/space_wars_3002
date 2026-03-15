<?php

namespace App\Http\Controllers\Internal;

use App\Models\GalaxyVendorProfile;
use App\Models\VendorDialogue;
use App\Services\Vendor\DialogueValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Internal API for the Go dialogue generator service.
 *
 * All endpoints require the InternalApiTokenMiddleware (Bearer token auth).
 * These routes are NOT part of the public gameplay API.
 *
 * Endpoints:
 *   GET  /api/internal/vendor-dialogue/pending          → pending()
 *   PATCH /api/internal/vendor-dialogue/{uuid}/status   → updateStatus()
 *   POST  /api/internal/vendor-dialogue/{uuid}/lines    → submitLines()
 */
class DialogueGeneratorController extends Controller
{
    public function __construct(
        private DialogueValidationService $validationService,
    ) {}

    /**
     * Return all vendors needing dialogue generation.
     *
     * The Go generator polls this endpoint to discover work.
     * Returns status=pending and status=failed vendors with the
     * full profile data needed to build generation prompts.
     *
     * GET /api/internal/vendor-dialogue/pending
     */
    public function pending(): JsonResponse
    {
        $vendors = GalaxyVendorProfile::needingDialogueGeneration()
            ->with('vendorProfile:id,name,archetype,service_type,criminality,personality,markup_base')
            ->get();

        return response()->json([
            'count'   => $vendors->count(),
            'vendors' => $vendors->map(fn ($v) => [
                'id'                          => $v->id,
                'uuid'                        => $v->uuid,
                'service_type'                => $v->service_type,
                'criminality'                 => (float) $v->criminality,
                'dialogue_generation_version' => $v->dialogue_generation_version,
                'profile'                     => [
                    'archetype'   => $v->vendorProfile?->archetype?->value ?? 'honest_dealer',
                    'personality' => $v->vendorProfile?->personality ?? [],
                    'markup_base' => (float) ($v->vendorProfile?->markup_base ?? 0.10),
                ],
            ]),
        ]);
    }

    /**
     * Update the dialogue generation status for a vendor.
     *
     * Go calls this to signal state transitions:
     *   pending → generating  (Go begins processing)
     *   generating → complete (Go finished successfully)
     *   generating → failed   (Go gave up after retries)
     *
     * PATCH /api/internal/vendor-dialogue/{vendorUuid}/status
     */
    public function updateStatus(Request $request, string $vendorUuid): JsonResponse
    {
        $validated = $request->validate([
            'status'       => 'required|in:generating,complete,failed',
            'generated_at' => 'nullable|date',
        ]);

        $vendor = GalaxyVendorProfile::findByUuid($vendorUuid);

        if (! $vendor) {
            return response()->json(['error' => 'Vendor not found', 'code' => 'VENDOR_NOT_FOUND'], 404);
        }

        $updates = ['dialogue_generation_status' => $validated['status']];

        if ($validated['status'] === 'complete') {
            $updates['dialogue_generated_at'] = $validated['generated_at'] ?? now();
        }

        $vendor->update($updates);

        return response()->json([
            'ok'     => true,
            'status' => $vendor->dialogue_generation_status,
        ]);
    }

    /**
     * Accept generated dialogue lines for a specific vendor and scope.
     *
     * Replace strategy: existing rows matching the exact scope
     * (line_type + interaction_bucket + transaction_context + inventory_context)
     * are deleted before the new rows are inserted.
     *
     * PHP validates all submitted lines before storage. Any validation failure
     * rejects the entire submission — the Go service should fix and retry.
     *
     * POST /api/internal/vendor-dialogue/{vendorUuid}/lines
     *
     * Body:
     * {
     *   "line_type": "greeting",
     *   "interaction_bucket": "first_visit",
     *   "transaction_context": "neutral",
     *   "inventory_context": "none",
     *   "generation_version": 3,
     *   "lines": ["What do you need?", "Back again?"]
     * }
     */
    public function submitLines(Request $request, string $vendorUuid): JsonResponse
    {
        $maxLines = (int) config('vendor_dialogue.max_lines_per_submission', 20);

        $validated = $request->validate([
            'line_type'           => 'required|in:greeting,inventory_pitch,deal_accepted,deal_rejected,farewell',
            'interaction_bucket'  => 'required|in:first_visit,second_visit,third_visit,repeat_customer',
            'transaction_context' => 'required|in:neutral,vendor_selling,vendor_buying',
            'inventory_context'   => 'required|string|max:64',
            'generation_version'  => 'required|integer|min:1',
            'lines'               => "required|array|min:1|max:{$maxLines}",
            'lines.*'             => 'required|string|max:255',
        ]);

        $vendor = GalaxyVendorProfile::findByUuid($vendorUuid);

        if (! $vendor) {
            return response()->json(['error' => 'Vendor not found', 'code' => 'VENDOR_NOT_FOUND'], 404);
        }

        // PHP-side validation (defense-in-depth, mirrors Go validation)
        $failures = $this->validationService->validateLines($validated['lines']);

        if (! empty($failures)) {
            return response()->json([
                'error'    => 'Validation failed — fix lines and resubmit',
                'code'     => 'DIALOGUE_VALIDATION_FAILED',
                'failures' => $failures,
            ], 422);
        }

        // Deduplicate within the submission
        $uniqueLines = collect($validated['lines'])->unique()->values();
        $duplicatesDropped = count($validated['lines']) - $uniqueLines->count();

        DB::transaction(function () use ($vendor, $validated, $uniqueLines) {
            // Delete old rows for this exact scope
            VendorDialogue::where('galaxy_vendor_profile_id', $vendor->id)
                ->where('line_type', $validated['line_type'])
                ->where('interaction_bucket', $validated['interaction_bucket'])
                ->where('transaction_context', $validated['transaction_context'])
                ->where('inventory_context', $validated['inventory_context'])
                ->delete();

            // Bulk insert new rows
            $now  = now();
            $rows = $uniqueLines->map(fn ($line) => [
                'galaxy_vendor_profile_id' => $vendor->id,
                'line_type'                => $validated['line_type'],
                'interaction_bucket'       => $validated['interaction_bucket'],
                'transaction_context'      => $validated['transaction_context'],
                'inventory_context'        => $validated['inventory_context'],
                'generation_version'       => $validated['generation_version'],
                'line_text'                => $line,
                'weight'                   => 1.0,
                'created_at'               => $now,
                'updated_at'               => $now,
            ])->toArray();

            VendorDialogue::insert($rows);
        });

        return response()->json([
            'ok'                => true,
            'stored'            => $uniqueLines->count(),
            'duplicates_dropped' => $duplicatesDropped,
        ]);
    }
}

<?php

namespace App\Observers;

use App\Models\GalaxyVendorProfile;

class GalaxyVendorProfileObserver
{
    private const WATCHED_FIELDS = ['service_type', 'criminality', 'personality', 'markup_base'];

    /**
     * When key personality/pricing fields change, mark dialogue as stale
     * so the Go generator picks up the vendor for regeneration.
     */
    public function updating(GalaxyVendorProfile $vendor): void
    {
        foreach (self::WATCHED_FIELDS as $field) {
            if ($vendor->isDirty($field)) {
                $vendor->dialogue_generation_status = 'pending';
                $vendor->dialogue_generation_version = $vendor->dialogue_generation_version + 1;
                $vendor->dialogue_generated_at = null;
                return;
            }
        }
    }
}

<?php

return [
    /*
     * Feature flags for game systems
     * Use config('features.feature_name') to check if a feature is enabled
     */

    'black_market'    => true,      // Black market commodities (visibility gated by shady interactions)
    'crew_profiles'   => true,      // Crew members with roles, alignment, and ship persona effects
    'vendor_profiles' => true,      // Vendor archetypes with reputation and markup mechanics
    'customs'         => true,      // Police/customs officials with arrival checks and contraband detection
    'npc_traders'     => false,     // NPC traders (disabled - enable when ready for Phase 9)
    'pirates'         => true,      // Pirate encounters and fleets
    'colonies'        => true,      // Colony settlement and development
];

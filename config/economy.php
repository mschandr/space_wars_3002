<?php

return [
    /*
     * Pricing mechanics: spread, clamping, price impact from supply/demand
     */
    'pricing' => [
        'spread_per_side'   => 0.08,          // buy = mid*(1+spread), sell = mid*(1-spread)
        'min_multiplier'    => 0.10,          // floor: price cannot go below 10% of base_value
        'max_multiplier'    => 10.00,         // ceiling: price cannot exceed 10x base_value
        'price_impact'      => 0.01,          // demand/supply shift per unit_step traded
        'units_per_step'    => 10,            // integer step unit for supply/demand mutation
        'port_type_bias'    => [],            // scaffold: future port-type price nudges
    ],

    /*
     * Market event configuration: probability, magnitude, duration
     */
    'events' => [
        'enabled'           => true,
        'chance_per_tick'   => 0.15,
        'magnitude_range'   => [0.5, 3.0],
        'duration_minutes'  => [30, 240],
    ],

    /*
     * Black market configuration: visibility threshold, access rules
     */
    'black_market' => [
        'enabled'           => true,
        'visibility_threshold' => 10,          // shady interactions before black market is perceived
        'access_rules'      => [
            'min_reputation'      => -20,
            'min_crew_alignment'  => 'neutral',
        ],
    ],

    /*
     * Stock ranges by mineral rarity (used in lazy generation)
     */
    'stock_by_rarity' => [
        'abundant'   => [15000, 30000],
        'common'     => [5000,  15000],
        'uncommon'   => [2000,   8000],
        'rare'       => [500,    3000],
        'epic'       => [200,    1000],
        'very_rare'  => [100,    1000],
        'legendary'  => [10,      200],
        'mythic'     => [5,        50],
    ],

    /*
     * NPC trader configuration (feature-flagged off by default)
     */
    'npc_traders' => [
        'enabled'        => false,             // feature flag (off until Phase 9)
        'tick_interval'  => 300,               // seconds between NPC trade cycles
    ],
];

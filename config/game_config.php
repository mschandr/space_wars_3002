<?php

return [

    /**
     * Feature flags
     */
    'feature' => [
        'persist_data' => true,
        'stamp_version' => true,
    ],

    /**
    |--------------------------------------------------------------------------
    | Galaxy Generation
    |--------------------------------------------------------------------------
     */
    'galaxy' => [
        'width' => 300,        // galaxy grid width
        'height' => 300,        // galaxy grid height
        'points' => 3000,       // number of Points of Interest
        'spacing' => 0.75,       // spacing factor for generators
        'turn_limit' => 250,
        'engine' => 'mt19937',  // RNG engine: mt19937, pcg, xoshiro
        'generator' => 'poisson',  // options: poisson, scatter, halton, vogel, stratified, latin, r2, uniform
        'is_public' => true,
    ],

    /**
    |--------------------------------------------------------------------------
    | Point Generator Options
    |--------------------------------------------------------------------------
     */
    'generator_options' => [
        'attempts' => 30,     // PoissonDisk candidate attempts
        'margin' => 0,      // safe margin from galaxy edges
        'returnFloats' => false,  // whether to return float coords
        'vogel_rotation' => 0,  // Vogel's Spiral rotation offset in degrees (0-360)
    ],

    /**
    |--------------------------------------------------------------------------
    | Gates / Hidden Network
    |--------------------------------------------------------------------------
     */
    'gates' => [
        'hidden_chance' => 0.1,   // % chance that a gate is hidden
        'dead_gate_chance' => 0.05,  // % chance hidden gate is a dead end
        'jackpot_chance' => 0.01,  // % chance hidden gate leads to relics
        'scanner_bonus' => 0.2,   // multiplier for scanners detecting gates
    ],

    /**
    |--------------------------------------------------------------------------
    | Ore & Economy
    |--------------------------------------------------------------------------
     */
    'ores' => [
        'types' => [
            'iron', 'gold', 'titanium', 'uranium', 'exotic_matter',
        ],
        'price_base' => 100,   // average credit value
        'price_fluct' => 0.25,  // ±25% local fluctuation
        'scarcity_bias' => [
            'exotic_matter' => 0.01,  // appears in 1% of systems
            'iron' => 0.8,   // very common
        ],
    ],

    /**
    |--------------------------------------------------------------------------
    | Colonies
    |--------------------------------------------------------------------------
     */
    'colonies' => [
        'growth_rate' => 1.05,   // population growth multiplier per turn
        'defenses' => true,   // enable orbital defenses
    ],

    /**
    |--------------------------------------------------------------------------
    | Ships
    |--------------------------------------------------------------------------
     */
    'ships' => [
        'starting_credits' => 100_000,
        'classes' => [
            'scout' => [
                'cargo' => 50,
                'speed' => 5,
                'combat' => 1,
            ],
            'freighter' => [
                'cargo' => 200,
                'speed' => 3,
                'combat' => 2,
            ],
            'battleship' => [
                'cargo' => 100,
                'speed' => 2,
                'combat' => 8,
            ],
        ],
    ],

    /**
    |--------------------------------------------------------------------------
    | Victory Conditions
    |--------------------------------------------------------------------------
     */
    'victory' => [
        'merchant_credits' => 1_000_000_000, // credits to win as Merchant Empire
        'colonization_share' => 0.5,           // 50% of galactic population
        'conquest_share' => 0.6,           // 60% of systems controlled
        'pirate_power' => 0.7,           // 70% of outlaw hubs seized
    ],

    /**
    |--------------------------------------------------------------------------
    | Mirror Universe
    |--------------------------------------------------------------------------
    | High-risk, high-reward parallel dimensions accessible via ultra-rare
    | hidden gates. Cooldown-based return prevents abuse.
    */
    'mirror_universe' => [
        'enabled' => true,

        // Gate Discovery
        'required_sensor_level' => 5,  // Minimum sensor level to detect mirror gates
        'gates_per_galaxy' => 1,  // Exactly one mirror gate per galaxy (ultra-rare)

        // Cooldown System
        'return_cooldown_hours' => 24,  // Hours before can return from mirror universe

        // Economy Modifiers (High Reward)
        'resource_multiplier' => 2.0,  // 2x base resource spawn
        'rare_mineral_spawn_rate' => 3.0,  // 3x chance for rare minerals
        'price_boost' => 1.5,  // 50% higher trading prices

        // Difficulty Modifiers (High Risk)
        'pirate_difficulty_boost' => 2.0,  // 2x pirate strength
        'pirate_fleet_size_boost' => 1.5,  // 50% larger pirate fleets
    ],

];

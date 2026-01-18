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
        'inhabited_percentage' => 0.40,  // 40% of stars are inhabited (1/3 to 1/2 range: 33-50%)
        'inhabited_min_spacing' => 50,   // Minimum distance between inhabited systems
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
    | Inhabited Systems
    |--------------------------------------------------------------------------
    | Inhabited systems have civilization, services, and dense gate networks.
    | These are hubs of trade and activity in the galaxy.
     */
    'inhabited_systems' => [
        'guaranteed_services' => [
            'trading_hub' => 0.65,        // 65% of inhabited systems have trading hubs (50-80% range)
            'ship_shop' => 0.8,           // 80% chance of ship shop (of those with trading hubs)
            'repair_yard' => 0.9,         // 90% chance of repair yard (of those with trading hubs)
            'cartographer' => 0.3,        // 30% chance of stellar cartographer (of those with trading hubs)
            'component_shop' => 0.6,      // 60% chance of component/upgrade shop (of those with trading hubs)
        ],
        'dense_gate_network' => true,     // Inhabited systems get warp gates
        'gate_multiplier' => 1.5,         // 1.5x more gates than base
        'min_gates_per_system' => 2,      // Minimum 2 gates for connectivity
    ],

    /**
    |--------------------------------------------------------------------------
    | Uninhabited Systems
    |--------------------------------------------------------------------------
    | Uninhabited systems are resource-rich but isolated. Perfect targets
    | for colonization and mining operations.
     */
    'uninhabited_systems' => [
        'mineral_spawn_rate' => 0.95,     // 95% have mineable minerals
        'gate_spawn_rate' => 0.0,         // No gates for uninhabited systems (players must use coordinates)
        'max_gates_per_system' => 0,      // No connectivity to force exploration
        'rich_deposits' => true,          // Higher mineral quantities
        'deposit_multiplier' => 1.5,      // 1.5x more minerals than inhabited
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
    | Direct Coordinate Travel
    |--------------------------------------------------------------------------
    | Allows players to jump directly to coordinates without using warp gates.
    | More expensive and limited by engine level, but provides freedom of movement.
     */
    'direct_travel' => [
        'enabled' => true,
        'fuel_penalty_multiplier' => 2.5,     // 2.5x fuel cost vs gate travel
        'xp_multiplier' => 0.75,              // 75% of normal XP reward
        'distance_per_warp_level' => 5.0,     // Level 1 = 5 units, Level 2 = 10, etc.
        'base_max_distance' => 5.0,           // Minimum jump distance (warp drive 1)
        'requires_clear_path' => false,       // Future: check for obstacles
    ],

    /**
    |--------------------------------------------------------------------------
    | Star Charts
    |--------------------------------------------------------------------------
    | Stellar Cartographers sell star charts that reveal information about
    | nearby systems. Charts are permanent player knowledge.
     */
    'star_charts' => [
        'base_price' => 1000,  // Base price in credits
        'unknown_multiplier' => 1.5,  // Price multiplier per unknown system
        'coverage_hops' => 2,  // Warp gate hops for chart coverage
        'spawn_rate' => 0.3,  // 30% of trading hubs get cartographers
        'starting_charts_count' => 3,  // Free charts for new players

        // Pirate detection
        'pirate_detection_base_accuracy' => 0.70,  // 70% base
        'pirate_detection_sensor_bonus' => 0.05,  // +5% per sensor level
        'pirate_detection_max_accuracy' => 0.95,  // Cap at 95%
    ],

    /**
    |--------------------------------------------------------------------------
    | Cartographer Names
    |--------------------------------------------------------------------------
    | Name generation for Stellar Cartographer shops
     */
    'cartographer_names' => [
        'prefixes' => ['StarNav', 'Void', 'Celestial', 'Quantum', 'Nova', 'Astral', 'Cosmic', 'Stellar', 'Galactic'],
        'suffixes' => ['Mappers', 'Cartography', 'Navigation', 'Systems', 'Guild', 'Charts', 'Surveys', 'Explorers'],
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

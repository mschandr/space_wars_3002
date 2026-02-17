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
     * |--------------------------------------------------------------------------
     * | Galaxy Generation
     * |--------------------------------------------------------------------------
     */
    'galaxy' => [
        'width' => 300,        // galaxy grid width
        'height' => 300,        // galaxy grid height
        'points' => 3000,       // number of Points of Interest
        'spacing' => 0.75,       // spacing factor for generators
        'turn_limit' => 250,
        'engine' => 'mt19937',  // RNG engine: mt19937, pcg, xoshiro
        'generator' => 'vogel',  // options: poisson, scatter, halton, vogel, stratified, latin, r2, uniform
        'is_public' => true,
        'inhabited_percentage' => 0.40,  // 40% of stars are inhabited (1/3 to 1/2 range: 33-50%)
        'inhabited_min_spacing' => 50,   // Minimum distance between inhabited systems
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Point Generator Options
     * |--------------------------------------------------------------------------
     */
    'generator_options' => [
        'attempts' => 30,     // PoissonDisk candidate attempts
        'margin' => 0,      // safe margin from galaxy edges
        'returnFloats' => false,  // whether to return float coords
        'vogel_rotation' => 0,  // Vogel's Spiral rotation offset in degrees (0-360)
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Gates / Hidden Network
     * |--------------------------------------------------------------------------
     */
    'gates' => [
        'hidden_chance' => 0.1,   // % chance that a gate is hidden
        'dead_gate_chance' => 0.05,  // % chance hidden gate is a dead end
        'jackpot_chance' => 0.01,  // % chance hidden gate leads to relics
        'scanner_bonus' => 0.2,   // multiplier for scanners detecting gates
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Inhabited Systems
     * |--------------------------------------------------------------------------
     * | Inhabited systems have civilization, services, and dense gate networks.
     * | These are hubs of trade and activity in the galaxy.
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
     * |--------------------------------------------------------------------------
     * | Uninhabited Systems
     * |--------------------------------------------------------------------------
     * | Uninhabited systems are resource-rich but isolated. Perfect targets
     * | for colonization and mining operations.
     */
    'uninhabited_systems' => [
        'mineral_spawn_rate' => 0.95,     // 95% have mineable minerals
        'gate_spawn_rate' => 0.0,         // No gates for uninhabited systems (players must use coordinates)
        'max_gates_per_system' => 0,      // No connectivity to force exploration
        'rich_deposits' => true,          // Higher mineral quantities
        'deposit_multiplier' => 1.5,      // 1.5x more minerals than inhabited
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Moons
     * |--------------------------------------------------------------------------
     * | Moon generation parameters for planetary systems.
     */
    'moons' => [
        'habitable_chance_gas_giant' => 0.05,
        'habitable_chance_ice_giant' => 0.02,
        'habitable_chance_other' => 0.01,
        'habitable_min_size' => 'medium',
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Ore & Economy
     * |--------------------------------------------------------------------------
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
     * |--------------------------------------------------------------------------
     * | Colonies
     * |--------------------------------------------------------------------------
     */
    'colonies' => [
        'growth_rate' => 1.05,   // population growth multiplier per turn
        'defenses' => true,   // enable orbital defenses
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Ships
     * |--------------------------------------------------------------------------
     */
    'ships' => [
        'starting_credits' => 100_000,
        'fuel_regen_seconds_per_unit' => 1, // TESTING: normal value is 30. Seconds between each 1-fuel regeneration tick.
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
     * |--------------------------------------------------------------------------
     * | Victory Conditions
     * |--------------------------------------------------------------------------
     */
    'victory' => [
        'merchant_credits' => 1_000_000_000, // credits to win as Merchant Empire
        'colonization_share' => 0.5,           // 50% of galactic population
        'conquest_share' => 0.6,           // 60% of systems controlled
        'pirate_power' => 0.7,           // 70% of outlaw hubs seized
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Direct Coordinate Travel
     * |--------------------------------------------------------------------------
     * | Allows players to jump directly to coordinates without using warp gates.
     * | More expensive and limited by engine level, but provides freedom of movement.
     */
    'direct_travel' => [
        'enabled' => true,
        'fuel_penalty_multiplier' => 4.0,     // 4.0x fuel cost vs gate travel
        'warp_efficiency_factor' => 0.25,     // Warp drive upgrades 75% less effective for direct jumps
        'xp_multiplier' => 0.5,              // 50% of normal XP reward
        'distance_per_warp_level' => 5.0,     // Level 1 = 5 units, Level 2 = 10, etc.
        'base_max_distance' => 5.0,           // Minimum jump distance (warp drive 1)
        'requires_clear_path' => false,       // Future: check for obstacles
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Star Charts
     * |--------------------------------------------------------------------------
     * | Stellar Cartographers sell star charts that reveal information about
     * | nearby systems. Charts are permanent player knowledge.
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
     * |--------------------------------------------------------------------------
     * | Cartographer Names
     * |--------------------------------------------------------------------------
     * | Name generation for Stellar Cartographer shops
     */
    'cartographer_names' => [
        'prefixes' => ['StarNav', 'Void', 'Celestial', 'Quantum', 'Nova', 'Astral', 'Cosmic', 'Stellar', 'Galactic'],
        'suffixes' => ['Mappers', 'Cartography', 'Navigation', 'Systems', 'Guild', 'Charts', 'Surveys', 'Explorers'],
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Mirror Universe
     * |--------------------------------------------------------------------------
     * | High-risk, high-reward parallel dimensions accessible via ultra-rare
     * | hidden gates. Cooldown-based return prevents abuse.
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

    /**
     * |--------------------------------------------------------------------------
     * | Tiered Galaxy Configuration
     * |--------------------------------------------------------------------------
     * | Configuration for tiered galaxies with core/outer regions.
     * | Core = civilized, Outer = frontier wilderness.
     */
    'tiered_galaxy' => [
        // Core region settings
        'core_min_spacing' => 15,           // Minimum spacing between core stars
        'core_charted_percentage' => 0.90,     // 90% of core stars are charted
        'core_inhabited_percentage' => 0.81,   // 81% of core stars are inhabited (90% charted × 90% populated)
        'core_defense_level' => 1,          // Default defense level for fortress systems

        // Outer region settings
        'outer_min_spacing' => 25,          // Minimum spacing between outer stars
        'outer_charted_percentage' => 0.20,    // 20% of outer stars are charted
        'outer_inhabited_percentage' => 0.02, // 2% of outer stars are inhabited (20% charted × 10% populated)
        'outer_mineral_multiplier' => 2.0,  // 2x mineral richness in outer region
        'outer_gate_max_distance' => 200,   // Max distance for dormant gate connections

        // Dormant gate activation
        'dormant_gate_sensor_requirement' => 3, // Sensor level to activate dormant gates

        // Fortress defenses
        'fortress_defenses' => [
            'orbital_cannons' => 4,
            'space_lasers' => 2,
            'ground_missiles' => 6,
            'planetary_shield_strength' => 10000,
            'fighter_port_fighters' => 1000,
        ],

        // Size tier formulas (for reference, actual values in GalaxySizeTier enum)
        // core_bounds = outer_size / 2
        // core_stars = outer_size / 5
        // outer_stars = (outer_size / 2) - core_stars
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Player Knowledge / Fog-of-War
     * |--------------------------------------------------------------------------
     * | Controls how players discover and retain galaxy information.
     * | 1 coordinate unit = 1 light year.
     */
    'knowledge' => [
        // Sensor range (LY)
        'sensor_range_base' => 1,              // LY for sensor level 1
        'sensor_range_increment' => 2,          // LY per level after 1

        // Stellar cartography coverage
        'chart_radius_inhabited_ly' => 5,       // Coverage radius from inhabited hub
        'chart_radius_uninhabited_ly' => 0,     // No charts available at uninhabited systems
        'chart_sector_limited' => true,         // Charts limited to current sector only
        'chart_hops_inhabited' => 2,            // Warp-gate hops for knowledge at inhabited systems
        'chart_hops_uninhabited' => 1,          // Warp-gate hops for knowledge at uninhabited systems

        // Core sector baseline
        'core_baseline_enabled' => true,        // Core inhabited systems have baseline connectivity knowledge
        'core_baseline_level' => 1,             // DETECTED — know they exist + connections within sector

        // Decay (wall-clock hours)
        'freshness_max_hours' => 168,           // 7 days until detail degrades to floor
        'decay_floor_level' => 1,               // DETECTED — knowledge never vanishes below this
        'visited_permanent' => true,            // VISITED never decays
        'warp_lane_permanent' => true,          // Lane connectivity never decays

        // Pirate intel
        'pirate_danger_radius_ly' => 5,         // Danger zone radius around known pirate lanes
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Orbital Structures
     * |--------------------------------------------------------------------------
     * | Player-buildable structures that orbit planets and moons.
     * | Defense platforms, magnetic mines, mining platforms, orbital bases.
     */
    'orbital_structures' => [
        'construction_rate' => 10,              // % progress per cycle
        'max_level' => 5,                       // Maximum upgrade level
        'mine_detection_base' => 0.30,          // 30% base detection chance
        'mine_detection_per_sensor' => 0.10,    // +10% per sensor level
        'mine_detection_max' => 0.90,           // 90% cap
        'mining_platform_base_rate' => 50,      // Minerals per cycle
        'mining_platform_storage' => 500,       // Max stored minerals
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Rarity System
     * |--------------------------------------------------------------------------
     * | Unified rarity tiers for ships and components.
     * | Weights control drop frequency. Multipliers scale stats and prices.
     */
    'rarity' => [
        'weights' => [
            'common' => 60,
            'uncommon' => 30,
            'rare' => 5,
            'epic' => 3,
            'unique' => 2,
            'exotic' => 1,
        ],
        'stat_multipliers' => [
            'common' => 1.0,
            'uncommon' => 1.1,
            'rare' => 1.25,
            'epic' => 1.5,
            'unique' => 1.8,
            'exotic' => 2.2,
        ],
        'price_multipliers' => [
            'common' => 1.0,
            'uncommon' => 1.5,
            'rare' => 3.0,
            'epic' => 6.0,
            'unique' => 12.0,
            'exotic' => 30.0,
        ],
        'jitter_percentage' => 0.05, // +/- 5% per stat for uniqueness
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Shipyard Configuration
     * |--------------------------------------------------------------------------
     * | Shipyard POIs sell unique pre-rolled ships. Inventory generated lazily
     * | on first player visit and persists forever.
     */
    'shipyard' => [
        'inventory_size' => [
            'capital' => [4, 8],    // min, max ships
            'heavy' => [3, 6],
            'standard' => [2, 4],
            'light' => [1, 3],
        ],
        'sell_value_percentage' => 0.40, // 40% of value when selling to salvage
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Salvage Yard Configuration
     * |--------------------------------------------------------------------------
     * | Salvage yards buy whole ships for lump-sum credits and sell components.
     */
    'salvage_yard' => [
        'inventory_size' => [
            'major' => [8, 15],     // min, max components
            'standard' => [5, 10],
            'minor' => [3, 6],
        ],
        'component_sell_percentage' => 0.50, // 50% of component value
        'ship_sell_percentage' => 0.35,      // 35% of ship value (lump sum)
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Progressive Scanning System
     * |--------------------------------------------------------------------------
     * | Sensor-based progressive revelation system. Ship sensor level determines
     * | scan depth - higher levels reveal more detailed information.
     */
    'scanning' => [
        // Baseline scan levels by status (pre-scanned intel)
        'inhabited_baseline_level' => 3,   // Inhabited systems are well-documented
        'charted_baseline_level' => 2,     // Charted uninhabited systems have shared intel
        'uncharted_baseline_level' => 0,   // Uncharted systems are complete fog

        // Scan range (how far you can scan remotely)
        'scan_range_multiplier' => 100,    // sensor_level * 100 = range in units

        // Precursor ships have special sensors
        'precursor_sensor_level' => 100,   // Precursor ships see everything

        // Auto-scan behavior
        'auto_scan_on_arrival' => true,    // Automatically scan when arriving at a system

        // What each level reveals (for reference, actual logic in ScanLevel enum)
        'level_reveals' => [
            1 => ['geography', 'planet_count', 'planet_types', 'habitability_basic'],
            2 => ['gates_presence', 'gate_status'],
            3 => ['minerals_basic', 'gas_giant_resources'],
            4 => ['minerals_rare', 'asteroid_resources'],
            5 => ['hidden_moons', 'orbital_mining', 'ring_deposits'],
            6 => ['anomalies', 'ruins', 'derelicts'],
            7 => ['deep_scan', 'subsurface', 'terraforming'],
            8 => ['intel', 'pirate_hideouts', 'hidden_bases'],
            9 => ['precursor_gates', 'precursor_tech', 'ancient_secrets'],
        ],

        // UI color scheme for scan levels (hex colors)
        'level_colors' => [
            0 => '#1a1a2e',  // Unscanned - dark fog
            1 => '#4a4a6a',  // Geography - dim
            2 => '#4a4a6a',  // Gates - dim
            3 => '#3366aa',  // Basic resources - blue
            4 => '#3366aa',  // Rare resources - blue
            5 => '#33aa66',  // Hidden features - green
            6 => '#33aa66',  // Anomalies - green
            7 => '#aa9933',  // Deep scan - gold
            8 => '#aa9933',  // Intel - gold
            9 => '#ff6600',  // Precursor secrets - orange
        ],

        // UI opacity for scan levels
        'level_opacities' => [
            0 => 0.2,
            1 => 0.4,
            2 => 0.4,
            3 => 0.6,
            4 => 0.6,
            5 => 0.8,
            6 => 0.8,
            7 => 0.9,
            8 => 0.9,
            9 => 1.0,
        ],
    ],

];

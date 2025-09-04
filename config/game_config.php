<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Galaxy Generation
    |--------------------------------------------------------------------------
    */
    'galaxy' => [
        'width'     => 300,        // galaxy grid width
        'height'    => 300,        // galaxy grid height
        'points'    => 3000,       // number of Points of Interest
        'spacing'   => 0.75,       // spacing factor for generators
        'engine'    => 'mt19937',  // RNG engine: mt19937, pcg, xoshiro
        'generator' => 'scatter',  // default: poisson, scatter, halton
    ],

    /*
    |--------------------------------------------------------------------------
    | Point Generator Options
    |--------------------------------------------------------------------------
    */
    'generator_options' => [
        'attempts'     => 30,     // PoissonDisk candidate attempts
        'margin'       => 0,      // safe margin from galaxy edges
        'returnFloats' => false,  // whether to return float coords
    ],

    /*
    |--------------------------------------------------------------------------
    | Gates / Hidden Network
    |--------------------------------------------------------------------------
    */
    'gates' => [
        'hidden_chance'     => 0.1,   // % chance that a gate is hidden
        'dead_gate_chance'  => 0.05,  // % chance hidden gate is a dead end
        'jackpot_chance'    => 0.01,  // % chance hidden gate leads to relics
        'scanner_bonus'     => 0.2,   // multiplier for scanners detecting gates
    ],

    /*
    |--------------------------------------------------------------------------
    | Ore & Economy
    |--------------------------------------------------------------------------
    */
    'ores' => [
        'types' => [
            'iron', 'gold', 'titanium', 'uranium', 'exotic_matter'
        ],
        'price_base'    => 100,   // average credit value
        'price_fluct'   => 0.25,  // ±25% local fluctuation
        'scarcity_bias' => [
            'exotic_matter' => 0.01,  // appears in 1% of systems
            'iron'          => 0.8,   // very common
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Colonies
    |--------------------------------------------------------------------------
    */
    'colonies' => [
        'growth_rate' => 1.05,   // population growth multiplier per turn
        'defenses'    => true,   // enable orbital defenses
    ],

    /*
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

    /*
    |--------------------------------------------------------------------------
    | Victory Conditions
    |--------------------------------------------------------------------------
    */
    'victory' => [
        'merchant_credits'   => 1_000_000_000, // credits to win as Merchant Empire
        'colonization_share' => 0.5,           // 50% of galactic population
        'conquest_share'     => 0.6,           // 60% of systems controlled
        'pirate_power'       => 0.7,           // 70% of outlaw hubs seized
    ],

];

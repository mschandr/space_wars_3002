<?php
return [
    'random' => [
        'engine' => 'mt19937',
    ],
    'galaxy' => [
        'width'  => 300,
        'height' => 300,
        'stars'  => [
            'count' => 3000,
            'system_probability' => 0.80,
            'max_multiplicity' => 3,
            'min_degree' => 2,
            'max_degree' => 4,
        ],
        'star_classes' => [
            'red_giant' => 0.10, 'main_sequence' => 0.75, 'white_dwarf' => 0.15,
        ],
        'world_weights' => [
            'red_giant'     => ['very_hot'=>35,'hot'=>30,'mild'=>20,'cold'=>10,'very_cold'=>5],
            'main_sequence' => ['very_hot'=>10,'hot'=>25,'mild'=>40,'cold'=>20,'very_cold'=>5],
            'white_dwarf'   => ['very_hot'=>2, 'hot'=>8, 'mild'=>20,'cold'=>35,'very_cold'=>35],
        ],
        'markets' => ['station_ratio'=>0.30, 'listed_ore_fraction'=>0.50],
    ],
    'ores' => [
        ['key'=>'ferrite',  'name'=>'Ferrite',  'rarity'=>'common',  'base_price'=>15,   'origins'=>['hot','mild']],
        ['key'=>'silicate', 'name'=>'Silicate', 'rarity'=>'common',  'base_price'=>12,   'origins'=>['hot','mild','cold']],
        ['key'=>'iridium',  'name'=>'Iridium',  'rarity'=>'rare',    'base_price'=>120,  'origins'=>['very_hot']],
        ['key'=>'frostium', 'name'=>'Frostium', 'rarity'=>'uncommon','base_price'=>60,   'origins'=>['cold','very_cold']],
    ],
];


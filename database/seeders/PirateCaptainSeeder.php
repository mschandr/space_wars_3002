<?php

namespace Database\Seeders;

use App\Models\PirateCaptain;
use App\Models\PirateFaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PirateCaptainSeeder extends Seeder
{
    private const FIRST_NAMES = [
        'Vex', 'Kira', 'Drax', 'Zara', 'Thane', 'Nyx', 'Kor', 'Lyra', 'Sable', 'Raze',
        'Vex', 'Kael', 'Dax', 'Rhea', 'Zeph', 'Nova', 'Jax', 'Mira', 'Rex', 'Vara',
        'Kane', 'Lena', 'Gage', 'Tessa', 'Vale', 'Rook', 'Echo', 'Ash', 'Blaze', 'Storm'
    ];

    private const LAST_NAMES = [
        'Blackthorne', 'Steelclaw', 'Ironfist', 'Darkwater', 'Shadowbane',
        'Stormrider', 'Voidwalker', 'Skullcrusher', 'Bloodfang', 'Nightshade',
        'Grimlock', 'Fireborn', 'Coldsteel', 'Ravencrest', 'Warforge',
        'Ghostblade', 'Dreadmaw', 'Ironheart', 'Bonecrusher', 'Hellspawn'
    ];

    private const TITLES = [
        'Captain', 'Commander', 'Admiral', 'Warlord', 'Commodore',
        'Reaver', 'Marauder', 'Scourge', 'Terror', 'Dread Lord'
    ];

    public function run(): void
    {
        $factions = PirateFaction::all();
        $totalCaptains = 0;

        foreach ($factions as $faction) {
            // Generate 3-5 captains per faction
            $captainCount = rand(3, 5);

            for ($i = 0; $i < $captainCount; $i++) {
                PirateCaptain::create([
                    'uuid' => Str::uuid(),
                    'faction_id' => $faction->id,
                    'first_name' => self::FIRST_NAMES[array_rand(self::FIRST_NAMES)],
                    'last_name' => self::LAST_NAMES[array_rand(self::LAST_NAMES)],
                    'title' => self::TITLES[array_rand(self::TITLES)],
                    'combat_skill' => rand(40, 90),
                    'attributes' => [
                        'reputation' => rand(1, 100),
                        'preferred_ship' => ['Viper', 'Corsair', 'Interdictor'][array_rand(['Viper', 'Corsair', 'Interdictor'])],
                        'tactics' => ['aggressive', 'defensive', 'opportunistic'][array_rand(['aggressive', 'defensive', 'opportunistic'])],
                    ],
                ]);

                $totalCaptains++;
            }
        }

        $this->command->info("Created {$totalCaptains} pirate captains across {$factions->count()} factions");
    }
}

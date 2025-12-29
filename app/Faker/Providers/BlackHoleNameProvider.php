<?php

namespace App\Faker\Providers;

use Faker\Provider\Base;

class BlackHoleNameProvider extends Base
{
    protected static array $mythicNames = [
        // Greek / Roman
        'Hades', 'Thanatos', 'Erebus', 'Nyx', 'Tartarus', 'Persephone',
        'Hecate', 'Pluto', 'Orcus', 'Dis Pater',
        // Norse / Germanic
        'Hel', 'Fenrir', 'Loki', 'Nidhogg', 'Angrboda', 'Surtr',
        // Egyptian
        'Anubis', 'Osiris', 'Nephthys', 'Set', 'Ammut', 'Khenti-Amentiu',
        // Mesopotamian
        'Ereshkigal', 'Nergal', 'Ninazu', 'Allatu', 'Mot', 'Lamashtu',
        // Hindu / Vedic
        'Yama', 'Kali', 'Shiva', 'Naraka', 'Chamunda', 'Bhairava',
        // Aztec / Maya
        'Mictlantecuhtli', 'Mictecacihuatl', 'Ah Puch', 'Xolotl', 'Camazotz', 'Ixtab',
        // Celtic
        'Donn', 'Morrigan', 'Arawn', 'Cailleach', 'Gwyn ap Nudd',
        // Slavic / Baltic
        'Veles', 'Chernobog', 'Morana', 'Giltine',
        // African
        'Oya', 'Eshu', 'Mbwiri', 'Kalunga',
        // Polynesian / Pacific
        'Milu', 'Hine-nui-te-po', 'Ta’aroa',
        // Japanese / Chinese
        'Izanami', 'Yama-no-Kami', 'Shinigami', 'King Yan',
    ];

    protected static array $nouns = [
        'Maw', 'Abyss', 'Rift', 'Singularity', 'Void', 'Collapse', 'Spiral',
        'Event Horizon', 'Chasm', 'Pit', 'Obscura', 'Hunger', 'Vortex', 'Gate', 'Descent',
    ];

    public static function generateBlackHoleName(): string
    {
        if (mt_rand(0, 1)) {
            $god = static::randomElement(static::$mythicNames);
            $noun = static::randomElement(static::$nouns);

            return "{$god} {$noun}";
        }

        return 'BH-'.mt_rand(100, 9999);
    }
}

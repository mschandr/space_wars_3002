<?php

namespace Database\Seeders;

use App\Models\CelestialBodyTypes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CelestialBodyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        DB::table('celestial_body_types')->delete();
        $celestial_body_types = [
            [
                'name'         => 'Star',
                'description'  => 'The fundamental building block of galaxies, generating light and energy through '
                    .'nuclear fusion.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'         => 'Planet',
                'description'  => "A celestial body orbiting a star, massive enough to be spherical under its own "
                    ."gravity but not producing its own light through nuclear fusion. Types of planets "
                    ."can include: Terrestrial Planet, Gas Giant, Ice Giant.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'         => 'Dwarf planet',
                'description'  => "A celestial body orbiting a star that is rounded under its own gravity but hasn't "
                    ."cleared the neighborhood around its orbit. Examples include Pluto, Eris, and Ceres.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'         => 'Moon',
                'description'  => 'A natural satellite orbiting a planet or dwarf planet. Moons can vary greatly in '
                    .'size and composition.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'         => 'Asteroid',
                'description'  => "An asteroid is a relatively small, rocky celestial body orbiting a star, typically "
                    ."composed of metals and minerals. They can vary significantly in size, with some "
                    ."being just a few meters across and others reaching hundreds of kilometers.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'         => 'Asteroid Belt',
                'description'  => "The asteroid belt is a region of the solar system located between the orbits of Mars "
                    ."and Jupiter. It's a vast collection of asteroids, but the individual asteroids "
                    ."within the belt are not a single entity.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'         => 'Comet',
                'description'  => "An icy celestial body with a tail of gas and dust that becomes visible when near "
                    ."the Sun. Comets originate from the outer regions of the solar system.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'         => 'Black Hole',
                'description'  => "A region of spacetime with such intense gravity that nothing, not even light, can "
                    ."escape. Black holes can form from the collapse of massive stars.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'         => 'Nebula',
                'description'  => "A giant cloud of gas and dust in interstellar space, where star formation can occur. "
                    ."Different types of nebulae include emission nebulae, reflection nebulae, and "
                    ."planetary nebulae.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'         => 'Super Massive Black Hole',
                'description'  => 'A black hole but bigger. Much, much, much bigger. Stay clear of it.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
        CelestialBodyTypes::insert($celestial_body_types);
    }
}

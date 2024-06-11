<?php

namespace Database\Seeders;

use App\Models\CelestialBodyType;
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
        DB::table('celestial_body_type')->delete();
        $celestial_body_types = [
            [
                'id'              => (string) Str::uuid(),
                'name'            => 'Star',
                'universe_weight' => 9,
                'system_weight'   => 0,
                'description'     => 'The fundamental building block of galaxies, generating light and energy through '
                    .'nuclear fusion.',
            ],
            [
                'id'              => (string) Str::uuid(),
                'name'            => 'Planet',
                'universe_weight' => 0,
                'system_weight'   => 5,
                'description'     => "A celestial body orbiting a star, massive enough to be spherical under its own "
                    ."gravity but not producing its own light through nuclear fusion. Types of planets "
                    ."can include: Terrestrial Planet, Gas Giant, Ice Giant.",
            ],
            [
                'id'          => (string) Str::uuid(),
                'name'        => 'Dwarf planet',
                'universe_weight' => 0,
                'system_weight'   => 6,
                'description' => "A celestial body orbiting a star that is rounded under its own gravity but hasn't "
                    ."cleared the neighborhood around its orbit. Examples include Pluto, Eris, and Ceres.",
            ],
            [
                'id'          => (string) Str::uuid(),
                'name'        => 'Moon',
                'universe_weight' => 0,
                'system_weight'   => 8,
                'description' => 'A natural satellite orbiting a planet or dwarf planet. Moons can vary greatly in '
                    .'size and composition.'
            ],
            [
                'id'          => (string) Str::uuid(),
                'name'        => 'Asteroid',
                'universe_weight' => 0,
                'system_weight'   => 8,
                'description' => "An asteroid is a relatively small, rocky celestial body orbiting a star, typically "
                    ."composed of metals and minerals. They can vary significantly in size, with some "
                    ."being just a few meters across and others reaching hundreds of kilometers."
            ],
            [
                'id'          => (string) Str::uuid(),
                'name'        => 'Asteroid Belt',
                'universe_weight' => 0,
                'system_weight'   => 9,
                'description' => "The asteroid belt is a region of the solar system located between the orbits of Mars "
                    ."and Jupiter. It's a vast collection of asteroids, but the individual asteroids "
                    ."within the belt are not a single entity."
            ],
            [
                'id'          => (string) Str::uuid(),
                'name'        => 'Comet',
                'universe_weight' => 0,
                'system_weight'   => 5,
                'description' => "An icy celestial body with a tail of gas and dust that becomes visible when near "
                    ."the Sun. Comets originate from the outer regions of the solar system."
            ],
            [
                'id'          => (string) Str::uuid(),
                'name'        => 'Black Hole',
                'universe_weight' => 3,
                'system_weight'   => 0,
                'description' => "A region of spacetime with such intense gravity that nothing, not even light, can "
                    ."escape. Black holes can form from the collapse of massive stars."
            ],
            [
                'id'          => (string) Str::uuid(),
                'name'        => 'Nebula',
                'universe_weight' => 5,
                'system_weight'   => 0,
                'description' => "A giant cloud of gas and dust in interstellar space, where star formation can occur. "
                    ."Different types of nebulae include emission nebulae, reflection nebulae, and "
                    ."planetary nebulae."
            ]
        ];
        CelestialBodyType::insert($celestial_body_types);
    }
}

<?php

namespace Database\Factories;

use App\Enums\BodyType;
use App\Exceptions\CelestialBodyException;
use App\Models\{CelestialBody, CelestialBodyTypes};
use Faker\SpaceProvider;
use Illuminate\Database\Eloquent\Factories\Factory as DBFactory;
use mschandr\WeightedRandom\WeightedRandomGenerator;

/**
 * @extends Factory <CelestialBody>
 */
class CelestialBodyFactory extends DBFactory
{
    protected $model = CelestialBody::class;
    protected array $celestialBodyTypeIds;
    protected int $celestialBodyTypes = 0;


    /**
     * @return array
     * @throws CelestialBodyException
     */
    public function definition(): array
    {
        $counter         = 0;
        $max_attempts    = 10;
        $cb              = new CelestialBody();
        $current_cbti_id = $cb->getRandomWeightedCelestialBodyTypeId();
        do {
            if ($counter > $max_attempts) {
                throw new CelestialBodyException('Exceeded maximum number of celestial body types');
            }
            $x = $this->faker->randomNumber(3, true);
            $y = $this->faker->randomNumber(3, true);
            $counter++;
            $check = $cb->checkForCoordinatesCollision($x, $y);
        } while ($check);
        return [
            'celestial_body_type_id' => $current_cbti_id,
            'name'                   => $this->getName($current_cbti_id, $x, $y),
            'x_coordinate'           => $x,
            'y_coordinate'           => $y,
        ];
    }

    /**
     * @returns string
     */
    public function getRandomWeightedCelestialBodyTypeId(): string
    {
        $celestial_bodies = CelestialBodyTypes::whereIn('name', BodyType::UniverseBodyTypes)
                                             ->get(['id', 'name']);
        $generator        = new WeightedRandomGenerator();

        foreach ($celestial_bodies as $key => $celestial_body_type) {
            $generator->registerValue($celestial_body_type->id, $celestial_body_type->getWeight());
        }
        return $generator->generate();
    }

    /**
     * @param  string $current_cbti_id
     * @param  int  $x
     * @param  int  $y
     *
     * @return string
     */
    public function getName(string $current_cbti_id, int $x, int $y): string
    {
        $cbti = new CelestialBodyTypes();
        $name = match ($current_cbti_id) {
            $cbti->getId(BodyType::ASTEROID_BELT)   => $this->faker->asteroidbeltName(),
            $cbti->getId(BodyTYpe::ASTEROID)        => $this->faker->asteroidName(),
            $cbti->getId(BodyTYpe::BLACK_HOLE)      => $this->faker->blackholeName($x, $y),
            $cbti->getId(BodyTYpe::COMET)           => $this->faker->cometName(),
            $cbti->getId(BodyTYpe::DWARF_PLANET)    => $this->faker->dwarfplanetName(),
            $cbti->getId(BodyTYpe::MOON)            => $this->faker->moonName(),
            $cbti->getId(BodyTYpe::NEBULA)          => $this->faker->nebulaeName(),
            $cbti->getId(BodyTYpe::PLANET)          => $this->faker->planetName(),
            $cbti->getId(BodyTYpe::STAR)            => $this->faker->starName(),
            $cbti->getId(BodyTYpe::SUPER_MASSIVE_BLACK_HOLE) => $this->faker->supermassiveblackholeName($x, $y),
            default => $this->faker->name(),
        };
        if ($name == "" || (new CelestialBody())->checkForNameCollision($name)) {
            $name = $this->faker->unknownStar();
        }
        return $name;
    }
}

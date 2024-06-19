<?php

namespace Database\Factories;

use App\Exceptions\CelestialBodyTypeException;
use App\Models\CelestialBody;
use App\Models\CelestialBodyType;
use Illuminate\Database\Eloquent\Factories\Factory as DBFactory;
use App\Enums\BodyType;
use Faker\SpaceProvider;
use FrankHouweling\WeightedRandom\WeightedRandomGenerator;

/**
 * @extends Factory <CelestialBody>
 */
class CelestialBodyFactory extends DBFactory
{
    protected $model = CelestialBody::class;
    protected array $celestialBodyTypeIds;
    protected int $celestialBodyTypes = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     * @throws CelestialBodyTypeException
     */
    public function definition(): array
    {
        $current_cbti_id = $this->getRandomWeightedCelestialBodyTypeId();
        return [
            'celestial_body_type_id' => $current_cbti_id,
            'name'                   => $this->getName($current_cbti_id),
            'x_coordinate'           => $this->faker->randomNumber(3, true),
            'y_coordinate'           => $this->faker->randomNumber(3, true),
        ];
    }

    /**
     * @return string
     * @throws CelestialBodyTypeException
     */
    public function getRandomWeightedCelestialBodyTypeId(): string
    {
        $celestial_bodies = CelestialBodyType::whereIn('name', BodyType::UniverseBodyTypes)
                                             ->get(['id', 'name']);
        $generator        = new WeightedRandomGenerator();
        try {
            foreach ($celestial_bodies as $k => $celestial_body_type) {
                $generator->registerValue($celestial_body_type->id, $celestial_body_type->getWeight());
            }
        } catch (CelestialBodyTypeException $e) {
            throw new CelestialBodyTypeException($e->getMessage());
        }
        return $generator->generate();
    }

    /**
     * @param  string|null  $current_cbti_id
     *
     * @return string
     */
    public function getName(string $current_cbti_id = null): string
    {
        $cbti = new CelestialBodyType();
        return match ($current_cbti_id) {
            $cbti->getId(BodyType::ASTEROID_BELT)               => $this->faker->asteroidbeltName(),
            $cbti->getId(BodyTYpe::ASTEROID)                    => $this->faker->asteroidName(),
            $cbti->getId(BodyTYpe::BLACK_HOLE)                  => $this->faker->blackholeName(),
            $cbti->getId(BodyTYpe::COMET)                       => $this->faker->cometName(),
            $cbti->getId(BodyTYpe::DWARF_PLANET)                => $this->faker->dwarfplanetName(),
            $cbti->getId(BodyTYpe::MOON)                        => $this->faker->moonName(),
            $cbti->getId(BodyTYpe::NEBULA)                      => $this->faker->nebulaeName(),
            $cbti->getId(BodyTYpe::PLANET)                      => $this->faker->planetName(),
            $cbti->getId(BodyTYpe::STAR)                        => $this->faker->starName(),
            $cbti->getId(BodyTYpe::SUPER_MASSIVE_BLACK_HOLE)    => $this->faker->supermassiveblackholeName(),
            default => $this->faker->name(),
        };
    }
}

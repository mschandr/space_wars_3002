<?php

namespace Database\Factories;

use App\Models\CelestialBody;
use App\Models\CelestialBodyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CelestialBody>
 */
class CelestialBodyFactory extends Factory
{
    protected $model = CelestialBody::class;
    protected array $celestial_body_type_ids;
    protected int $celestial_body_types = 0;

    public function getRandomCelestialBodyTypeId(): string
    {
        if (empty($this->celestial_body_type_ids)) {
            $this->celestial_body_type_ids = CelestialBodyType::all('id')->pluck('id')->toArray();
            $this->celestial_body_types = count($this->celestial_body_type_ids);
        }
        return $this->celestial_body_type_ids[rand(0, $this->celestial_body_types - 1)];
    }
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'celestial_body_type_id' => $this->getRandomCelestialBodyTypeId(),
            'name'                   => $this->faker->name(),
            'x_coordinate'           => $this->faker->randomNumber(3, true),
            'y_coordinate'           => $this->faker->randomNumber(3, true),
        ];
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VendorDialogue>
 */
class VendorDialogueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lineTypes = ['greeting', 'inventory_pitch', 'deal_accepted', 'deal_rejected', 'farewell'];
        $buckets = ['first_visit', 'second_visit', 'third_visit', 'repeat_customer'];

        return [
            'galaxy_vendor_profile_id' => null, // Must be set when creating
            'line_type' => $this->faker->randomElement($lineTypes),
            'interaction_bucket' => $this->faker->randomElement($buckets),
            'inventory_context' => $this->faker->optional(0.7)->randomElement(['weapons', 'minerals', 'rare_goods', 'contraband']),
            'line_text' => $this->faker->sentence(rand(5, 15)),
            'weight' => $this->faker->randomFloat(4, 0.5, 2.0),
            'generation_version' => 1,
        ];
    }

    public function greeting(): static
    {
        return $this->state(fn (array $attributes) => [
            'line_type' => 'greeting',
        ]);
    }

    public function inventoryPitch(): static
    {
        return $this->state(fn (array $attributes) => [
            'line_type' => 'inventory_pitch',
            'inventory_context' => $this->faker->randomElement(['weapons', 'minerals', 'rare_goods', 'contraband']),
        ]);
    }

    public function dealAccepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'line_type' => 'deal_accepted',
        ]);
    }

    public function dealRejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'line_type' => 'deal_rejected',
        ]);
    }

    public function farewell(): static
    {
        return $this->state(fn (array $attributes) => [
            'line_type' => 'farewell',
        ]);
    }

    public function firstVisit(): static
    {
        return $this->state(fn (array $attributes) => [
            'interaction_bucket' => 'first_visit',
        ]);
    }

    public function repeatCustomer(): static
    {
        return $this->state(fn (array $attributes) => [
            'interaction_bucket' => 'repeat_customer',
        ]);
    }
}

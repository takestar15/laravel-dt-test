<?php

namespace Database\Factories;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Translation>
 */
class TranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locale' => fake()->randomElement(['en', 'fr', 'es']),
            'key' => fake()->unique()->slug(3, '.'),
            'value' => fake()->sentence(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\TranslationTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TranslationTag>
 */
class TranslationTagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['web', 'mobile', 'desktop', 'checkout', 'marketing']),
        ];
    }
}

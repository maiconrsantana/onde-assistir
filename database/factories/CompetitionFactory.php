<?php

namespace Database\Factories;

use App\Models\Competition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Competition>
 */
class CompetitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Competicao '.fake()->unique()->numberBetween(1000, 999999);

        return [
            'provider' => 'api_football',
            'external_id' => fake()->unique()->numberBetween(1000, 9999),
            'name' => $name,
            'slug' => Str::slug($name),
            'country_code' => 'BR',
            'season_name' => now()->year,
            'active' => true,
        ];
    }
}

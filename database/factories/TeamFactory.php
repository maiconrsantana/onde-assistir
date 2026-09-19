<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Time '.fake()->unique()->numberBetween(1000, 999999);

        return [
            'provider' => 'football_data',
            'external_id' => fake()->unique()->numberBetween(10000, 99999),
            'name' => $name,
            'short_name' => Str::upper(Str::substr($name, 0, 3)),
            'slug' => Str::slug($name),
            'logo_url' => null,
        ];
    }
}

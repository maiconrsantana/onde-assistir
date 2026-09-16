<?php

namespace Database\Factories;

use App\Models\FixtureProviderMapping;
use App\Models\FootballFixture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixtureProviderMapping>
 */
class FixtureProviderMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'football_fixture_id' => FootballFixture::factory(),
            'provider' => FixtureProviderMapping::PROVIDER_THESPORTSDB,
            'external_event_id' => fake()->uuid(),
            'match_score' => fake()->randomFloat(4, 0.8, 1),
            'matched_at' => now()->utc(),
            'raw_payload' => [
                'matched_by' => 'factory',
            ],
        ];
    }
}

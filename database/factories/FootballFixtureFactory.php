<?php

namespace Database\Factories;

use App\Models\Competition;
use App\Models\FootballFixture;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FootballFixture>
 */
class FootballFixtureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'provider' => 'football_data',
            'external_id' => fake()->unique()->numberBetween(100000, 999999),
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'round' => 'Rodada '.fake()->numberBetween(1, 38),
            'starts_at' => now()->utc()->addDays(fake()->numberBetween(1, 30)),
            'status' => 'scheduled',
            'venue' => fake()->randomElement(['Neo Quimica Arena', 'Maracana', 'Morumbi', 'Allianz Parque']),
            'city' => fake()->randomElement(['Sao Paulo', 'Rio de Janeiro', 'Porto Alegre']),
            'source_url' => null,
            'raw_payload' => [
                'fixture_id' => fake()->uuid(),
            ],
            'synced_at' => now()->utc(),
            'resolution_status' => FootballFixture::RESOLUTION_PENDING,
            'review_status' => FootballFixture::REVIEW_PENDING,
            'publication_status' => FootballFixture::PUBLICATION_DRAFT,
            'resolution_hash' => fake()->sha1(),
            'resolved_at' => null,
            'resolution_invalidated_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'published_at' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Broadcaster;
use App\Models\FixtureBroadcast;
use App\Models\FootballFixture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixtureBroadcast>
 */
class FixtureBroadcastFactory extends Factory
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
            'broadcaster_id' => Broadcaster::factory(),
            'broadcast_source_id' => null,
            'access_type' => fake()->randomElement([
                FixtureBroadcast::ACCESS_FREE,
                FixtureBroadcast::ACCESS_SUBSCRIPTION,
                FixtureBroadcast::ACCESS_PAY_PER_VIEW,
            ]),
            'country_code' => 'BR',
            'source_type' => FixtureBroadcast::SOURCE_THESPORTSDB,
            'source_url' => fake()->url(),
            'confidence' => fake()->randomFloat(4, 0.7, 1),
            'verified_at' => now()->utc(),
            'needs_review' => false,
            'notes' => null,
        ];
    }
}

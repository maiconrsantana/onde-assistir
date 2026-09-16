<?php

namespace Database\Factories;

use App\Models\BroadcastSource;
use App\Models\FootballFixture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BroadcastSource>
 */
class BroadcastSourceFactory extends Factory
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
            'provider' => BroadcastSource::PROVIDER_THESPORTSDB,
            'external_event_id' => fake()->uuid(),
            'channels' => [
                [
                    'name' => 'Globo',
                    'type' => 'tv_open',
                ],
            ],
            'evidence' => [
                [
                    'url' => fake()->url(),
                    'publisher' => 'Fonte oficial',
                    'summary' => 'Confirma a transmissao no Brasil.',
                ],
            ],
            'evidence_summary' => 'Transmissao confirmada por fonte oficial.',
            'raw_response' => [
                'sanitized' => true,
            ],
            'model' => null,
            'tokens_used' => null,
            'web_search_calls' => null,
            'provider_confidence' => 0.9,
            'calculated_confidence' => 0.9,
            'result_status' => BroadcastSource::RESULT_FOUND,
            'selected' => false,
            'query_hash' => fake()->sha1(),
            'queried_at' => now()->utc(),
            'validated_at' => now()->utc(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Competition;
use App\Models\PublicationSetting;
use App\Models\RoundPublication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoundPublication>
 */
class RoundPublicationFactory extends Factory
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
            'season_name' => (string) now()->year,
            'round' => 'Rodada '.fake()->numberBetween(1, 38),
            'publication_status' => RoundPublication::STATUS_DRAFT,
            'publication_mode' => PublicationSetting::MODE_MANUAL,
            'approved_by' => null,
            'approved_at' => null,
            'published_at' => null,
            'unpublished_at' => null,
            'audit_payload' => [
                'created_by' => 'factory',
            ],
        ];
    }
}

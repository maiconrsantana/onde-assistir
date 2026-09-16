<?php

namespace Database\Factories;

use App\Models\PublicationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicationSetting>
 */
class PublicationSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'publication_mode' => PublicationSetting::MODE_MANUAL,
        ];
    }
}

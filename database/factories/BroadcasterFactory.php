<?php

namespace Database\Factories;

use App\Models\Broadcaster;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Broadcaster>
 */
class BroadcasterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Canal '.fake()->unique()->numberBetween(1000, 999999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => fake()->randomElement([
                Broadcaster::TYPE_TV_OPEN,
                Broadcaster::TYPE_TV_CLOSED,
                Broadcaster::TYPE_STREAMING,
                Broadcaster::TYPE_YOUTUBE,
                Broadcaster::TYPE_OTHER,
            ]),
        ];
    }
}

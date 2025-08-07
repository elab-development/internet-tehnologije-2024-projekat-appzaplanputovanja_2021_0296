<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $locations = ['Paris', 'Prague','Amsterdam'];
        return [
            'name' => $this->faker->words(2, true),
            'location' => $this->faker->randomElement($locations),
            'type' => $this->faker->randomElement([ 'Transport', 'Accommodation',
                         'Food&Drink', 'Culture&Sightseeing',
                        'Shopping&Souvenirs', 'Relaxation&Wellness',
                    'Educational&Volunteering','Entertainment&Leisure']),
            'price' => $this->faker->numberBetween(1,100), //$this->faker->randomFloat(2, 10, 200),
            'content' => $this->faker->paragraph(),
            'duration' => $this->faker->numberBetween(30, 240), // Duration in minutes
            'preference_types' => $this->faker->randomElements([
                 'travel_with_children', 'enjoy_nature',  'love_food_and_drink', 'want_to_relax', 'want_culture', 'seek_fun', 'adventurous', 'avoid_crowds', 'comfortable_travel', 'cafe_sitting',
            'shopping','want_to_learn', 'active_vacation', 'research_of_tradition',
            ], rand(3, 4)),
        ];
    }
}

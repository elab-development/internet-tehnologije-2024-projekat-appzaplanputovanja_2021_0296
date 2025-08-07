<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TravelPlan>
 */
class TravelPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $locations = ['Paris','Prague', 'Amsterdam'];
        $startDate = $this->faker->dateTimeBetween('now', '+1 month');
        return [
           'user_id' => User::factory(),
            'start_location' => $this->faker->city(),
            'destination' => $this->faker->randomElement($locations),//$this->faker->city(), //$Activity->location, 
            'start_date' => $startDate,
            'end_date' => (clone $startDate)->modify('+' . rand(2, 6) . ' days'),
            'budget' => $this->faker->numberBetween(1000, 5000), 
            'passenger_count' => $this->faker->numberBetween(1, 3),
            'total_cost' => 0, // This will be calculated later
            'preferences' =>  $this->faker->randomElements([
                 'love_food_and_drink', 'want_culture',
                  'seek_fun','adventurous',  'cafe_sitting', 'shopping',
                  'want_to_learn', 'active_vacation', 'research_of_tradition',
            ], rand(2, 3)),
    ];
    }
}

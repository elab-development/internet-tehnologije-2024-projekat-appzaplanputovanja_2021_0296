<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\TravelPlan;
use App\Models\Activity;


/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlanItem>
 */
class PlanItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timeFrom = $this->faker->dateTimeBetween('now', '+1 month'); //$this->faker->dateTimeBetween($TravelPlan->start_date, $TravelPlan->end_date); 
        $timeTo = (clone $timeFrom)->modify('+2 hours'); //addMinutes(Activity->duration); 
        return [
            'travel_plan_id' => TravelPlan::factory(),
            'activity_id' => Activity::factory(),
            'amount' => 0, //$this->faker->randomFloat(2, 10, 1000), 
            'name' => $this->faker->words(2, true), 
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
        ];
    }
}

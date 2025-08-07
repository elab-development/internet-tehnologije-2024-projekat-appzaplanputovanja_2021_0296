<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TravelPlan;
use App\Models\Activity;
use Carbon\Carbon;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //za svaki plan kreira aktivnosti
        TravelPlan::all()->each(function ($plan) {

            //obavezne aktivnosti - prevoz i smestaj
            $nights = Carbon::parse($plan->start_date)->diffInDays(Carbon::parse($plan->end_date));

            Activity::create([
                'name' => "Transport to {$plan->destination}",
                'location' => $plan->destination,
                'type' => 'transport',
                'price' => 30,
                'duration' => 90,
                'content' => "Transport from {$plan->start_location} to {$plan->destination}"
            ]);

            Activity::create([
                'name' => "Accommodation in {$plan->destination}",
                'location' => $plan->destination,
                'type' => 'accommodation',
                'price' => 40 * $nights,
                'duration' => $nights * 480,
                'content' => "Accommodation stay in {$plan->destination}"
            ]);

            Activity::create([
                'name' => "Transport back to {$plan->start_location}",
                'location' => $plan->destination,
                'type' => 'transport',
                'price' => 30,
                'duration' => 90,
                'content' => "Transport back to {$plan->start_location}"
            ]);

            //kreira i 7 aktivnosti sa tipom preferencije
            Activity::factory(7)->create([
                'location' => $plan->destination,
                'type' => fake()->randomElement([
                    'Food&Drink', 'Culture&Sightseeing', 'Nature&Adventure',
                    'Relaxation&Wellness', 'Educational&Volunteering',
                    'Entertainment&Leisure', 'Shopping&Souvenirs'
                ]),
            ]);   
        });
    }
}

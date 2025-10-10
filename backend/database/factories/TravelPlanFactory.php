<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\TravelPlan;
use App\Models\User;
use App\Models\Activity;
use Illuminate\Support\Carbon;

class TravelPlanFactory extends Factory
{
    protected $model = TravelPlan::class;

    private array $transportModes = ['airplane','train','car','bus'];
    private array $accClasses = [
        'hostel','guesthouse','budget_hotel','standard_hotel','boutique_hotel','luxury_hotel',
        'resort','apartment','bed_and_breakfast','villa','mountain_lodge','camping','glamping'
    ];

    private array $startLocations = ['Belgrade','Ljubljana','Zagreb','Sarajevo','Novi Sad','Niš'];

    public function definition(): array
    {
        //$user = User::inRandomOrder()->first() ?? User::factory()->create();
        $user = User::where('is_admin', false)->inRandomOrder()->first()
                            ?? User::factory()->create(['is_admin' => false]);  //admin users are not allowed to create travel plans
        
                            $locations = Activity::query()->distinct()->pluck('location')->toArray();
        if (empty($locations)) {
            $locations = ['Prague'];
        }
        $destination = $this->faker->randomElement($locations);

        $start = Carbon::now()->addDays($this->faker->numberBetween(10, 90))->startOfDay();
        $end   = (clone $start)->addDays($this->faker->numberBetween(2, 6));

        $prefsAll = Activity::availablePreferenceTypes();
        shuffle($prefsAll);
        $prefs = array_slice($prefsAll, 0, $this->faker->numberBetween(2, 5));

        return [
            'user_id'            => $user->id,
            'start_location' => $this->faker->randomElement($this->startLocations),
            'destination'        => $destination,
            'start_date'         => $start->toDateString(),
            'end_date'           => $end->toDateString(),
            'budget'             => $this->faker->numberBetween(800, 4000),
            'passenger_count'    => $this->faker->numberBetween(1, 4),
            'preferences'        => $prefs,
            'total_cost'         => 0, // biće uvećavan dodavanjem PlanItem-a
            'transport_mode'     => $this->faker->randomElement($this->transportModes),
            'accommodation_class'=> $this->faker->randomElement($this->accClasses),
        ];
    }
}

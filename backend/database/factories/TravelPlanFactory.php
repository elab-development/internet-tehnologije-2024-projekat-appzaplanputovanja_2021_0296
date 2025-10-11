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

    public function definition(): array
    {
        // Uzimamo non-admin korisnika kao vlasnika plana
        $user = User::where('is_admin', false)->inRandomOrder()->first()
            ?? User::factory()->create(['is_admin' => false]); // admin nema pravo kreiranja plana

        // Destinacije i početne lokacije iz realnih Activity zapisa
        $locations = Activity::query()->whereNotNull('location')->distinct()->pluck('location')->toArray();
        if (empty($locations)) { $locations = ['Prague']; }

        $destination = $this->faker->randomElement($locations);

        // Start lokacije iz TRANSPORT aktivnosti
        $startLocations = Activity::query()
            ->where('type','Transport')
            ->whereNotNull('start_location')
            ->distinct()->pluck('start_location')->toArray();
        if (empty($startLocations)) { $startLocations = ['Belgrade']; }
        $startLocation = $this->faker->randomElement($startLocations);

        // Izaberi transport_mode koji postoji za ovu kombinaciju start->destination
        $transportMode = Activity::query()
            ->where('type', 'Transport')
            ->where('start_location', $startLocation)
            ->where('location', $destination)
            ->orderBy('price', 'desc') // outbound skuplji → ali mod je isti
            ->value('transport_mode')
            ?: 'train';
        // Izvedi postojecu accommodation_class za destinaciju
        $accommodationClass = Activity::query()
            ->where('type','Accommodation')
            ->where('location',$destination)
            ->inRandomOrder()
            ->value('accommodation_class')
            ?: 'standard_hotel';

        // Datumi – budućnost i end posle start
        $start = Carbon::now()->addDays($this->faker->numberBetween(10, 90))->startOfDay();
        $end   = (clone $start)->addDays($this->faker->numberBetween(2, 6));

        // Preferences – iz dostupnih tipova iz Activity modela
        $prefsAll = Activity::availablePreferenceTypes();
        shuffle($prefsAll);
        $prefs = array_slice($prefsAll, 0, $this->faker->numberBetween(2, 5));

        return [
            'user_id'          => $user->id,
            'start_location'   => $startLocation,
            'destination'      => $destination,
            'start_date'       => $start->toDateString(),
            'end_date'         => $end->toDateString(),
            'budget'           => $this->faker->numberBetween(800, 4000),
            'passenger_count'  => $this->faker->numberBetween(1, 4),
            'preferences'      => $prefs,
            'total_cost'       => 0, // popunjava se generisanjem stavki
            'transport_mode'   => $transportMode,
            'accommodation_class' => $accommodationClass,
        ];
    }
}

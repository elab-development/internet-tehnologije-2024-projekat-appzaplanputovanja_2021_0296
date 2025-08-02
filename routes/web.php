<?php
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
}); 


/**testiranje modela i kreiranje putovanja u bazi podataka 
use App\Models\TravelPlan;
use App\Models\User;
use App\Models\Activity;
use App\Models\PlanItem;
use App\Models\Accommodation;
use App\Models\Ticket;

Route::get('/test-models', function () {
    $user = User::factory()->create(['name' => 'Test User']);

    $plan = TravelPlan::create([
        'user_id' => $user->id,
        'start_location' => 'Beograd',
        'destination' => 'Tara',
        'start_date' => '2025-08-15',
        'end_date' => '2025-08-20',
        'preferences' => 'nature, hiking',
        'budget' => 50000,
        'total_cost' => 0
    ]);

    $act1 = Activity::create([
        'name' => 'Vidikovac Banjska stena',
        'description' => 'Pogled na Drinu',
        'location' => 'Tara',
        'price' => 0,
        'duration' => 120,
    ]);

    $act2 = Activity::create([
        'name' => 'Planinarenje',
        'description' => 'Staza Crni vrh',
        'location' => 'Tara',
        'price' => 1500,
        'duration' => 120,
    ]);

    PlanItem::create(['travel_plan_id' => $plan->id, 'activity_id' => $act1->id,
        'name' => 'Poseta vidikovcu',
        'time_from' => '2025-08-16 10:00:00',
        'time_to' => '2025-08-16 12:00:00',
        'amount' => 0
    ]);
    PlanItem::create(['travel_plan_id' => $plan->id, 'activity_id' => $act2->id,
        'name' => 'Planinarenje',
        'time_from' => '2025-08-17 09:00:00',
        'time_to' => '2025-08-17 11:00:00',
        'amount' => 1500
    ]);

    Accommodation::create([
        'travel_plan_id' => $plan->id,
        'name' => 'Apartman Tara Lux',
        'location' => 'Tara',
        'price_per_night' => 4000,
        'total_price' => 20000,
        'number_of_nights' => 5,
        'passenger_count' => 2,
        'country' => 'Serbia',
        'email' => '',
    ]);

    Ticket::create([
        'travel_plan_id' => $plan->id , 
        'transport_type' => 'Bus',
        'price' => 2000,
        'departure_city' => 'Beograd',
        'arrival_city' => 'Tara',
        'passenger_count' => 2,
    ]);

    return 'Test uspešno prošao! Pogledaj bazu.';
}); **/


<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Activity;
use App\Models\PlanItem;
use App\Models\TravelPlan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
   
     
   public function run(): void
    {
        // Pokretanje svih zasebnih seedera
        $this->call([
            UserSeeder::class,
            TravelPlanSeeder::class,
            ActivitySeeder::class,
            PlanItemSeeder::class,
        ]);

        /**DB::transaction(function () {       //jedna transakcija baze


            User::factory(1)->create()->each(function ($user) {   
                //kreira 5 korisnika i za svakog korisnika kreira 2 travel plana
                $travelPlans = TravelPlan::factory(1)->create([ 'user_id' => $user->id,]);

                foreach ($travelPlans as $plan) {
                    $totalCost = 0;
                    
                    //dodaje svaki dan smestaja posebno u stavke
                    $days=Carbon::parse($plan->start_date)->diffInDays(Carbon::parse($plan->end_date));
                     for ($i = 0; $i < $days; $i++) {
                        $accommodation=Activity::create([
                            'name' => "Accommodation Day " . ($i + 1) . " in {$plan->destination}",
                            'location' => $plan->destination,
                            'price' => 40,
                            'duration' => 480, // 8 sati u minutima- vreme spavanja
                            'type' => 'accommodation',
                            'content' => 'Accommodation in ' . $plan->destination . ' for day ' . ($i + 1),
                        ]);
                        PlanItem::create([
                            'name' => $accommodation->name,
                            'travel_plan_id' => $plan->id,
                            'activity_id' => $accommodation->id,
                            'amount' => $accommodation->price * $plan->passenger_count,
                            'time_from' => Carbon::parse($plan->start_date)->copy()->addDays($i)->setTime(23, 30),
                            'time_to' => Carbon::parse($plan->start_date)->copy()->addDays($i + 1)->setTime(7,30),                    
                        ]);
                        $totalCost += $accommodation->price * $plan->passenger_count; 
                    };

                    //aktivnosti koje odgovaraju preferencama

                    $preferences = $plan->preferences;
                    $activityPool = Activity::factory(8)->create();
                    foreach ($activityPool as $activity) {
                        if ($activity->location !== $plan->destination) {
                            continue; //ako lokacija aktivnosti nije ista kao destinacija plana, preskoci
                        }
                        if (!empty(array_intersect($preferences, $activity->preference_types))) {
                            $from = Carbon::parse($plan->start_date)->addDays(rand(0, $days-1))->setTime(rand(8, 19),0);
                            $to = (clone $from)->addMinutes($activity->duration);
                            //proveri da li se aktivnost preklapa sa vec dodatim aktivnostima
                            $overlap = PlanItem::where('travel_plan_id', $plan->id){
                                ->where(function ($query) use ($from, $to) {
                                    $query->whereBetween('time_from', [$from, $to])//Proverava da li početak postojeće aktivnosti (time_from) pada unutar vremena nove aktivnosti
                                        ->orWhereBetween('time_to', [$from, $to])//Proverava da li kraj postojeće aktivnosti (time_to) pada unutar vremena nove aktivnosti
                                        ->orWhere(function ($q) use ($from, $to) {//Proverava da li neka aktivnost potpuno obuhvata novu aktivnost
                                            $q->where('time_from', '<=', $from)
                                                ->where('time_to', '>=', $to);
                                        });
                                })
                                ->get(); //Uzimamo sve stavke koje zadovoljavaju neki od ta tri slučaja

                            if ($overlap->isNotEmpty()) {
                                continue; //ako postoji preklapanje, preskoci ovu aktivnost
                            }

                            //izračunavanje iznosa za aktivnost
                            $amount = $activity->price * $plan->passenger_count;
                            if ($totalCost + $amount > $plan->budget) {
                                break;
                            }

                            PlanItem::create([
                                'travel_plan_id' => $plan->id,
                                'activity_id' => $activity->id,
                                'time_from' => $from,
                                'time_to' => $to,
                                'amount' => $amount,
                                'name'=>$activity->name,
                            ]);

                            $totalCost += $amount;
                        }
                    }

                    $plan->update(['total_cost' => $totalCost]); //ažuriranje ukupnog troška plana
                }
            });
        });**/
    
    }
}

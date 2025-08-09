<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PlanItem;
use App\Models\TravelPlan;
use App\Models\Activity;
use Carbon\Carbon;

class PlanItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TravelPlan::all()->each(function ($plan) {
            $totalCost = 0;
            $numNights = Carbon::parse($plan->start_date)->diffInDays(Carbon::parse($plan->end_date));

            // 1. Dodaj TRANSPORT aktivnosti
            $transportTo = Activity::where('type', 'Transport')
                ->where('name', 'like', "Transport to {$plan->destination}")
                ->first();

            $transportBack = Activity::where('type', 'Transport')
                ->where('name', 'like', "Transport back to {$plan->start_location}")
                ->first();

            if ($transportTo) {
                PlanItem::create([
                    'travel_plan_id' => $plan->id,
                    'activity_id' => $transportTo->id,
                    'name' => $transportTo->name,
                    'time_from' => Carbon::parse($plan->start_date)->setTime(8, 0),
                    'time_to' => Carbon::parse($plan->start_date)->addMinutes($transportTo->duration)->setTime(9, 30),
                    'amount' => $transportTo->price * $plan->passenger_count,
                ]);
                $totalCost += $transportTo->price * $plan->passenger_count;
            }

            if ($transportBack) {
                PlanItem::create([
                    'travel_plan_id' => $plan->id,
                    'activity_id' => $transportBack->id,
                    'name' => $transportBack->name,
                    'time_from' => Carbon::parse($plan->end_date)->setTime(16, 0),
                    'time_to' => Carbon::parse($plan->end_date)->addMinutes($transportBack->duration)->setTime(17, 30),
                    'amount' => $transportBack->price * $plan->passenger_count,
                ]);
                $totalCost += $transportBack->price * $plan->passenger_count;
            }

            // 2. Dodaj ACCOMMODATION aktivnost
            $accommodation = Activity::where('type', 'Accommodation')
                ->where('location', $plan->destination)
                ->first();

            if ($accommodation) {
                PlanItem::create([
                    'travel_plan_id' => $plan->id,
                    'activity_id' => $accommodation->id,
                    'name' => $accommodation->name,
                    'time_from' => Carbon::parse($plan->start_date)->setTime(14, 0),
                    'time_to' => Carbon::parse($plan->end_date)->setTime(8, 0),
                    'amount' => $accommodation->price * $plan->passenger_count,
                ]);
                $totalCost += $accommodation->price * $plan->passenger_count;
            }

            // 3. Dodaj aktivnosti koje odgovaraju preferencijama
            $preferences = $plan->preferences ?? []; //cuva niz korisnikovih preferencija iz datog plana
            $activities = Activity::where('type', '!=', 'Transport')
                ->where('type', '!=', 'Accommodation')
                ->where('location', $plan->destination)
                ->get();    //uzima sve aktivnosti koje se slazu sa destinacijom a nisu obavezne

            foreach ($activities as $activity) { //prolazi kroz sve njih
                if (empty(array_intersect($preferences, $activity->preference_types ?? []))) {
                    continue;
                }

                $from = Carbon::parse($plan->start_date)->addDays(rand(0, $numNights - 1))->setTime(rand(9, 18), 0);
                $to = (clone $from)->addMinutes($activity->duration);

            //Provera da li se preklapa sa postojećim PlanItem-ima
                $overlap = PlanItem::where('travel_plan_id', $plan->id)
                    ->where(function ($query) use ($from, $to) {
                        $query->whereBetween('time_from', [$from, $to]) //Proverava da li početak postojeće aktivnosti (time_from) pada unutar vremena nove aktivnosti
                            ->orWhereBetween('time_to', [$from, $to])   //Proverava da li kraj postojeće aktivnosti (time_to) pada unutar vremena nove aktivnosti
                            ->orWhere(function ($q) use ($from, $to) {  //Proverava da li neka aktivnost potpuno obuhvata novu aktivnost
                                $q->where('time_from', '<=', $from)
                                    ->where('time_to', '>=', $to);
                            });
                    })
                     ->whereHas('activity', function ($q) {   //iskljuci aktivnost Accommodation kao aktivnost sa kojom se moze preklopiti
                        $q->where('type', '!=', 'accommodation');
                    })
                    ->exists(); //Uzima sve stavke koje zadovoljavaju neki od ta tri slučaja

                if ($overlap) {
                    continue; //ako postoji preklapanje, preskoci ovu aktivnost
                } 
                

                $amount = $activity->price * $plan->passenger_count;

                if ($totalCost + $amount > $plan->budget) { //ogranicenje budzeta
                    break;                  
                }

                PlanItem::create([
                    'travel_plan_id' => $plan->id,
                    'activity_id' => $activity->id,
                    'name' => $activity->name,
                    'time_from' => $from,
                    'time_to' => $to,
                    'amount' => $amount,
                ]);

                $totalCost += $amount;
            }

            // 4. Ažuriraj total_cost
            $plan->update(['total_cost' => $totalCost]);
        });
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Activity;
use App\Models\TravelPlan;
use App\Models\PlanItem;
use App\Models\Setting;

class TravelPlanSeeder extends Seeder
{
    public function run(): void
    {
       // $users = User::all();
       $users = User::where('is_admin', false)->get(); // samo obični korisnici mogu kreirati planove putovanja

        if ($users->isEmpty()) {
            $users = User::factory()->count(2)->create();
        }

        $locations = Activity::query()->distinct()->pluck('location')->toArray();
        if (empty($locations)) {
            $this->command->warn('No activities found. Run ActivitySeeder first.');
            return;
        }

        foreach ($users as $user) {
            // izaberi destinaciju koja ima i Transport i Accommodation varijante
            $destination = collect($locations)->random();

            // izaberi postojeće enum vrednosti (moraju postojati Activity varijante) 
            $modes = Activity::where('type','Transport')->where('location',$destination)
                ->distinct()->pluck('transport_mode')->filter()->values()->all();
            $accs  = Activity::where('type','Accommodation')->where('location',$destination)
                ->distinct()->pluck('accommodation_class')->filter()->values()->all();
            if (empty($modes) || empty($accs)) {
                // preskoči ovu destinaciju ako nema potrebne varijante
                continue;
            }

            $transportMode = collect($modes)->random();
            $accClass      = collect($accs)->random();

            $start = Carbon::now()->addDays(rand(15, 60))->startOfDay();
            $end   = (clone $start)->addDays(rand(3, 5));

            // formiraj preferences kao podskup dostupnih tipova 
            $prefsAll = Activity::availablePreferenceTypes();
            shuffle($prefsAll);
            $prefs = array_slice($prefsAll, 0, rand(4, 6));

            $plan = TravelPlan::factory()->make([
                'user_id'             => $user->id,
                'start_location'      => collect(['Belgrade','Novi Sad','Niš','Subotica'])->random(),
                'destination'         => $destination,
                'start_date'          => $start->toDateString(),
                'end_date'            => $end->toDateString(),
                'budget'              => rand(1200, 3000),
                'passenger_count'     => rand(1, 4),
                'preferences'         => $prefs,
                'total_cost'          => 0,
                'transport_mode'      => $transportMode,
                'accommodation_class' => $accClass,
            ]);

            // kreiraj u transakciji + obavezne PlanItem
            DB::transaction(function () use ($plan) {
                $plan->save();

                $outboundStart  = Setting::getValue('outbound_start', '08:00');
                $checkinTime    = Setting::getValue('checkin_time',   '14:00');
                $checkoutTime   = Setting::getValue('checkout_time',  '09:00');
                $returnStart    = Setting::getValue('return_start',   '15:00');

                // TRANSPORT zapis koji se slaže sa izborom plana
                $transport = Activity::where('type','Transport')
                    ->where('location', $plan->destination)
                    ->where('transport_mode', $plan->transport_mode)
                    ->orderBy('price')
                    ->first();

                // ACCOMMODATION zapis koji se slaže sa izborom plana
                $accommodation = Activity::where('type','Accommodation')
                    ->where('location', $plan->destination)
                    ->where('accommodation_class', $plan->accommodation_class)
                    ->orderBy('price')
                    ->first();

                // 1) Odlazni transport (start_date @ outbound_start)
                $depFrom = Carbon::parse($plan->start_date.' '.$outboundStart);
                $depTo   = (clone $depFrom)->addMinutes($transport->duration);
                $this->attachItem($plan, $transport, $depFrom, $depTo, "Transport {$plan->start_location} → {$plan->destination} ({$plan->transport_mode})");

                // 2) Smeštaj (od checkin do checkout, preskače proveru preklapanja po prirodi)
                $accFrom = Carbon::parse($plan->start_date.' '.$checkinTime);
                $accTo   = Carbon::parse($plan->end_date.' '.$checkoutTime);
                $this->attachItem($plan, $accommodation, $accFrom, $accTo, "Accommodation in {$plan->destination} ({$plan->accommodation_class})");

                // 3) Povratni transport (end_date @ return_start)
                $retFrom = Carbon::parse($plan->end_date.' '.$returnStart);
                $retTo   = (clone $retFrom)->addMinutes($transport->duration);
                $this->attachItem($plan, $transport, $retFrom, $retTo, "Transport {$plan->destination} → {$plan->start_location} ({$plan->transport_mode})");
            });
        }
    }

    private function attachItem(TravelPlan $plan, Activity $activity, Carbon $from, Carbon $to, string $name): void
    {
        $amount = $activity->price * $plan->passenger_count; // amount = price * passenger_count 
        if ($plan->total_cost + $amount > $plan->budget) {
            return; // poštuj ograničenje budžeta pre kreiranja 
        }

        PlanItem::create([
            'travel_plan_id' => $plan->id,
            'activity_id'    => $activity->id,
            'name'           => $name,
            'time_from'      => $from,
            'time_to'        => $to,
            'amount'         => $amount,
        ]);

        $plan->increment('total_cost', $amount);
    }
}

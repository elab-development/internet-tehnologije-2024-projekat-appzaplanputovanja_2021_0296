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
        $users = User::where('is_admin', false)->get();
        if ($users->isEmpty()) {
            $users = User::factory()->count(2)->create();
        }

        $locations = Activity::query()->distinct()->pluck('location')->toArray();
        if (empty($locations)) {
            $this->command->warn('No activities found. Run ActivitySeeder first.');
            return;
        }

        $startLocations = ['Belgrade','Ljubljana','Zagreb','Sarajevo','Novi Sad','Niš'];

        foreach ($users as $user) {
            $destination = collect($locations)->random();

            $modes = Activity::where('type','Transport')->where('location',$destination)
                ->distinct()->pluck('transport_mode')->filter()->values()->all();
            $accs  = Activity::where('type','Accommodation')->where('location',$destination)
                ->distinct()->pluck('accommodation_class')->filter()->values()->all();
            if (empty($modes) || empty($accs)) {
                continue;
            }

            $transportMode = collect($modes)->random();
            $accClass      = collect($accs)->random();

            $start = Carbon::now()->addDays(rand(15, 60))->startOfDay();
            $end   = (clone $start)->addDays(rand(3, 5));

            $prefsAll = Activity::availablePreferenceTypes();
            shuffle($prefsAll);
            $prefs = array_slice($prefsAll, 0, rand(4, 6));

            $plan = TravelPlan::factory()->make([
                'user_id'             => $user->id,
                'start_location'      => collect($startLocations)->random(),
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

            DB::transaction(function () use ($plan) {
                $plan->save();

                // Settings (fallback ako nema zapisa)
                $outboundStart  = Setting::getValue('outbound_start', '08:00');
                $checkinTime    = Setting::getValue('checkin_time',   '14:00');
                $checkoutTime   = Setting::getValue('checkout_time',  '09:00');
                $returnStart    = Setting::getValue('return_start',   '15:00');

                $settings = (object)[
                    'outbound_start' => $outboundStart,
                    'checkin_time'   => $checkinTime,
                    'checkout_time'  => $checkoutTime,
                    'return_start'   => $returnStart,
                ];

                // === OBAVEZNE STAVKE (bez budžet filtera; force=true) ===
                $this->attachMandatoryItems($plan, $settings);
            });
        }
    }

    // --- obavezne stavke izdvojene kao METODA na nivou KLASE ---
    private function attachMandatoryItems(TravelPlan $plan, $settings): void
    {
        // 1) OUT transport
        $outName = "Transport {$plan->start_location} → {$plan->destination} ({$plan->transport_mode})";
        $out = Activity::firstOrCreate([
            'type'           => 'Transport',
            'location'       => $plan->destination,
            'transport_mode' => $plan->transport_mode,
            'name'           => $outName,
        ], [
            'price' => 80, 'duration' => 120,
            'preference_types' => $plan->preferences ?? [],
        ]);

        // 2) RETURN transport
        $retName = "Transport {$plan->destination} → {$plan->start_location} ({$plan->transport_mode})";
        $ret = Activity::firstOrCreate([
            'type'           => 'Transport',
            'location'       => $plan->destination,
            'transport_mode' => $plan->transport_mode,
            'name'           => $retName,
        ], [
            'price' => 80, 'duration' => 120,
            'preference_types' => $plan->preferences ?? [],
        ]);

        // 3) ACCOMMODATION
        $accName = "Accommodation in {$plan->destination} ({$plan->accommodation_class})";
        $acc = Activity::firstOrCreate([
            'type'                => 'Accommodation',
            'location'            => $plan->destination,
            'accommodation_class' => $plan->accommodation_class,
            'name'                => $accName,
        ], [
            'price' => 65, 'duration' => 24*60,
            'preference_types' => $plan->preferences ?? [],
        ]);

        // vremena
        $outTime = Carbon::parse(($plan->start_date).' '.($settings->outbound_start ?? '08:00'));
        $retTime = Carbon::parse(($plan->end_date).' '.($settings->return_start   ?? '15:00'));
        $checkin = Carbon::parse(($plan->start_date).' '.($settings->checkin_time ?? '14:00'));
        $checkout= Carbon::parse(($plan->end_date).' '.($settings->checkout_time  ?? '10:00'));

        // force = true  -> ignoriše budžetski cutoff za obavezne
        $this->attachItem($plan, $out, $outTime, (clone $outTime)->addMinutes($out->duration), $outName, true);
        $this->attachItem($plan, $acc, $checkin, $checkout, $accName, true);
        $this->attachItem($plan, $ret, $retTime, (clone $retTime)->addMinutes($ret->duration), $retName, true);
    }

    // --- amount pravilno (smeštaj = cena * broj noći), + opcioni $force ---
    private function attachItem(TravelPlan $plan, Activity $activity, Carbon $from, Carbon $to, string $name, bool $force = false): void
    {
        // obračun iznosa
        if ($activity->type === 'Accommodation') {
            $nights = max(1, Carbon::parse($plan->end_date)->diffInDays(Carbon::parse($plan->start_date)));
            $amount = $activity->price * $nights; // po noćenju
        } else {
            $amount = $activity->price * $plan->passenger_count; // transport i ostalo po osobi
        }

        // budžet filter samo ako NIJE force
        if (!$force && ($plan->total_cost + $amount > $plan->budget)) {
            return;
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


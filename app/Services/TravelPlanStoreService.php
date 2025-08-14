<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\PlanItem;
use App\Models\Setting;
use App\Models\TravelPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TravelPlanStoreService
{
    /**
     * Kreira TravelPlan (već validiran), generiše obavezne aktivnosti
     * i popunjava plan aktivnostima po preferencama i budžetu.
     */
    public function createWithGeneratedItems(array $data): TravelPlan
    {
        return DB::transaction(function () use ($data) {
            /** @var TravelPlan $plan */
            $plan = TravelPlan::create($data);
            $plan->refresh();

            // 1) Obavezne aktivnosti (outbound, accommodation, return)
            $this->generateMandatoryItems($plan);

            // 2) Popuni raspored odgovarajućim aktivnostima (koliko budžet dozvoljava)
            $this->fillWithMatchingActivities($plan);

            return $plan->fresh(['planItems.activity']);
        });
    }

    /**
     * Generiše: transport (start->dest), accommodation (14:00->09:00), transport (dest->start).
     * Poštuje budžet i računa amount = price * passenger_count.
     */
    public function generateMandatoryItems(TravelPlan $plan): void
    {
        // podrazumevana vremena- admin posle moze rucno menjati PlanItem
        $outboundStart = Setting::getValue('outbound_start', '08:00');
        $checkinTime   = Setting::getValue('checkin_time',   '14:00');
        $checkoutTime  = Setting::getValue('checkout_time',  '09:00');
        $returnStart   = Setting::getValue('return_start',   '15:00');

        // Pronadji TRANSPORT varijantu (po enum transport_mode) za datu destinaciju
        $transport = Activity::where('type','Transport')
            ->where('location', $plan->destination)
            ->where('transport_mode', $plan->transport_mode)
            ->orderBy('price')->first();

        // Pronadji ACCOMMODATION varijantu (po enum accommodation_class) za destinaciju
        $accommodation = Activity::where('type','Accommodation')
            ->where('location', $plan->destination)
            ->where('accommodation_class', $plan->accommodation_class)
            ->orderBy('price')->first();

        if (!$transport || !$accommodation) {
            abort(422, 'Mandatory activities are missing for the selected transport or accommodation. Add the appropriate Activity variants.');
        }
        // Outbound
        $from = Carbon::parse($plan->start_date.' '.$outboundStart);
        $to   = (clone $from)->addMinutes($transport->duration);
        $transport->name="Transport {$plan->start_location} → {$plan->destination} ({$plan->transport_mode})";
        $this->createItemIfBudgetAllows(
            $plan,
            $transport,
            $from,
            $to
        );

        // Accommodation – ceo boravak
        $accFrom = Carbon::parse($plan->start_date)->setTimeFromTimeString($checkinTime);
        $accTo   = Carbon::parse($plan->end_date)->setTimeFromTimeString($checkoutTime);
        $accommodation->name = "Accommodation in {$plan->destination} ({$plan->accommodation_class})";
        $this->createItemIfBudgetAllows(
            $plan,
            $accommodation,
            $accFrom,
            $accTo,
            ignoreOverlap: true // ne računa se u overlap
        );

        // Return
        $retFrom = Carbon::parse($plan->end_date.' '.$returnStart);
        $retTo   = (clone $retFrom)->addMinutes($transport->duration);
        $transport->name = "Transport {$plan->destination} → {$plan->start_location} ({$plan->transport_mode})";
        $this->createItemIfBudgetAllows(
            $plan,
            $transport,
            $retFrom,
            $retTo
        );
    }

    /**
     * Punjenje plana aktivnostima koje se uklapaju (lokacija + prefs), bez overlapa i do budžeta.
     */
    public function fillWithMatchingActivities(TravelPlan $plan): void
    {
        // Pročitaj podešavanja, uz fallback vrednosti
        $hasSetting = class_exists(Setting::class);
        $defaultDayStart = $hasSetting ? Setting::getValue('default_day_start', '09:00') : '09:00';
        $defaultDayEnd   = $hasSetting ? Setting::getValue('default_day_end',   '20:00') : '20:00';
        $bufferAfterOutboundMin = (int) ($hasSetting ? Setting::getValue('buffer_after_outbound_min', 30) : 30);
        $bufferBeforeReturnMin  = (int) ($hasSetting ? Setting::getValue('buffer_before_return_min', 0)  : 0);

        $start = Carbon::parse($plan->start_date);
        $end   = Carbon::parse($plan->end_date);

        // Nađi TRANSPORT stavke (outbound/return) za određivanje dnevnih granica
        $items = $plan->planItems()->with('activity')->get();

        $outbound = $items->filter(fn($pi) =>
            $pi->activity && $pi->activity->type === 'Transport' &&
            Carbon::parse($pi->time_from)->isSameDay($start)
        )->sortBy('time_from')->first();

        $return = $items->filter(fn($pi) =>
            $pi->activity && $pi->activity->type === 'Transport' &&
            Carbon::parse($pi->time_from)->isSameDay($end)
        )->sortByDesc('time_from')->first();

        //Pripremi kandidate: destinacija + poklapanje bar jedne preferencije, isključi Transport/Accommodation
        $prefs = collect($plan->preferences ?? []);
        $candidates = Activity::where('location', $plan->destination)
            ->whereNotIn('type', ['Transport','Accommodation'])
            ->get()
            ->filter(function(Activity $a) use ($prefs) {
                $ap = collect($a->preference_types ?? []);
                return $prefs->isEmpty() ? true : $prefs->intersect($ap)->isNotEmpty();
            })
            ->sortBy('price') // jeftinije prvo – efikasnije punjenje budžeta
            ->values();

         //Iteriraj dane
        $day = $start->copy();
        while ($day->lte($end)) {

            // Odredi dnevni prozor
            if ($day->isSameDay($start)) {
                $dayStart = $outbound
                    ? Carbon::parse($outbound->time_to)->copy()->addMinutes($bufferAfterOutboundMin)
                    : Carbon::parse($day->format('Y-m-d').' '.$defaultDayStart);
                $dayEnd = Carbon::parse($day->format('Y-m-d').' '.$defaultDayEnd);
            } elseif ($day->isSameDay($end)) {
                $dayStart = Carbon::parse($day->format('Y-m-d').' '.$defaultDayStart);
                $dayEnd   = $return
                    ? Carbon::parse($return->time_from)->copy()->subMinutes($bufferBeforeReturnMin)
                    : Carbon::parse($day->format('Y-m-d').' '.$defaultDayEnd);
            } else {
                $dayStart = Carbon::parse($day->format('Y-m-d').' '.$defaultDayStart);
                $dayEnd   = Carbon::parse($day->format('Y-m-d').' '.$defaultDayEnd);
            }

            // ako je prozor izopačen (npr. povratak vrlo rano), preskoči dan
            if ($dayStart->gte($dayEnd)) {
                $day->addDay();
                continue;
            }

            // Pokušaj da spakuješ aktivnosti u (prvu moguću) rupu tog dana
            foreach ($candidates as $activity) {

                // nađi prvu slobodnu rupu u dnevnom prozoru za trajanje aktivnosti
                $slot = $this->findNextAvailableTimeSlot(
                    $plan,
                    $dayStart,
                    $dayEnd,
                    (int) $activity->duration,
                );

                if (!$slot) {
                    continue; // nema mesta za ovu aktivnost tog dana – probaj sledeću
                }

                [$slotFrom, $slotTo] = $slot;

                $this->createItemIfBudgetAllows($plan, $activity, $slotFrom, $slotTo);

                //helper u sledećem pozivu vidi nov zauzet termin
            }

            $day->addDay();
        }
    }

    /**
     * Pronalazi sledeći slobodan slot (bez overlapa, Accommodation se ignoriše u blokadi).
     */
    public function findNextAvailableTimeSlot(TravelPlan $plan, Carbon $winStart, Carbon $winEnd, int $durationMin): ?array
    {
        $gapMin = (int) Setting::getValue('gap_between_activities_min', 10);

        // zauzeti slotovi (bez Accommodation)-intervali koji se bilo kako seku sa prozorom
        $busy = $plan->planItems()
            ->whereHas('activity', fn($q) => $q->where('type','!=','Accommodation'))
            ->where('time_from','<',$winEnd)
            ->where('time_to','>',$winStart)
            ->orderBy('time_from')
            ->get(['time_from','time_to'])
            ->map(function($pi) use ($gapMin, $winStart, $winEnd) {
                // isečemo na prozor i proširimo za gap (da očuvamo „distance”)
                $from = Carbon::parse($pi->time_from)->max($winStart)->copy()->subMinutes($gapMin);
                $to   = Carbon::parse($pi->time_to)->min($winEnd)->copy()->addMinutes($gapMin);
                return [$from,$to];
            })
            ->values();

        // Ako nema zauzetih, staje li cela aktivnost u window?
        if ($busy->isEmpty()) {
            $slotEnd = $winStart->copy()->addMinutes($durationMin);
            return $slotEnd->lte($winEnd) ? [$winStart->copy(), $slotEnd] : null;
        }

        // merge preklapajucih intervala
        $merged = [];
        foreach ($busy as [$f,$t]) {
            if (!$merged) { $merged[] = [$f->copy(),$t->copy()]; continue; }
            [$lf,$lt] = $merged[count($merged)-1];
            if ($f->lte($lt)) { $merged[count($merged)-1][1] = $t->max($lt); }
            else { $merged[] = [$f->copy(),$t->copy()]; }
        }

        //Traži rupu PRE prvog zauzeća
        [$f0,$t0] = $merged[0];
        $slotEnd = $winStart->copy()->addMinutes($durationMin);
        if ($slotEnd->lte($f0)) return [$winStart->copy(), $slotEnd];

        //Traži rupu IZMEĐU zauzeća
        for ($i=0; $i < count($merged)-1; $i++) {
            [$aF,$aT] = $merged[$i];
            [$bF,$bT] = $merged[$i+1];
            $gapStart = $aT->copy(); //kraj prethodnog zauzeća
            $gapEnd   = $bF->copy(); //pocetak sledeceg zauzeća
            $slotEnd  = $gapStart->copy()->addMinutes($durationMin);
            if ($slotEnd->lte($gapEnd) && $gapStart->gte($winStart)) {
                return [$gapStart, $slotEnd];
            }
        }

        //Traži rupu POSLE poslednjeg zauzeća
        [$lf,$lt] = $merged[count($merged)-1];
        $gapStart = $lt->copy();
        $slotEnd  = $gapStart->copy()->addMinutes($durationMin);
        if ($slotEnd->lte($winEnd)) return [$gapStart, $slotEnd];

        return null; // nema slobodnog slot-a
    }

    /**
     * Kreira PlanItem samo ako ne prelazi budžet i ne krši pravila (return-guard, overlap…).
     */
    public function createItemIfBudgetAllows(
        TravelPlan $plan,
        Activity $activity,
        Carbon $timeFrom,
        Carbon $timeTo,
        bool $ignoreOverlap = false
    ): ?PlanItem {
        // guard: kraj mora biti pre plan.end_date (povratni transport)
        if ($timeTo->gt(Carbon::parse($plan->end_date))) {
            return null;
        }

        // overlap provera (Accommodation se ignoriše)
        if (!$ignoreOverlap) {
            $overlap = $plan->planItems()
                ->whereHas('activity', fn($q) => $q->where('type','!=','Accommodation'))
                ->where('time_from','<',$timeTo)
                ->where('time_to','>',$timeFrom)
                ->exists();
            if ($overlap) return null;
        }

        $amount = $activity->price * $plan->passenger_count;
        if ($plan->total_cost + $amount > $plan->budget) {
            return null;
        }

        $item = PlanItem::create([
            'travel_plan_id' => $plan->id,
            'activity_id'    => $activity->id,
            'name'           => $activity,
            'time_from'      => $timeFrom,
            'time_to'        => $timeTo,
            'amount'         => $amount,
        ]);

        $plan->increment('total_cost', $amount);
        $plan->refresh();

        return $item;
    }
}

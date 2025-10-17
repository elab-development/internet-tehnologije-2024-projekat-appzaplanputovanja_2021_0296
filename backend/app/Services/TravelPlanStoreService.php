<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\PlanItem;
use App\Models\Setting;
use App\Models\TravelPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ConflictException;

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

            $this->assertMandatoryItems($plan);

            $this->syncTotalCost($plan);
            
            return $plan->fresh(['planItems.activity']);
        });
    }

   private function assertMandatoryItems(TravelPlan $plan): void
    {
        // mora biti tačno 2 transporta
        $transportCount = $plan->planItems()
            ->whereHas('activity', fn($q) => $q->where('type', 'Transport'))
            ->count();

        // i bar 1 smeštajna stavka
        $accommodationItem = $plan->planItems()
            ->whereHas('activity', fn($q) => $q->where('type', 'Accommodation'))
            ->first();

        if ($transportCount !== 2 || !$accommodationItem) {
            throw new BusinessRuleException( 
                'Mandatory items missing. Increase budget or adjust dates.', 
                'MANDATORY_MISSING'
            );
        }

        // smeštaj mora da pokriva ceo boravak (po Settings)
        $checkin  = Setting::getValue('checkin_time', '14:00');
        $checkout = Setting::getValue('checkout_time','09:00');

        $shouldFrom = Carbon::parse($plan->start_date.' '.$checkin);
        $shouldTo   = Carbon::parse($plan->end_date.' '.$checkout);

        if (!Carbon::parse($accommodationItem->time_from)->equalTo($shouldFrom) ||
            !Carbon::parse($accommodationItem->time_to)->equalTo($shouldTo)) {
           throw new BusinessRuleException(
                'Accommodation must span the entire stay according to check-in/out settings.',
                'ACCOMMODATION_NOT_SPANNING_STAY'
            );
        }
    }
    /**
     * Generiše: transport (start->dest), accommodation (14:00->09:00), transport (dest->start).
     * Poštuje budžet i računa amount = price * passenger_count.
     */
    public function generateMandatoryItems(TravelPlan $plan): void
    {
        // Podrazumevana vremena (admin ih menja kroz Settings)
        $outboundStart = Setting::getValue('outbound_start', '08:00');
        $checkinTime   = Setting::getValue('checkin_time',   '14:00');
        $checkoutTime  = Setting::getValue('checkout_time',  '09:00');
        $returnStart   = Setting::getValue('return_start',   '15:00');

        // 1) TRANSPORT: tačna ruta i mod
        // OUTBOUND = skuplji
        $outboundTransport = Activity::where('type','Transport')
        ->where('transport_mode', $plan->transport_mode)
        ->where('start_location', $plan->start_location)
        ->where('location', $plan->destination)
        ->orderBy('price', 'desc')
        ->first();

        // RETURN = jeftiniji
        $returnTransport = Activity::where('type','Transport')
        ->where('transport_mode', $plan->transport_mode)
        ->where('start_location', $plan->start_location)
        ->where('location', $plan->destination)
        ->orderBy('price', 'asc')
        ->first();

        // 2) ACCOMMODATION: tačna klasa u destinaciji
        $accommodation = Activity::where('type','Accommodation')
            ->where('location', $plan->destination)
            ->where('accommodation_class', $plan->accommodation_class)
            ->orderBy('price')
            ->first();

        if (!$outboundTransport || !$returnTransport || !$accommodation) {
            throw new BusinessRuleException( 
                'There are no activities for the selected transportation routes or accommodation at the selected location.', 
                 'MANDATORY_VARIANTS_MISSING'
            );
        }

        // 3) Provera budžeta za obavezne
        $startDate = Carbon::parse($plan->start_date)->startOfDay();
        $endDate   = Carbon::parse($plan->end_date)->startOfDay();
        $nights    = max(1, $startDate->diffInDays($endDate));
     

        $mandatoryTotal =
            ($outboundTransport->price + $returnTransport->price) * $plan->passenger_count
        + ($accommodation->price * $nights * $plan->passenger_count);

        if ($mandatoryTotal > (float)$plan->budget) {
            throw new BusinessRuleException( 
                'The budget does not cover mandatory transportation (both ways) and accommodation.',
                 'BUDGET_TOO_LOW_FOR_MANDATORY'
            ); 
        }


        // 4) Kreiranje 3 obavezne stavke, tačnim redosledom

        // a) Outbound (start_date @ outboundStart)
        $from = Carbon::parse($plan->start_date.' '.$outboundStart);
        $to   = (clone $from)->addMinutes((int)$outboundTransport->duration);

        // prilagodimo naziv da bude jasan u planu
        $ob = clone $outboundTransport;
        $ob->name = "Transport {$plan->start_location} → {$plan->destination} ({$plan->transport_mode})";

        $this->createMandatoryItemOrFail($plan, $ob, $from, $to);

        // b) Accommodation (od checkin do checkout)
        $accFrom = Carbon::parse($plan->start_date)->setTimeFromTimeString($checkinTime);
        $accTo   = Carbon::parse($plan->end_date)->setTimeFromTimeString($checkoutTime);

        $acc = clone $accommodation;
        $acc->name = "Accommodation in {$plan->destination} ({$plan->accommodation_class})";

        $this->createMandatoryItemOrFail($plan, $acc, $accFrom, $accTo, ignoreOverlap: true);

        // c) Return (end_date @ returnStart)
        $retFrom = Carbon::parse($plan->end_date.' '.$returnStart);
        $retTo   = (clone $retFrom)->addMinutes((int)$returnTransport->duration);

        $ret = clone $returnTransport;
        $ret->name = "Transport {$plan->destination} → {$plan->start_location} ({$plan->transport_mode})";

        $this->createMandatoryItemOrFail($plan, $ret, $retFrom, $retTo);
    }


    /**
     * Punjenje plana aktivnostima koje se uklapaju (lokacija + prefs), bez overlapa i do budžeta.
     */
    public function fillWithMatchingActivities(TravelPlan $plan): void
    {
        // Podešavanja
        $hasSetting = class_exists(Setting::class);
        $defaultDayStart = $hasSetting ? Setting::getValue('default_day_start', '09:00') : '09:00';
        $defaultDayEnd   = $hasSetting ? Setting::getValue('default_day_end',   '20:00') : '20:00';
        $bufferAfterOutboundMin = (int) ($hasSetting ? Setting::getValue('buffer_after_outbound_min', 30) : 30);
        $bufferBeforeReturnMin  = (int) ($hasSetting ? Setting::getValue('buffer_before_return_min', 0)  : 0);

        // Cilj potrošnje (više prema budžetu)
        $target = (int) floor((float)$plan->budget * 0.95);

        $start = Carbon::parse($plan->start_date);
        $end   = Carbon::parse($plan->end_date);

        // TRANSPORT stavke za granice prvog/poslednjeg dana
        $items = $plan->planItems()->with('activity')->get();
        $outbound = $items->first(fn($pi) =>
            $pi->activity && $pi->activity->type === 'Transport' &&
            Carbon::parse($pi->time_from)->isSameDay($start)
        );
        $return = $items->filter(fn($pi) =>
            $pi->activity && $pi->activity->type === 'Transport' &&
            Carbon::parse($pi->time_from)->isSameDay($end)
        )->sortByDesc('time_from')->first();

        // ---- DEFINIŠI $baseQuery (KANDIDATI) ----
        $prefs = collect($plan->preferences ?? []);

        // sve aktivnosti u destinaciji osim Transport/Accommodation
        $allAtDestination = Activity::query()
            ->where('location', $plan->destination)
            ->whereNotIn('type', ['Transport','Accommodation'])
            ->get();

        // filtriraj po preferencama (ako postoje)
        $baseQuery = $allAtDestination->filter(function (Activity $a) use ($prefs) {
            if ($prefs->isEmpty()) return true;
            $ap = collect($a->preference_types ?? []);
            return $ap->isNotEmpty() && $prefs->intersect($ap)->isNotEmpty();
        })->values();

        // podeli na plaćene i besplatne
        $candidatesPaid = $baseQuery->filter(fn($a) => (int)$a->price > 0)
            ->sortByDesc('price') // skuplje prvo da brže priđemo cilju
            ->values();

        $candidatesFree = $baseQuery->filter(fn($a) => (int)$a->price === 0)
            ->values();

        // Limiti za FREE
        $maxFreePerPlan = 4;
        $maxFreePerDay  = 2;

        $day = $start->copy();
        while ($day->lte($end)) {
            // dnevni prozor [dayStart, dayEnd]
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
            if ($dayStart->gte($dayEnd)) { $day->addDay(); continue; }

            // Per-day brojač za free i globalni broj već ubačenih free
            $maxFreePerPlanUsed = $plan->planItems()
                ->whereHas('activity', fn($q) => $q->whereNotIn('type', ['Transport','Accommodation']))
                ->get()
                ->filter(fn($pi) => (int)optional($pi->activity)->price === 0)
                ->count();

            $freeUsedDay = 0;

            // 1) PLAĆENE – do targeta budžeta
            foreach ($candidatesPaid as $activity) {
                if ((float)$plan->total_cost >= $target) break;

                $slot = $this->findNextAvailableTimeSlot($plan, $dayStart, $dayEnd, (int)$activity->duration);
                if (!$slot) continue;

                [$from, $to] = $slot;
                $created = $this->createItemIfBudgetAllows($plan, $activity, $from, $to);
                if ($created && (float)$plan->total_cost >= $target) break;
            }

            // 2) FREE – popuni rupe bez obzira na budžet (uz limite)
            if ($maxFreePerPlanUsed < $maxFreePerPlan) {
                foreach ($candidatesFree as $activity) {
                    if ($maxFreePerPlanUsed >= $maxFreePerPlan || $freeUsedDay >= $maxFreePerDay) break;

                    $slot = $this->findNextAvailableTimeSlot($plan, $dayStart, $dayEnd, (int)$activity->duration);
                    if (!$slot) continue;

                    [$from, $to] = $slot;

                    // ako helper blokira amount=0, direktno kreiraj PlanItem:
                    $ok = $this->createItemIfBudgetAllows($plan, $activity, $from, $to);
                    if (!$ok) {
                        PlanItem::create([
                            'travel_plan_id' => $plan->id,
                            'activity_id'    => $activity->id,
                            'name'           => $activity->name,
                            'time_from'      => $from,
                            'time_to'        => $to,
                            'amount'         => 0,
                        ]);
                        $plan->refresh();
                    }

                    $maxFreePerPlanUsed++;
                    $freeUsedDay++;
                }
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

        $amount = $this->computeAmount($plan, $activity, $timeFrom, $timeTo);
        if ($plan->total_cost + $amount > $plan->budget) {
            return null;
        }


        $item = PlanItem::create([
            'travel_plan_id' => $plan->id,
            'activity_id'    => $activity->id,
            'name'           => $activity->name,
            'time_from'      => $timeFrom,
            'time_to'        => $timeTo,
            'amount'         => $amount,
        ]);

        $plan->increment('total_cost', $amount);
        $plan->refresh();

        return $item;
    }
    public function createMandatoryItemOrFail( 
        TravelPlan $plan,
        Activity $activity,
        Carbon $timeFrom,
        Carbon $timeTo,
        bool $ignoreOverlap = false
    ): PlanItem {
        // guard: kraj mora biti pre ili na dan end_date (za povratni transport)
        if ($timeTo->gt(Carbon::parse($plan->end_date)->endOfDay())) {
            throw new BusinessRuleException(
                'Mandatory item exceeds the plan end date window.', 'MANDATORY_OUTSIDE_WINDOW');
        }

        // overlap provera (Accommodation se ignoriše)
        if (!$ignoreOverlap) {
            $overlap = $plan->planItems()
                ->whereHas('activity', fn($q) => $q->where('type','!=','Accommodation'))
                ->where('time_from','<',$timeTo)
                ->where('time_to','>',$timeFrom)
                ->exists();
            if ($overlap) {
                throw new BusinessRuleException(
                    'Mandatory item overlaps with existing schedule.', 'MANDATORY_OVERLAP');
            }
        }

        $amount = $this->computeAmount($plan, $activity, $timeFrom, $timeTo);
        if ($plan->total_cost + $amount > $plan->budget) {
            throw new BusinessRuleException(
                'Budget too low to include mandatory item.', 'BUDGET_TOO_LOW_FOR_MANDATORY');
        }

        $item = PlanItem::create([
            'travel_plan_id' => $plan->id,
            'activity_id'    => $activity->id,
            'name'           => $activity->name,
            'time_from'      => $timeFrom,
            'time_to'        => $timeTo,
            'amount'         => $amount,
        ]);

        $plan->increment('total_cost', $amount);
        $plan->refresh();

        return $item;  
    }

    private function syncTotalCost(TravelPlan $plan): void //za slucaj neusklađenosti na kraju kreiranja plana
    {
        $sum = (float) $plan->planItems()->sum('amount');
        if ($sum !== (float) $plan->total_cost) {
            $plan->update(['total_cost' => $sum]);
        }
    }

    private function computeAmount(TravelPlan $plan, Activity $activity, Carbon $from, Carbon $to): float
    {
        $pax = max(1, (int)$plan->passenger_count);
        if (strtolower($activity->type) === 'accommodation') {
            $startDate = Carbon::parse($plan->start_date)->startOfDay();
            $endDate   = Carbon::parse($plan->end_date)->startOfDay();
            $nights = max(1, $startDate->diffInDays($endDate));
            return (float)$activity->price * $nights * $pax;
        }
        return (float)$activity->price * $pax;
    }

}

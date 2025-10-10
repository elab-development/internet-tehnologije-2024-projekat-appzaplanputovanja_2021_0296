<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\PlanItem;
use App\Models\Setting;
use App\Models\TravelPlan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TravelPlanUpdateService
{
    /**
     * 1) Kad se promene datumi: izbaci stavke van novog prozora, ažuriraj obavezne,
     *    i popuni nove dane novim aktivnostima (koliko budžet dozvoljava).
     */
    public function adjustDates(TravelPlan $plan, Carbon $oldStart, Carbon $oldEnd): void
    {
        [$cfg, $start, $end, $outbound, $return] = $this->bootstrapContext($plan);

        $floor = $this->mandatoryCostFloor($plan);
        if ($floor > (float)$plan->budget) {
            $missing = $floor - (float)$plan->budget;
            abort(422, sprintf(
                'Budget too low for mandatory items. Minimum required: %.2f (missing %.2f).',
                $floor, $missing
            ));}
        // izbaci stavke van [start,end] (Accommodation se NE izuzima – mora da stane u prozor)
        foreach ($plan->planItems as $pi) {
            $from = Carbon::parse($pi->time_from);
            $to   = Carbon::parse($pi->time_to);
            if ($from->lt($start) || $to->gt($end)) {
                $plan->decrement('total_cost', (float)$pi->amount);
                $pi->delete();
            }
        }
        $plan->refresh();
        if ($plan->total_cost < 0) $plan->update(['total_cost' => 0]);

        // upsert obaveznih
        $this->upsertMandatoryItems($plan, $cfg);

        // popuni NOVO-OTVORENE dane (pre starog starta i posle starog kraja)
        $this->fillNewlyOpenedDays($plan, $oldStart, $oldEnd);

        $this->assertMandatoryItems($plan);
    }

    /**
     * 2) Kad se promeni broj putnika: reobračun amount-a svuda + total_cost.
     *    Ako se smanji pax i ostane headroom u budžetu → pokušaj dodavanja aktivnosti.
     */
    public function adjustPassengerCount(TravelPlan $plan, int $oldCount, int $newCount): void
    {
        $newTotal = 0.0;
        foreach ($plan->planItems as $pi) {
            $price = (float) optional($pi->activity)->price;
            $newAmount = $price * $newCount;
            if ((float)$pi->amount !== (float)$newAmount) {
                $pi->update(['amount' => $newAmount]);
            }
            $newTotal += $newAmount;
        }
        $plan->update(['total_cost' => $newTotal]);
        $plan->refresh();

        if ($newCount < $oldCount) {
            // ima prostora – dodaj do približno budžetu
            $this->expandTowardBudget($plan);
        }
    }

    /**
     * 3) Kad se promeni budžet: ako je manji i prelazimo ga → uklanjaj,
     *    ako je veći → dodaj dok se ne približi (ne prelazi) budžet.
     */
    public function adjustBudget(TravelPlan $plan, float $oldBudget, float $newBudget): void
    {
        if ((float)$plan->total_cost > $newBudget) {
            $this->shrinkToBudget($plan);
        } else {
            $this->expandTowardBudget($plan);
        }
    }

    /**
     * Učitaj podešavanja i “transport granice” dana, vrati tuple:
     * [$cfg, $start, $end, $outbound, $return]
     */
    private function bootstrapContext(TravelPlan $plan): array
    {
        $hasSetting = class_exists(Setting::class);
        $cfg = [
            'defaultDayStart'        => $hasSetting ? Setting::getValue('default_day_start', '09:00') : '09:00',
            'defaultDayEnd'          => $hasSetting ? Setting::getValue('default_day_end',   '20:00') : '20:00',
            'bufferAfterOutboundMin' => (int)($hasSetting ? Setting::getValue('buffer_after_outbound_min', 30) : 30),
            'bufferBeforeReturnMin'  => (int)($hasSetting ? Setting::getValue('buffer_before_return_min', 0)  : 0),
            'gapBetweenItemsMin'     => (int)($hasSetting ? Setting::getValue('gap_between_activities_min', 10) : 10),
            'outboundStart'          => $hasSetting ? Setting::getValue('outbound_start', '08:00') : '08:00',
            'checkinTime'            => $hasSetting ? Setting::getValue('checkin_time',  '14:00') : '14:00',
            'checkoutTime'           => $hasSetting ? Setting::getValue('checkout_time', '09:00') : '09:00',
            'returnStart'            => $hasSetting ? Setting::getValue('return_start',  '15:00') : '15:00',
        ];

        $start = Carbon::parse($plan->start_date)->startOfDay();

        $end = Carbon::parse($plan->end_date)->endOfDay();

        $items = $plan->planItems()->with('activity')->get();
        $outbound = $items->filter(fn($pi) =>
            $pi->activity && $pi->activity->type === 'Transport' &&
            Carbon::parse($pi->time_from)->isSameDay($start)
        )->sortBy('time_from')->first();

        $return = $items->filter(fn($pi) =>
            $pi->activity && $pi->activity->type === 'Transport' &&
            Carbon::parse($pi->time_from)->isSameDay($end)
        )->sortByDesc('time_from')->first();

        return [$cfg, $start, $end, $outbound, $return];
    }

    private function mandatoryCostFloor(TravelPlan $plan): float
        {
            $transport = Activity::where('type','Transport')
                ->where('location', $plan->destination)
                ->where('transport_mode', $plan->transport_mode)
                ->orderBy('price')->first();

            $accommodation = Activity::where('type','Accommodation')
                ->where('location', $plan->destination)
                ->where('accommodation_class', $plan->accommodation_class)
                ->orderBy('price')->first();

            if (!$transport || !$accommodation) {
                abort(422, 'No transport/accommodation variants for the selected destination/class.');
            }

            return (($transport->price * 2) + $accommodation->price) * $plan->passenger_count;
        }

    private function upsertMandatoryItems(TravelPlan $plan, array $cfg): void
    {
        $transport = Activity::where('type','Transport')
            ->where('location', $plan->destination)
            ->where('transport_mode', $plan->transport_mode)
            ->orderBy('price')->first();

        $accommodation = Activity::where('type','Accommodation')
            ->where('location', $plan->destination)
            ->where('accommodation_class', $plan->accommodation_class)
            ->orderBy('price')->first();

        // outbound
        $depFrom = Carbon::parse(Carbon::parse($plan->start_date)->toDateString().' '.$cfg['outboundStart']);
        $depTo   = (clone $depFrom)->addMinutes($transport->duration);
        $this->upsertSingleMandatory(
            $plan,
            'Transport',
            fn($pi) => Carbon::parse($pi->time_from)->isSameDay($plan->start_date),
            "Transport {$plan->start_location} → {$plan->destination} ({$plan->transport_mode})",
            $transport, $depFrom, $depTo
        );

        // accommodation
        $accFrom = Carbon::parse(Carbon::parse($plan->start_date)->toDateString().' '.$cfg['checkinTime']);
        $accTo   = Carbon::parse(Carbon::parse($plan->end_date)->toDateString().' '.$cfg['checkoutTime']);
        $this->upsertSingleMandatory(
            $plan,
            'Accommodation',
            fn($pi) => optional($pi->activity)->type === 'Accommodation',
            "Accommodation in {$plan->destination} ({$plan->accommodation_class})",
            $accommodation, $accFrom, $accTo, ignoreOverlap: true
        );

        // return
        $retFrom = Carbon::parse(Carbon::parse($plan->end_date)->toDateString().' '.$cfg['returnStart']);
        $retTo   = (clone $retFrom)->addMinutes($transport->duration);
        $this->upsertSingleMandatory(
            $plan,
            'Transport',
            fn($pi) => Carbon::parse($pi->time_from)->isSameDay($plan->end_date),
            "Transport {$plan->destination} → {$plan->start_location} ({$plan->transport_mode})",
            $transport, $retFrom, $retTo
        );
    }

   /**
 * Upsert mandatory (Transport / Accommodation) activity for a plan.
 * Ensures mandatory items always exist; may remove optional ones to fit the budget.
 */
    private function upsertSingleMandatory(
        TravelPlan $plan,
        string $type,
        \Closure $matchFn,
        string $displayName,
        Activity $activity,
        Carbon $from,
        Carbon $to,
        bool $ignoreOverlap = false
    ): void {
        $existing = $plan->planItems->first(function (PlanItem $pi) use ($type, $matchFn) {
            return optional($pi->activity)->type === $type && $matchFn($pi);
        });

        // --- Guard: vreme povratka unutar datuma plana
        if ($to->gt(Carbon::parse($plan->end_date)->endOfDay())) {
            abort(422, 'Mandatory item exceeds the plan end date window.');
        }

        // --- Guard: ne sme se preklapati sa drugim (osim ako je Accommodation)
        if (!$ignoreOverlap) {
            $overlap = $plan->planItems()
                ->whereHas('activity', fn($q) => $q->where('type', '!=', 'Accommodation'))
                ->where('time_from', '<', $to)
                ->where('time_to', '>', $from)
                ->when($existing, fn($q) => $q->where('id', '!=', $existing->id))
                ->exists();

            if ($overlap) {
                abort(422, 'Mandatory item overlaps with existing schedule.');
            }
        }

        $amount = $activity->price * $plan->passenger_count;

        // --- Ako već postoji, samo ažuriraj
        if ($existing) {
            if ((float)$existing->amount !== (float)$amount) {
                $delta = $amount - (float)$existing->amount;
                $plan->increment('total_cost', $delta);
            }

            $existing->update([
                'activity_id' => $activity->id,
                'name'        => $displayName,
                'time_from'   => $from,
                'time_to'     => $to,
                'amount'      => $amount,
            ]);

            $plan->refresh();
            return;
        }

        // --- Ako ne postoji: proveri budžet
        if ($plan->total_cost + $amount > $plan->budget) {
            // Pokušaj da oslobodiš prostor uklanjanjem neobaveznih aktivnosti
            $this->shrinkToBudget($plan);
            $plan->refresh();
        }

        // --- Ako ni sada nema mesta — abortuj
        if ($plan->total_cost + $amount > $plan->budget) {
            abort(422, 'Cannot fit mandatory items within budget even after removing optional activities.');
        }

        // --- Kreiraj novu mandatory stavku
        PlanItem::create([
            'travel_plan_id' => $plan->id,
            'activity_id'    => $activity->id,
            'name'           => $displayName,
            'time_from'      => $from,
            'time_to'        => $to,
            'amount'         => $amount,
        ]);

        $plan->increment('total_cost', $amount);
        $plan->refresh();
    }

    private function assertMandatoryItems(TravelPlan $plan): void
    {
        $transportCount = $plan->planItems()
            ->whereHas('activity', fn($q) => $q->where('type', 'Transport'))
            ->count();

        $accommodationCount = $plan->planItems()
            ->whereHas('activity', fn($q) => $q->where('type', 'Accommodation'))
            ->count();

        if ($transportCount !== 2 || $accommodationCount < 1) {
            abort(422, 'Mandatory items missing. Increase budget or adjust dates.');
        }
    }

    private function fillNewlyOpenedDays(TravelPlan $plan, Carbon $oldStart, Carbon $oldEnd): void
    {
        [$cfg, $start, $end] = $this->bootstrapContext($plan);

        // PRE: [start .. oldStart-1]
        $cursor = $start->copy();
        while ($cursor->lt($oldStart)) {
            $this->fillOneDay($plan, $cursor);
            $cursor->addDay();
        }
        // POSLE: [oldEnd+1 .. end]
        $cursor = $oldEnd->copy()->addDay();
        while ($cursor->lte($end)) {
            $this->fillOneDay($plan, $cursor);
            $cursor->addDay();
        }
    }

    private function fillOneDay(TravelPlan $plan, Carbon $day): void
    {
        [$cfg, $startPlan, $endPlan, $outbound, $return] = $this->bootstrapContext($plan);

        $start = Carbon::parse($day->toDateString().' '.$cfg['defaultDayStart']);
        $end   = Carbon::parse($day->toDateString().' '.$cfg['defaultDayEnd']);

        if ($day->isSameDay($startPlan) && $outbound) {
            $start = Carbon::parse($outbound->time_to)->addMinutes($cfg['bufferAfterOutboundMin']);
        }
        if ($day->isSameDay($endPlan) && $return) {
            $end = Carbon::parse($return->time_from)->subMinutes($cfg['bufferBeforeReturnMin']);
        }
        if ($start->gte($end)) return;

        $cands = $this->candidateActivities($plan);
        foreach ($cands as $a) {
            $slot = $this->findSlot($plan->id, $start, $end, (int)$a->duration, $cfg['gapBetweenItemsMin']);
            if (!$slot) continue;
            [$from,$to] = $slot;

            $amount = $a->price * $plan->passenger_count;
            if ($plan->total_cost + $amount > $plan->budget) continue;
            if ($return && $to->gt(Carbon::parse($return->time_from))) continue;

            PlanItem::create([
                'travel_plan_id' => $plan->id,
                'activity_id'    => $a->id,
                'name'           => $a->name,
                'time_from'      => $from,
                'time_to'        => $to,
                'amount'         => $amount,
            ]);
            $plan->increment('total_cost', $amount);
            $plan->refresh();

            if ($this->closeToBudget($plan)) break;
        }
    }

    private function shrinkToBudget(TravelPlan $plan): void
    {
        $items = $this->nonMandatoryItems($plan)
            ->map(fn($pi) => ['item'=>$pi,'score'=>$this->utilityScore($plan, $pi->activity),'price'=>(float)$pi->amount])
            ->sortBy([['score','asc'], ['price','desc']])->values();

        foreach ($items as $row) {
            if ($plan->total_cost <= $plan->budget) break;
            /** @var PlanItem $pi */
            $pi = $row['item'];
            $plan->decrement('total_cost', (float)$pi->amount);
            $pi->delete();
            $plan->refresh();
        }
        if ($plan->total_cost < 0) $plan->update(['total_cost'=>0]);
    }

    private function expandTowardBudget(TravelPlan $plan): void
    {
        if ($this->closeToBudget($plan)) return;

        $gaps  = $this->freeTimeWindows($plan);
        $cands = $this->candidateActivities($plan)
            ->map(fn($a) => ['activity'=>$a, 'score'=>$this->utilityScore($plan, $a)])
            ->sortBy([['score','desc'], ['activity.price','asc']])->values();

        foreach ($cands as $row) {
            $a = $row['activity'];
            $amount = $a->price * $plan->passenger_count;
            if ($plan->total_cost + $amount > $plan->budget) continue;

            $slot = $this->findFirstFittingSlotFromGaps($gaps, (int)$a->duration);
            if (!$slot) continue;
            [$from,$to] = $slot;

            PlanItem::create([
                'travel_plan_id' => $plan->id,
                'activity_id'    => $a->id,
                'name'           => $a->name,
                'time_from'      => $from,
                'time_to'        => $to,
                'amount'         => $amount,
            ]);
            $plan->increment('total_cost', $amount);
            $plan->refresh();

            $gaps = $this->consumeGap($gaps, [$from,$to]);

            if ($this->closeToBudget($plan)) break;
        }
    }


    private function candidateActivities(TravelPlan $plan)
    {
        $prefs = collect($plan->preferences ?? []);
        return Activity::where('location', $plan->destination)
            ->whereNotIn('type', ['Transport','Accommodation'])
            ->get()
            ->filter(function(Activity $a) use ($prefs){
                $ap = collect($a->preference_types ?? []);
                return $prefs->isEmpty() ? true : $prefs->intersect($ap)->isNotEmpty();
            })->values();
    }

    private function nonMandatoryItems(TravelPlan $plan)
    {
        return $plan->planItems->filter(function(PlanItem $pi){
            $t = optional($pi->activity)->type;
            return !in_array($t, ['Transport','Accommodation'], true);
        })->values();
    }

    private function utilityScore(TravelPlan $plan, Activity $a): float
    {
        $prefsPlan = collect($plan->preferences ?? []);
        $prefsAct  = collect($a->preference_types ?? []);
        $match = $prefsPlan->intersect($prefsAct)->count();
        $score = $match;
        $score += max(0, 1.0 - ($a->price / 100.0));
        return $score;
    }

    private function closeToBudget(TravelPlan $plan): bool
    {
        $budget = (float)$plan->budget;
        if ($budget <= 0) return true;
        $diff = $budget - (float)$plan->total_cost;
        return $diff <= max(10, 0.05 * $budget);
    }

    private function freeTimeWindows(TravelPlan $plan): Collection
    {
        $start = Carbon::parse($plan->start_date);
        $end   = Carbon::parse($plan->end_date);

        $blocks = $plan->planItems
            ->filter(fn($pi) => optional($pi->activity)->type !== 'Accommodation')
            ->map(fn($pi) => [Carbon::parse($pi->time_from), Carbon::parse($pi->time_to)])
            ->sortBy(fn($b) => $b[0]->timestamp)->values();

        $gaps = collect();
        $cursor = $start->copy();

        foreach ($blocks as [$bFrom,$bTo]) {
            if ($bFrom->gt($cursor)) $gaps->push([$cursor->copy(), $bFrom->copy()]);
            if ($bTo->gt($cursor))   $cursor = $bTo->copy();
        }
        if ($cursor->lt($end)) $gaps->push([$cursor->copy(), $end->copy()]);
        return $gaps->filter(fn($g) => $g[0]->lt($end))->values();
    }

    private function findFirstFittingSlotFromGaps(Collection $gaps, int $duration)
    {
        foreach ($gaps as [$from,$to]) {
            if ($from->copy()->addMinutes($duration)->lte($to)) {
                return [$from->copy(), $from->copy()->addMinutes($duration)];
            }
        }
        return null;
    }

    private function consumeGap(Collection $gaps, array $used): Collection
    {
        [$uFrom,$uTo] = $used;
        $new = collect();
        foreach ($gaps as [$gFrom,$gTo]) {
            if ($uTo->lte($gFrom) || $uFrom->gte($gTo)) { $new->push([$gFrom,$gTo]); continue; }
            if ($uFrom->gt($gFrom)) $new->push([$gFrom,$uFrom->copy()]);
            if ($uTo->lt($gTo))     $new->push([$uTo->copy(),$gTo]);
        }
        return $new->sortBy(fn($g) => $g[0]->timestamp)->values();
    }

    private function findSlot(int $planId, Carbon $winStart, Carbon $winEnd, int $durMin, int $gapMin): ?array
    {
        $busy = PlanItem::where('travel_plan_id', $planId)
            ->whereHas('activity', fn($q) => $q->where('type','!=','Accommodation'))
            ->where('time_from','<',$winEnd)
            ->where('time_to','>',$winStart)
            ->orderBy('time_from')
            ->get(['time_from','time_to'])
            ->map(function($pi) use ($winStart,$winEnd,$gapMin){
                $from = Carbon::parse($pi->time_from)->max($winStart)->subMinutes($gapMin);
                $to   = Carbon::parse($pi->time_to)->min($winEnd)->addMinutes($gapMin);
                return [$from,$to];
            })
            ->values();

        if ($busy->isEmpty()) {
            $slotEnd = $winStart->copy()->addMinutes($durMin);
            return $slotEnd->lte($winEnd) ? [$winStart->copy(), $slotEnd] : null;
        }

        // merge busy
        $merged = [];
        foreach ($busy as [$f,$t]) {
            if (!$merged) { $merged[] = [$f->copy(),$t->copy()]; continue; }
            [$lf,$lt] = $merged[count($merged)-1];
            if ($f->lte($lt)) { $merged[count($merged)-1][1] = $t->max($lt); }
            else { $merged[] = [$f->copy(),$t->copy()]; }
        }

        // pre prvog
        [$f0,$t0] = $merged[0];
        $slotEnd = $winStart->copy()->addMinutes($durMin);
        if ($slotEnd->lte($f0)) return [$winStart->copy(), $slotEnd];

        // između
        for ($i=0; $i < count($merged)-1; $i++) {
            [$aF,$aT] = $merged[$i];
            [$bF,$bT] = $merged[$i+1];
            $gapStart = $aT->copy();
            $gapEnd   = $bF->copy();
            $slotEnd  = $gapStart->copy()->addMinutes($durMin);
            if ($slotEnd->lte($gapEnd) && $gapStart->gte($winStart)) {
                return [$gapStart, $slotEnd];
            }
        }

        // posle poslednjeg
        [$lf,$lt] = $merged[count($merged)-1];
        $gapStart = $lt->copy();
        $slotEnd  = $gapStart->copy()->addMinutes($durMin);
        if ($slotEnd->lte($winEnd)) return [$gapStart, $slotEnd];

        return null;
    }
}

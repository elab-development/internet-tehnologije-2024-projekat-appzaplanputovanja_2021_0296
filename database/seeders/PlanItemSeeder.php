<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Models\TravelPlan;
use App\Models\PlanItem;
use App\Models\Activity;
use App\Models\Setting;

class PlanItemSeeder extends Seeder
{
    public function run(): void
    {
        $plans = TravelPlan::with('planItems.activity')->get();
        if ($plans->isEmpty()) return;

        $defaultDayStart = Setting::getValue('default_day_start', '09:00');
        $defaultDayEnd   = Setting::getValue('default_day_end',   '20:00');
        $bufAfterOut     = (int) Setting::getValue('buffer_after_outbound_min', 30);
        $bufBeforeRet    = (int) Setting::getValue('buffer_before_return_min',  0);
        $gapBetween      = 20;

        foreach ($plans as $plan) {
            $start = Carbon::parse($plan->start_date);
            $end   = Carbon::parse($plan->end_date);

            $items = $plan->planItems; // vec sadrži transport i accommodation
            $outbound = $items->first(fn($pi) =>
                $pi->activity && $pi->activity->type === 'Transport' &&
                Carbon::parse($pi->time_from)->isSameDay($start)
            );
            $return = $items->first(fn($pi) =>
                $pi->activity && $pi->activity->type === 'Transport' &&
                Carbon::parse($pi->time_from)->isSameDay($end)
            );

            // kandidati: ista destinacija, bez Transport/Accommodation i (ako plan ima prefs) bar jedna zajednička preferencija
            $prefs = $plan->preferences ?? [];
            $candidates = Activity::where('location', $plan->destination)
                ->whereNotIn('type', ['Transport','Accommodation'])
                ->inRandomOrder()
                ->get()
                ->filter(function ($a) use ($prefs) {
                    if (empty($prefs)) return true;
                    $ap = $a->preference_types ?? [];
                    return !empty(array_intersect($prefs, $ap));
                })
                ->values();

            $day = $start->copy();
            while ($day->lte($end)) {
                if ($day->isSameDay($start)) {
                    $dayStart = $outbound ? Carbon::parse($outbound->time_to)->addMinutes($bufAfterOut)
                                          : Carbon::parse($day->format('Y-m-d').' '.$defaultDayStart);
                    $dayEnd   = Carbon::parse($day->format('Y-m-d').' '.$defaultDayEnd);
                } elseif ($day->isSameDay($end)) {
                    $dayStart = Carbon::parse($day->format('Y-m-d').' '.$defaultDayStart);
                    $dayEnd   = $return ? Carbon::parse($return->time_from)->subMinutes($bufBeforeRet)
                                        : Carbon::parse($day->format('Y-m-d').' '.$defaultDayEnd);
                } else {
                    $dayStart = Carbon::parse($day->format('Y-m-d').' '.$defaultDayStart);
                    $dayEnd   = Carbon::parse($day->format('Y-m-d').' '.$defaultDayEnd);
                }

                if ($dayStart->gte($dayEnd)) {
                    $day->addDay();
                    continue;
                }

                foreach ($candidates as $activity) {
                    // pronadji prvu rupu tog dana (izbegni preklapanje sa već postojećim, ignoriši Accommodation)
                    [$slotFrom, $slotTo] = $this->findSlot($plan->id, $dayStart, $dayEnd, (int) $activity->duration, $gapBetween) ?? [null,null];
                    if (!$slotFrom) continue;

                    // amount i budžet
                    $amount = $activity->price * $plan->passenger_count;
                    if ($plan->total_cost + $amount > $plan->budget) {
                        continue;
                    }

                    // ne sme da prestigne polazak povratnog transporta
                    if ($return && $slotTo->gt(Carbon::parse($return->time_from))) {
                        continue;
                    }

                    PlanItem::create([
                        'travel_plan_id' => $plan->id,
                        'activity_id'    => $activity->id,
                        'name'           => $activity->name,
                        'time_from'      => $slotFrom,
                        'time_to'        => $slotTo,
                        'amount'         => $amount,
                    ]);
                    $plan->increment('total_cost', $amount);
                }

                $day->addDay();
            }
        }
    }

    private function findSlot(int $planId, Carbon $winStart, Carbon $winEnd, int $durMin, int $gapMin): ?array
    {
        $busy = PlanItem::where('travel_plan_id', $planId)
            ->whereHas('activity', fn($q) => $q->where('type','!=','Accommodation'))
            ->where(function($q) use ($winStart, $winEnd) {
                $q->where('time_from','<',$winEnd)->where('time_to','>',$winStart);
            })
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

        // merge
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

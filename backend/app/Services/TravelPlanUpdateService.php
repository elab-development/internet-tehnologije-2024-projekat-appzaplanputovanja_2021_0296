<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\PlanItem;
use App\Models\Setting;
use App\Models\TravelPlan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ConflictException;

class TravelPlanUpdateService
{
    /**
     * 1) Kad se promene datumi: izbaci stavke van novog prozora, ažuriraj obavezne,
     *    i popuni nove dane novim aktivnostima (koliko budžet dozvoljava).
     */
    public function adjustDates(TravelPlan $plan, Carbon $oldStart, Carbon $oldEnd): void
    {
        DB::transaction(function () use ($plan, $oldStart, $oldEnd) {

            // Učitaj sve stavke sa aktivnostima
            $plan->loadMissing(['planItems.activity']);

            //ucitaj podešavanja
            [$cfg] = $this->bootstrapContext($plan);

            //novi prozor
            $newStartDate = Carbon::parse($plan->start_date);
            $newEndDate   = Carbon::parse($plan->end_date);

            $newOutboundFrom = Carbon::parse($plan->start_date.' '.$cfg['outboundStart']);
            $newReturnFrom   = Carbon::parse($plan->end_date.' '.$cfg['returnStart']);
            $newAccommFrom   = Carbon::parse($plan->start_date.' '.$cfg['checkinTime']);
            $newAccommTo     = Carbon::parse($plan->end_date.' '.$cfg['checkoutTime']);

            // Minimalni trošak obaveznih (floor) – pre bilo čega proveri budžet
            $floor = $this->mandatoryCostFloor($plan);
            if ($floor > (float)$plan->budget) {
                $missing = $floor - (float)$plan->budget;
                 throw new BusinessRuleException(
                    'Budget too low for mandatory items.',
                    'BUDGET_TOO_LOW_FOR_MANDATORY'
                );
            }

            // ---- PRONAĐI POSTOJEĆE OBAVEZNE STAVKE (ISTE STAVKE, NEMA KREIRANJA NOVIH) ----
            
            // Transporti u ISTOM smeru i ISTOM modu (oba mandatory su iz ove grupe)
            $transportsSameDir = $plan->planItems
                ->filter(function (PlanItem $pi) use ($plan) {
                    return optional($pi->activity)?->type === 'Transport'
                        && $pi->activity->transport_mode === $plan->transport_mode
                        && $pi->activity->start_location === $plan->start_location
                        && $pi->activity->location === $plan->destination;
                })
                ->values();

                // OUTBOUND = SKUPlji (tiebreaker: raniji time_from)
            $outbound = $transportsSameDir
                ->sortBy([
                    fn($pi) => -1 * (float)($pi->activity->price ?? 0), // DESC cena
                    fn($pi) => Carbon::parse($pi->time_from)->getTimestamp(), // ASC vreme
                ])
                ->first();

                // RETURN = JEFTINIJI (tiebreaker: kasniji time_from)
            $return = $transportsSameDir
                ->sortBy([
                    fn($pi) => (float)($pi->activity->price ?? 0), // ASC cena
                    fn($pi) => -1 * Carbon::parse($pi->time_from)->getTimestamp(), // DESC vreme
                ])
                ->first();

                // Ako postoji samo jedan transport (isti je i skuplji i jeftiniji) – pokušaj uzeti drugi po nizoj ceni kao return
            if ($outbound && $return && $outbound->id === $return->id) {
                $asc = $transportsSameDir->sortBy(fn($pi) => (float)($pi->activity->price ?? 0))->values();
                if ($asc->count() >= 2) {
                    $return = $asc->get(1);
                }
            }
            

            /// Accommodation = type=Accommodation i ista accommodation_class kao plan
            $accomm = $plan->planItems
                ->first(function (PlanItem $pi) use ($plan) {
                    return optional($pi->activity)?->type === 'Accommodation'
                        && $pi->activity->accommodation_class === $plan->accommodation_class;
                });

            // ---- AŽURIRAJ VREMENA + IZNOS ZA OBAVEZNE STAVKE ----
            if ($outbound) {
                $dur = (int) ($outbound->activity->duration ?? 0);
                $newTo = (clone $newOutboundFrom)->addMinutes($dur);

                $newAmount = $this->computeAmount($plan, $outbound->activity, $newOutboundFrom, $newTo);
                                
                $outbound->update([
                    'time_from' => $newOutboundFrom,
                    'time_to'   => $newTo,
                    'amount'    => $newAmount,
                ]);
            }

            if ($return) {
                $dur = (int) ($return->activity->duration ?? 0);
                $newTo = (clone $newReturnFrom)->addMinutes($dur);

                $newAmount = $this->computeAmount($plan, $return->activity, $newReturnFrom, $newTo);
                $return->update([
                    'time_from' => $newReturnFrom,
                    'time_to'   => $newTo,
                    'amount'    => $newAmount,
                ]);
            }

            if ($accomm) {
                $newAmount = $this->computeAmount($plan, $accomm->activity, $newAccommFrom, $newAccommTo);
                $accomm->update([
                    'time_from' => $newAccommFrom,
                    'time_to'   => $newAccommTo,
                    'amount'    => $newAmount,
                ]);
            }

            // ---- IZBACI STAVKE KOJE SE PREKLAPAJU U START/END DAY SA OBAVEZNIM ----
            if ($outbound) {
                $cutStart = Carbon::parse($outbound->time_to);
                $plan->planItems()
                    ->whereHas('activity', fn($q) => $q->where('type','!=','Accommodation')->where('type','!=','Transport'))
                    ->whereDate('time_from', Carbon::parse($plan->start_date)->toDateString())
                    ->where('time_to','<=',$cutStart)   // završava pre/tačno na kraju outbound-a → ukloni
                    ->delete();

                // takođe, sve što bi se preklopilo sa outbound-om (sigurnost)
                $plan->planItems()
                    ->whereHas('activity', fn($q) => $q->where('type','!=','Accommodation'))
                    ->whereDate('time_from', Carbon::parse($plan->start_date)->toDateString())
                    ->where('time_from','<',$cutStart)
                    ->where('time_to','>',$cutStart)
                    ->delete();
            }

            if ($return) {
                $cutEnd = Carbon::parse($return->time_from);
                $plan->planItems()
                    ->whereHas('activity', fn($q) => $q->where('type','!=','Accommodation')->where('type','!=','Transport'))
                    ->whereDate('time_from', Carbon::parse($plan->end_date)->toDateString())
                    ->where(function($q) use ($cutEnd){
                        $q->where('time_from','>=',$cutEnd)    // počinje nakon povratka
                        ->orWhere('time_to','>',$cutEnd);    // ili ga seče
                    })
                    ->delete();
            }
            $plan->refresh();

            // ---- IZBACI STAVKE VAN NOVOG PROZORA ----
            // (ne dira obavezne gore, nego sve ostale aktivnosti izvan [newStart, newEnd]-ali odnosi se na dane a ne na sate)
            $removedSum = 0.0;
            $toRemove = $plan->planItems
                ->filter(function (PlanItem $pi) use ($newStartDate, $newEndDate,$outbound, $return, $accomm) {
                    // zadrži obavezne koje smo gore ažurirali
                    if ($outbound && $pi->id === $outbound->id) return false;
                    if ($return   && $pi->id === $return->id)   return false;
                    if ($accomm   && $pi->id === $accomm->id)   return false;

                    $from = Carbon::parse($pi->time_from);
                    $to   = Carbon::parse($pi->time_to);
                    // potpuno izvan novog intervala
                    return $to->lt($newStartDate->copy()->startOfDay()) ||
                        $from->gt($newEndDate->copy()->endOfDay());
                });

            foreach ($toRemove as $pi) {
                $removedSum += (float) $pi->amount;
                $pi->delete();
            }

            if ($removedSum > 0) {
                $plan->decrement('total_cost', $removedSum);
            }

            // ---- DOPUNI PLAN SAMO AKO IMA BUDŽETA ----
            // Ako je prozor proširen (start ranije ili end kasnije), popuni novonastale dane
            //poredi nove datume sa STARIM prosleđenim argumentima ***
            $expanded = $newStartDate->lt($oldStart->copy()->startOfDay())
                    || $newEndDate->gt($oldEnd->copy()->endOfDay());

            // prvo poravnaj total da dobiješ tačan headroom
            $this->recalcTotal($plan);
            $plan->refresh();

            if ($expanded && $plan->total_cost < $plan->budget) {
                //prvo popuni baš nove dane (ciljano, per-day)
                $this->fillNewlyOpenedDays($plan, $oldStart->copy()->startOfDay(), $oldEnd->copy()->endOfDay());
                $this->recalcTotal($plan);
                $plan->refresh();
            }

            if ($plan->total_cost < $plan->budget) {
                $this->expandTowardBudget($plan); // dodaje “ostale” aktivnosti do ispunjenja budžeta
                $this->recalcTotal($plan);
                $plan->refresh();
            }

            // Ako je sužen prozor, ili posle ukupnih izmena pređemo budžet → skrati
            if ($plan->total_cost > $plan->budget) {
                $this->shrinkToBudget($plan);
                $plan->refresh();
            }

            // ---- FINAL: poravnaj total i validiraj raspored ----
            $this->recalcTotal($plan);
            $plan->refresh();

            $this->assertMandatoryItems($plan);
            $this->assertNoOverlapsAndInWindow($plan);
        });
            
    }


    /**
     * 2) Kad se promeni broj putnika: reobračun amount-a svuda + total_cost.
     *    Ako se smanji pax i ostane headroom u budžetu → pokušaj dodavanja aktivnosti.
     */
    public function adjustPassengerCount(TravelPlan $plan, int $oldCount, int $newCount): void
    {
        DB::transaction(function () use ($plan, $oldCount, $newCount) {
            $newTotal = 0.0;

            $items = $plan->planItems()->with('activity')->lockForUpdate()->get();
            foreach ($items as $pi) {
                $from = Carbon::parse($pi->time_from);
                $to   = Carbon::parse($pi->time_to);
                $newAmount = $this->computeAmount($plan, $pi->activity, $from, $to);
                if ((float)$pi->amount !== (float)$newAmount) {
                    $pi->update(['amount' => $newAmount]);
                }
                $newTotal += $newAmount;
            }

            // Poravnaj plan.total_cost posle reobračuna stavki
            $plan->update(['total_cost' => $newTotal]);
            $plan->refresh();

             // Ako je smanjen broj putnika → pokušaj da dodaš aktivnosti
            if ($newCount < $oldCount) {
                $this->expandTowardBudget($plan);
            }

             // Ako je povećan broj putnika i prešli smo budžet → uklanjaj
            if ($plan->total_cost > $plan->budget) {
                $this->shrinkToBudget($plan);
                $plan->refresh();
                if ($plan->total_cost > $plan->budget) {
                    throw new BusinessRuleException(
                       'Increasing passenger count exceeds the budget even after rebalancing.',
                       'BUDGET_TOO_LOW_AFTER_PAX_CHANGE'
                     );
                }
            }

            // sigurnost: re-račun total-a nakon većih promena
            $this->recalcTotal($plan);
            $this->assertMandatoryItems($plan);
            $this->assertNoOverlapsAndInWindow($plan);
        });
    }


    /**
     * 3) Kad se promeni budžet: ako je manji i prelazimo ga → uklanjaj,
     *    ako je veći → dodaj dok se ne približi (ne prelazi) budžet.
     */
    public function adjustBudget(TravelPlan $plan, float $oldBudget, float $newBudget): void
    {
        DB::transaction(function () use ($plan, $newBudget) {
            
            $plan->loadMissing(['planItems.activity']);
            $this->recalcTotal($plan);
            $plan->refresh();

            $currentTotal = (float) $plan->total_cost;

            // Ne dozvoli smanjenje ispod trenutnog total-a
            if ($newBudget < $currentTotal) {
                throw new BusinessRuleException(
                    'Budget decrease would make the current plan exceed the budget. Reduce optional activities or set a higher budget.',
                    'BUDGET_EXCEEDED'
                );
            }

            //upisi novi budžet
             $plan->update(['budget' => $newBudget]);

            // ako je veći budžet od total-a → pokušaj dodavanja aktivnosti
            if ($newBudget > $currentTotal) {
                $this->expandTowardBudget($plan);
            }

            // 3) finalni recalc i validacije
            $this->recalcTotal($plan);
            $plan->refresh();

            $this->assertMandatoryItems($plan);
            $this->assertNoOverlapsAndInWindow($plan);
        });
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
        // outbound: skuplji
        $outboundT = Activity::where('type','Transport')
        ->where('start_location', $plan->start_location)
        ->where('location', $plan->destination)
        ->where('transport_mode', $plan->transport_mode)
        ->orderBy('price','desc') // skuplji
        ->first();

        // return: jeftiniji
        $returnT = Activity::where('type','Transport')
        ->where('start_location', $plan->start_location)
        ->where('location', $plan->destination)
        ->where('transport_mode', $plan->transport_mode)
        ->orderBy('price','asc') // jeftiniji
        ->first();

        // accommodation
        $accommodation = Activity::where('type', 'Accommodation')
            ->where('location', $plan->destination)
            ->where('accommodation_class', $plan->accommodation_class)
            ->whereNotNull('price')
            ->orderBy('price', 'asc')
            ->first();

        if (!$outboundT || !$returnT || !$accommodation) {
            throw new BusinessRuleException(
                'No matching transport/accommodation variants for selected start/destination/mode/class.',
                'MANDATORY_VARIANTS_MISSING'
            );
        }
        
        $startDate = Carbon::parse($plan->start_date)->startOfDay();
        $endDate   = Carbon::parse($plan->end_date)->startOfDay();
        $nights    = max(1, $startDate->diffInDays($endDate));

        $floor = ($outboundT->price + $returnT->price) * $plan->passenger_count
            + ($accommodation->price * $nights * $plan->passenger_count);

        return (float)$floor;

        //return floatval(($outboundT->price + $returnT->price + $accommodation->price) * $plan->passenger_count);
    }

    private function upsertMandatoryItems(TravelPlan $plan, array $cfg): void
    {
       // outbound: skuplji
        $outboundT = Activity::where('type','Transport')
        ->where('start_location', $plan->start_location)
        ->where('location', $plan->destination)
        ->where('transport_mode', $plan->transport_mode)
        ->orderBy('price','desc') // skuplji
        ->first();

        // return: jeftiniji
        $returnT = Activity::where('type','Transport')
        ->where('start_location', $plan->start_location)
        ->where('location', $plan->destination)
        ->where('transport_mode', $plan->transport_mode)
        ->orderBy('price','asc') // jeftiniji
        ->first();

        $accommodation = Activity::where('type','Accommodation')
            ->where('location', $plan->destination)
            ->where('accommodation_class', $plan->accommodation_class)
            ->whereNotNull('price')
            ->orderBy('price')->first();

        // outbound
        $depFrom = Carbon::parse(Carbon::parse($plan->start_date)->toDateString().' '.$cfg['outboundStart']);
        $depTo = (clone $depFrom)->addMinutes((int)$outboundT->duration);
        $this->upsertSingleMandatory(
            $plan, 'Transport',
            fn($pi) => Carbon::parse($pi->time_from)->isSameDay($plan->start_date),
            "Transport {$plan->start_location} → {$plan->destination} ({$plan->transport_mode})",
            $outboundT, $depFrom, $depTo
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
        $retTo = (clone $retFrom)->addMinutes((int)$returnT->duration);
        $this->upsertSingleMandatory(
            $plan, 'Transport',
            fn($pi) => Carbon::parse($pi->time_from)->isSameDay($plan->end_date),
            "Transport {$plan->destination} → {$plan->start_location} ({$plan->transport_mode})",
            $returnT, $retFrom, $retTo
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
        // sanity
        if (!$activity) {
            throw new BusinessRuleException('Mandatory activity variant not found.', 'MANDATORY_VARIANT_NOT_FOUND');
        }

        // Guard: outbound ne sme da počne pre starta plana
        $planStart = Carbon::parse($plan->start_date)->startOfDay();
        if ($from->lt($planStart)) {
            throw new BusinessRuleException('Mandatory item starts before plan start date.', 'MANDATORY_OUTSIDE_WINDOW');
        }

        // Guard: ne sme da pređe kraj plana 
        $planEnd = Carbon::parse($plan->end_date)->endOfDay();
        if ($to->gt($planEnd)) {
            throw new BusinessRuleException('Mandatory item exceeds the plan end date window.', 'MANDATORY_OUTSIDE_WINDOW');
        }

        // Pronađi postojeću stavku istog tipa i istog "match" kriterijuma
        
        $existing = $plan->planItems->first(function (PlanItem $pi) use ($type, $matchFn) {
            return optional($pi->activity)->type === $type && $matchFn($pi);
        });

        // Overlap guard (smeštaj ignorišemo kad $ignoreOverlap = true)
        if (!$ignoreOverlap) {
            $overlap = $plan->planItems()
                ->whereHas('activity', fn($q) => $q->where('type', '!=', 'Accommodation'))
                ->where('time_from', '<', $to)
                ->where('time_to', '>', $from)
                ->when($existing, fn($q) => $q->where('id', '!=', $existing->id))
                ->exists();

            if ($overlap) {
                throw new BusinessRuleException('Mandatory item overlaps with existing schedule.', 'MANDATORY_OVERLAP');
            }
        }

        // IZNOS: centralizovano preko helpera (po noći × pax za smeštaj; × pax za ostalo)
        $amount = $this->computeAmount($plan, $activity, $from, $to);

        if ($existing) {
            // Ako već postoji – ažuriraj i poravnaj total kroz delta
            $oldAmount = (float)$existing->amount;
            $delta = $amount - $oldAmount;

            // Ako delta gura preko budžeta → pokušaj da oslobodiš prostor
            if ($delta > 0 && ($plan->total_cost + $delta) > $plan->budget) {
                $this->shrinkToBudget($plan);
                $plan->refresh();
                if (($plan->total_cost + $delta) > $plan->budget) {
                    throw new BusinessRuleException('Cannot fit mandatory item within budget after rebalancing.', 'BUDGET_TOO_LOW_FOR_MANDATORY');
                }
            }

            $existing->update([
                'activity_id' => $activity->id,
                'name'        => $displayName,
                'time_from'   => $from,
                'time_to'     => $to,
                'amount'      => $amount,
            ]);

            if ($delta != 0.0) {
                $plan->increment('total_cost', $delta);
            }
            $plan->refresh();
            return;
        }

        // Nema postojeće – ubaci novu, ali prvo obezbedi budžet
        if (($plan->total_cost + $amount) > $plan->budget) {
            $this->shrinkToBudget($plan);
            $plan->refresh();
        }
        if (($plan->total_cost + $amount) > $plan->budget) {
           throw new BusinessRuleException('Cannot fit mandatory items within budget even after removing optional activities.', 'BUDGET_TOO_LOW_FOR_MANDATORY');

        }

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
            throw new BusinessRuleException('Mandatory items missing. Increase budget or adjust dates.', 'MANDATORY_MISSING');
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

        // Kandidati + split paid/free
        $cands = $this->candidateActivities($plan)->values();
        $paid  = $cands->filter(fn($a) => (int)$a->price > 0)->sortBy('price')->values();
        $free  = $cands->filter(fn($a) => (int)$a->price === 0)->values();

        // Limiti FREE
        $maxFreePerPlan = 2;
        $freeUsedPlan   = $plan->planItems()
            ->whereHas('activity', fn($q) => $q->whereNotIn('type', ['Transport','Accommodation']))
            ->get()
            ->filter(fn($pi) => (int)optional($pi->activity)->price === 0)
            ->count();

        $maxFreePerDay = 1;
        $freeUsedDay   = 0;

        // 1) PLAĆENE
        foreach ($paid as $a) {
            $amount = (int)$a->price * (int)$plan->passenger_count;
            if ((float)$plan->total_cost + $amount > (float)$plan->budget) continue;

            $slot = $this->findSlot($plan->id, $start, $end, (int)$a->duration, (int)$cfg['gapBetweenItemsMin']);
            if (!$slot) continue;
            [$from,$to] = $slot;

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

            // posle prve uspešne – prekini za taj dan; ili ukloni `break` ako želiš više
            break;
        }

        // 2) FREE (ako ima mesta i nismo prešli limite)
        if ($freeUsedPlan < $maxFreePerPlan && $freeUsedDay < $maxFreePerDay) {
            foreach ($free as $a) {
                $slot = $this->findSlot($plan->id, $start, $end, (int)$a->duration, (int)$cfg['gapBetweenItemsMin']);
                if (!$slot) continue;
                [$from,$to] = $slot;

                if ($return && $to->gt(Carbon::parse($return->time_from))) continue;

                PlanItem::create([
                    'travel_plan_id' => $plan->id,
                    'activity_id'    => $a->id,
                    'name'           => $a->name,
                    'time_from'      => $from,
                    'time_to'        => $to,
                    'amount'         => 0,
                ]);
                $plan->refresh();
                // posle jedne free – dosta za taj dan (možeš ukloniti break ako želiš 2+)
                break;
            }
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

        [$cfg, $startPlan, $endPlan, $outbound, $return] = $this->bootstrapContext($plan); 

        $cands = $this->candidateActivities($plan)->values();
        $paid  = $cands->filter(fn($a) => (int)$a->price > 0)->sortBy('price')->values();
        $free  = $cands->filter(fn($a) => (int)$a->price === 0)->values();

        $maxFreePerPlan = 4;
        $freeUsedPlan   = $plan->planItems()
            ->whereHas('activity', fn($q) => $q->whereNotIn('type', ['Transport','Accommodation']))
            ->get()
            ->filter(fn($pi) => (int)optional($pi->activity)->price === 0)
            ->count();

        // rupe po danima
        $dayBuckets = $this->freeTimeWindowsPerDay($plan)   // Collection
        ->map(fn ($b) => [
            'date' => $b['date'],
            'gaps' => collect($b['gaps'])->all(), // gaps -> array
        ])
        ->all();

        if (empty($dayBuckets)) return;

        // limit po danu – da se ne “natrpa” prvi dan
        $maxPaidPerDay = 5;
        $paidPlacedPerDay = [];

        // helper koji nađe prvi slot u zadatom danu
        $fitInDay = function(array &$gaps, int $durMin) {
            foreach ($gaps as $idx => [$from,$to]) {
                if ($from->copy()->addMinutes($durMin)->lte($to)) {
                    $slotFrom = $from->copy();
                    $slotTo   = $from->copy()->addMinutes($durMin);
                    // “pojede” deo rupe
                    $new = collect($gaps);
                    $new = $this->consumeGap($new, [$slotFrom,$slotTo]);
                    $gaps = $new->values()->all(); 
                    return [$slotFrom,$slotTo];
                }
            }
            return null;
        };

        // 1) PLAĆENE – round-robin kroz dane dok se ne približimo budžetu
        $dayIndex = 0;
        foreach ($paid as $a) {
            $amount = (int)$a->price * (int)$plan->passenger_count;
            if ((float)$plan->total_cost + $amount > (float)$plan->budget) continue;

            $attempts = 0; // da ne uđemo u beskonačnu petlju
            $placed = false;

            while ($attempts < max(1, count($dayBuckets))) {
                $bucket = &$dayBuckets[$dayIndex];
                $dateKey = $bucket['date'];
                $paidPlacedPerDay[$dateKey] = $paidPlacedPerDay[$dateKey] ?? 0;

                if ($paidPlacedPerDay[$dateKey] < $maxPaidPerDay && !empty($bucket['gaps'])) {
                    $slot = $fitInDay($bucket['gaps'], (int)$a->duration);
                    if ($slot) {
                        [$from,$to] = $slot;

                        if ($return && $from->isSameDay($endPlan) && $to->gt(Carbon::parse($return->time_from))) {
                        // ako je nakon returnT samo preskoči ovu aktivnost i idi dalje
                            $attempts++;
                            $dayIndex = ($dayIndex + 1) % count($dayBuckets);
                            continue;
                        }
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

                        $paidPlacedPerDay[$dateKey]++;
                        $placed = true;
                        break;
                    }
                }

                // sledeći dan (round-robin)
                $dayIndex = ($dayIndex + 1) % count($dayBuckets);
                $attempts++;
            }

            if ($this->closeToBudget($plan)) return;
            if (!$placed) {
                // nijedan dan nema slot za ovu aktivnost – probaj sledeću
                continue;
            }

            // sledeći pokušaj kreće od sledećeg dana da se raspored “rasipa”
            $dayIndex = ($dayIndex + 1) % max(1,count($dayBuckets));
        }

        // 2) FREE – i dalje po danima, uz globalni limit
        foreach ($free as $a) {
            if ($freeUsedPlan >= $maxFreePerPlan) break;

            $attempts = 0; $placed = false;
            while ($attempts < max(1, count($dayBuckets))) {
                $bucket = &$dayBuckets[$dayIndex];
                if (!empty($bucket['gaps'])) {
                    $slot = $fitInDay($bucket['gaps'], (int)$a->duration);
                    if ($slot) {
                        [$from,$to] = $slot;
                        // proveri returnT
                        if ($return && $from->isSameDay($endPlan) && $to->gt(Carbon::parse($return->time_from))) {
                            $attempts++;
                            $dayIndex = ($dayIndex + 1) % count($dayBuckets);
                            continue;
                        }

                        PlanItem::create([
                            'travel_plan_id' => $plan->id,
                            'activity_id'    => $a->id,
                            'name'           => $a->name,
                            'time_from'      => $from,
                            'time_to'        => $to,
                            'amount'         => 0,
                        ]);
                        $plan->refresh();
                        $freeUsedPlan++;
                        $placed = true;
                        break;
                    }
                }
                $dayIndex = ($dayIndex + 1) % count($dayBuckets);
                $attempts++;
            }
            if (!$placed) break; // nema više rupa nigde
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

    private function freeTimeWindowsPerDay(TravelPlan $plan): Collection
    {
        [$cfg,  $startPlan, $endPlan, $outbound, $return] = $this->bootstrapContext($plan);

        $startDate = Carbon::parse($plan->start_date)->startOfDay();
        $endDate   = Carbon::parse($plan->end_date)->startOfDay();

        $days = collect();
        $cursor = $startDate->copy();

        while ($cursor->lte($endDate)) {
            // radni prozor tog dana
            $dayStart = Carbon::parse($cursor->toDateString().' '.$cfg['defaultDayStart']);
            $dayEnd   = Carbon::parse($cursor->toDateString().' '.$cfg['defaultDayEnd']);

            // prilagodi prvi i poslednji dan
            if ($cursor->isSameDay($startPlan) && $outbound) {
                $dayStart = Carbon::parse($outbound->time_to)->addMinutes($cfg['bufferAfterOutboundMin']);
            }
            if ($cursor->isSameDay($endPlan) && $return) {
                $dayEnd = Carbon::parse($return->time_from)->subMinutes($cfg['bufferBeforeReturnMin']);
            }

            if ($dayStart->gte($dayEnd)) { // nema prozora za taj dan
                $cursor->addDay();
                continue;
            }

            // uzmi busy blokove tog dana (bez Accommodation) i izgradi rupe unutar [dayStart, dayEnd]
            $busy = $plan->planItems()
                ->whereHas('activity', fn($q) => $q->where('type','!=','Accommodation'))
                ->where('time_from','<',$dayEnd)
                ->where('time_to','>',$dayStart)
                ->orderBy('time_from')
                ->get()
                ->map(function($pi) use ($dayStart,$dayEnd,$cfg){
                    $from = Carbon::parse($pi->time_from)->max($dayStart)->subMinutes($cfg['gapBetweenItemsMin']);
                    $to   = Carbon::parse($pi->time_to)->min($dayEnd)->addMinutes($cfg['gapBetweenItemsMin']);
                    return [$from,$to];
                })
                ->values();

            $gaps = collect();
            if ($busy->isEmpty()) {
                $gaps->push([$dayStart->copy(), $dayEnd->copy()]);
            } else {
                // merge i pronađi rupe
                $merged = [];
                foreach ($busy as [$f,$t]) {
                    if (!$merged) { $merged[] = [$f->copy(),$t->copy()]; continue; }
                    [$lf,$lt] = $merged[count($merged)-1];
                    if ($f->lte($lt)) { $merged[count($merged)-1][1] = $t->max($lt); }
                    else { $merged[] = [$f->copy(),$t->copy()]; }
                }
                // pre prvog
                [$f0,$t0] = $merged[0];
                if ($dayStart->lt($f0)) $gaps->push([$dayStart->copy(), $f0->copy()]);
                // između
                for ($i=0; $i < count($merged)-1; $i++) {
                    [$aF,$aT] = $merged[$i];
                    [$bF,$bT] = $merged[$i+1];
                    if ($aT->lt($bF)) $gaps->push([$aT->copy(), $bF->copy()]);
                }
                // posle poslednjeg
                [$lf,$lt] = $merged[count($merged)-1];
                if ($lt->lt($dayEnd)) $gaps->push([$lt->copy(), $dayEnd->copy()]);
            }

            $days->push([
                'date' => $cursor->toDateString(),
                'gaps' => $gaps->values(),
            ]);

            $cursor->addDay();
        }

        return $days;
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

    private function computeAmount(TravelPlan $plan, Activity $a, Carbon $from = null, Carbon $to = null): float
    {
        $pax = max(1, (int)$plan->passenger_count);

        if (strtolower($a->type) === 'accommodation') {
            // ako nisu prosleđeni, koristi plan period za smeštaj
            $startDate = Carbon::parse($plan->start_date)->startOfDay();
            $endDate   = Carbon::parse($plan->end_date)->startOfDay();
            $nights    = max(1, $startDate->diffInDays($endDate));
            return (float)$a->price * $nights * $pax;
        }
        return (float)$a->price * $pax;
    }

    public function recalcTotal(TravelPlan $plan): void
    {
        $sum = (float)$plan->planItems()->sum('amount');
        if ($sum < 0) { $sum = 0.0; }
        $plan->update(['total_cost' => $sum]);
    }

    private function assertNoOverlapsAndInWindow(TravelPlan $plan): void
    {
        $items = $plan->planItems()->with('activity')->orderBy('time_from')->get()
            ->filter(fn($pi) => optional($pi->activity)->type !== 'Accommodation')
            ->values();

        for ($i=0; $i < $items->count(); $i++) {
            for ($j=$i+1; $j < $items->count(); $j++) {
                $a = $items[$i]; $b = $items[$j];
                $aFrom = Carbon::parse($a->time_from); $aTo = Carbon::parse($a->time_to);
                $bFrom = Carbon::parse($b->time_from); $bTo = Carbon::parse($b->time_to);
                if ($aFrom->lt($bTo) && $bFrom->lt($aTo)) {
                    throw new ConflictException('Overlap of appointments between activities in the plan.', 'TIME_OVERLAP');
                }
            }
        }

        $winStart = Carbon::parse($plan->start_date)->startOfDay();
        $winEnd   = Carbon::parse($plan->end_date)->endOfDay();

        foreach ($plan->planItems as $pi) {
            $from = Carbon::parse($pi->time_from); $to = Carbon::parse($pi->time_to);
            if ($from->lt($winStart) || $to->gt($winEnd)) {
                throw new BusinessRuleException('The activity is outside the travel period.', 'OUTSIDE_TRAVEL_PERIOD');
            }
        }
    }


}

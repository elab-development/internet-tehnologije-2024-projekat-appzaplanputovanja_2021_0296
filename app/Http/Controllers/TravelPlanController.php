<?php

namespace App\Http\Controllers;

use App\Models\TravelPlan;
use App\Models\Activity;
use App\Models\PlanItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TravelPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(TravelPlan::with('planItems')->paginate(10)); // Return paginated list of travel plans with their items
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'         => 'required|integer|exists:users,id', //menjati kada se koristi auth()->user()->id
            'start_location'  => 'required|string',
            'destination'     => ['required', 'string',
                                 Rule::in(Activity::query()->distinct()->pluck('location')->toArray()), ], //PHP niz od jedinstvenih vrednosti iz kolone location iz activities tabele
            'end_date'        => 'required|date|after_or_equal:start_date',
            'budget'          => 'required|numeric|min:0',
            'passenger_count' => 'required|integer|min:1',
            'preferences'     => 'required|array',
            'preferences.*'   => [
                                Rule::in(Activity::availablePreferenceTypes()),],
        ]);

        return DB::transaction(function () use ($data) {
            $plan = TravelPlan::create($data);

            // 1) obavezne aktivnosti (Activity zapisi) + 2) PlanItem-i za njih
            $this->generateMandatoryItems($plan);

            // 3) ostale aktivnosti po preferencijama, bez preklapanja i u okviru budžeta
            $this->fillWithMatchingActivities($plan);

            return response()->json($plan->load('planItems'), 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(TravelPlan $travelPlan)
    {
        return response()->json($travelPlan->load('planItems')); // Return a single travel plan with its items
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TravelPlan $travelPlan)
    {
        $currentTotal = $travelPlan->planItems()->sum('amount');

        $data = $request->validate([
            'start_location'  => 'sometimes|required|string',
            'destination'     => [ 'sometimes', 'required', 'string',
                                 Rule::in(Activity::query()->distinct()->pluck('location')->toArray()),],
            'start_date'      => ['sometimes', 'required', 'date', 'after:today'],
            'end_date'        => 'sometimes|required|date|after_or_equal:start_date',
            'budget'          => ['sometimes','numeric','min:0', 
                                function($attribute, $value, $fail) use ($currentTotal) {
                                    if ($value < $currentTotal) {
                                        $fail("The budget cannot be less than the current total cost ($currentTotal).");
                                    }}],
            'passenger_count' => 'sometimes|required|integer|min:1',
            'preferences'     => 'sometimes|required|array',
            'preferences.*'   => [
                                 Rule::in(Activity::availablePreferenceTypes()),],
        ]);

        $travelPlan->update($data);

        return response()->json([
            'message' => 'Travel plan uspešno ažuriran.',
            'data'    => $travelPlan->fresh()
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TravelPlan $travelPlan)
    {
        $travelPlan->delete();

        //return response()->noContent();
        return response()->json([
            'data'    => null,
            'message' => 'Travel plan deleted successfully.'
        ], 200);
    }

    private function generateMandatoryItems(TravelPlan $plan): void
    {
        $start = Carbon::parse($plan->start_date);
        $end   = Carbon::parse($plan->end_date);

        $nights = $start->diffInDays($end);

        //Ensure/kreiraj potrebne Activity zapise (poštuj enum "Transport"/"Accommodation")
        $toName   = "Transport-From-{$plan->start_location}-To-{$plan->destination}";
        $backName = "Transport-From-{$plan->destination}-To-{$plan->start_location}";
        $accName  = "Accommodation in {$plan->destination}";

        $transportTo = Activity::firstOrCreate(
            ['name' => $toName, 'location' => $plan->destination],
            ['type' => 'Transport', 'price' => 30, 'duration' => 90, 'content' => "From {$plan->start_location} to {$plan->destination}", 'preference_types' => []]
        );

        $transportBack = Activity::firstOrCreate(
            ['name' => $backName, 'location' => $plan->destination],
            ['type' => 'Transport', 'price' => 30, 'duration' => 90, 'content' => "Back to {$plan->start_location}", 'preference_types' => []]
        );

        // Trajanje smeštaja postavi približno u minutama 
        $accDurationMin = $start->copy()->setTime(14,0)->diffInMinutes($end->copy()->setTime(9,0), false);
        if ($accDurationMin < 0) { $accDurationMin = 0; }

        $accommodation = Activity::firstOrCreate(
            ['name' => $accName, 'location' => $plan->destination],
            ['type' => 'Accommodation', 'price' => 40 * max(1, $nights), 'duration' => $accDurationMin, 'content' => "Stay in {$plan->destination}", 'preference_types' => []]
        );

        //Kreiraj PlanItem-e za obavezne aktivnosti (Accommodation se sme preklapati sa svima)
        //Transport polazak: start_date 08:00 — 90 min
        $this->createItemIfBudgetAllows($plan, $transportTo, 
            $start->copy()->setTime(8, 0), 
            $start->copy()->setTime(8, 0)->addMinutes($transportTo->duration)
        );

        // Accommodation: od start_date 14:00 do end_date 09:00
        $this->createItemIfBudgetAllows($plan, $accommodation, 
            $start->copy()->setTime(14, 0), 
            $end->copy()->setTime(9, 0),
            true
        );

        // Transport povratak: end_date 15:00 — 90 min (do 16:30)
        $backFrom = $end->copy()->setTime(15, 0);
        $this->createItemIfBudgetAllows($plan, $transportBack, 
            $backFrom, 
            $backFrom->copy()->addMinutes($transportBack->duration)
        );
}
private function fillWithMatchingActivities(TravelPlan $plan): void
{
    $start = Carbon::parse($plan->start_date);
    $end   = Carbon::parse($plan->end_date);

    // Aktivnosti na destinaciji (osim Transport/Accommodation) koje se poklapaju sa preferencijama
    $prefs = $plan->preferences ?? [];

    $candidates = Activity::where('location', $plan->destination)
        ->whereNotIn('type', ['Transport','Accommodation'])
        ->get()
        ->filter(function ($a) use ($prefs) {
            if (empty($prefs)) return true;
            $ap = $a->preference_types ?? [];
            return !empty(array_intersect($prefs, $ap));
        });

    // jednostavno raspoređivanje: od prvog dana posle transporta, do poslednjeg dana do 15:00
    $day = $start->copy();
    $currentFrom = $start->copy()->setTime(9,30); // posle jutarnjeg transporta (08:00–09:30)

    while ($day->lte($end)) {
        // dnevni prozor:
        $dayStart = $day->isSameDay($start) ? $currentFrom->copy() : $day->copy()->setTime(9, 0);
        $dayEnd   = $day->isSameDay($end) ? $end->copy()->setTime(15, 0) : $day->copy()->setTime(20, 0);

        foreach ($candidates as $activity) {
            // predloži slot
            $slotFrom = $dayStart->copy();
            $slotTo   = $slotFrom->copy()->addMinutes($activity->duration);

            if ($slotTo->gt($dayEnd)) {
                continue; // ne staje u ovaj dan
            }

            // proveri preklapanje (ignoriše Accommodation)
            $overlap = PlanItem::where('travel_plan_id', $plan->id)
                ->whereHas('activity', function ($q) {
                    $q->where('type', '!=', 'Accommodation');
                })
                ->where(function ($q) use ($slotFrom, $slotTo) {
                    $q->whereBetween('time_from', [$slotFrom, $slotTo])
                    ->orWhereBetween('time_to', [$slotFrom, $slotTo])
                    ->orWhere(function ($qq) use ($slotFrom, $slotTo) {
                        $qq->where('time_from', '<=', $slotFrom)
                           ->where('time_to', '>=', $slotTo);
                    });
                })
                ->exists();

            if ($overlap) {
                // pomeri početak na kraj najranije kolizije – za jednostavnost, samo preskoči ovaj kandidat za danas
                continue;
            }

            // budžet
            $amount = $activity->price * $plan->passenger_count;
            if (($plan->total_cost + $amount) > $plan->budget) {
                return; // nema više budžeta – prekid
            }

            // kreiraj stavku
            $this->createItemIfBudgetAllows($plan, $activity, $slotFrom, $slotTo);

            // pomeri daily start na kraj upravo ubačene aktivnosti + 30 min pauze
            $dayStart = $slotTo->copy()->addMinutes(30);
        }

        $day->addDay();
    }
}
private function createItemIfBudgetAllows(TravelPlan $plan, Activity $activity, Carbon $from, Carbon $to, bool $ignoreOverlap = false): ?PlanItem
{
    // okvir putovanja
    $planEndEod = Carbon::parse($plan->end_date)->endOfDay();
    if ($to->greaterThan($planEndEod)) {
        return null;
    }

    if (!$ignoreOverlap && $activity->type !== 'Accommodation') {
        $overlap = PlanItem::where('travel_plan_id', $plan->id)
            ->whereHas('activity', function ($q) {
                $q->where('type', '!=', 'Accommodation');
            })
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('time_from', [$from, $to])
                ->orWhereBetween('time_to', [$from, $to])
                ->orWhere(function ($qq) use ($from, $to) {
                    $qq->where('time_from', '<=', $from)
                       ->where('time_to', '>=', $to);
                });
            })
            ->exists();

        if ($overlap) {
            return null;
        }
    }

    $amount = $activity->price * $plan->passenger_count;
    if (($plan->total_cost + $amount) > $plan->budget) {
        return null;
    }

    $item = $plan->planItems()->create([
        'activity_id' => $activity->id,
        'name'        => $activity->name,
        'time_from'   => $from,
        'time_to'     => $to,
        'amount'      => $amount,
    ]);

    $plan->increment('total_cost', $amount);

    return $item;
}



}

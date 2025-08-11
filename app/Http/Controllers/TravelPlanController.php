<?php

namespace App\Http\Controllers;

use App\Models\TravelPlan;
use App\Models\Activity;
use App\Models\PlanItem;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use App\Http\Resources\TravelPlanResource;
use App\Http\Resources\PlanItemResource;

class TravelPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $q = TravelPlan::with(['planItems' => function($q) {
            $q->orderBy('time_from');
        }, 'planItems.activity','user']);

        // Filter by user_id
        if ($userId = $request->integer('user_id')) {
            $q->where('user_id', $userId);
        }

        // Filter by destination
        if ($dest = $request->query('destination')) {
            $q->where('destination', $dest);
        }

        //Filter by time interval overlap
        $dateFrom = $request->date('date_from');
        $dateTo   = $request->date('date_to');

        if ($dateFrom || $dateTo) {
            $q->where(function($qq) use ($dateFrom, $dateTo) {
                if ($dateFrom) $qq->where('end_date', '>=', $dateFrom); //plan ends after (or on) date_from
                if ($dateTo)   $qq->where('start_date', '<=', $dateTo); //plan starts before (or on) date_to
            });
        }

        $perPage = min(max($request->integer('per_page', 10), 1), 100);
        
        return TravelPlanResource::collection($q->paginate($perPage)->appends($request->query()));
        //return response()->json($q->paginate($perPage)->appends($request->query()));
        //return response()->json(TravelPlan::with('planItems')->paginate(10)); // Return paginated list of travel plans with their items
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
            'start_date'      => ['required', 'date', 'after:today'],
            'end_date'        => 'required|date|after_or_equal:start_date',
            'budget'          => 'required|numeric|min:0',
            'passenger_count' => 'required|integer|min:1',
            'preferences'     => 'required|array',
            'preferences.*'   => [
                                Rule::in(Activity::availablePreferenceTypes()),],
            'transport_mode' => ['required', Rule::in(['airplane','train','car','bus','ferry','cruise ship'])],
            'accommodation_class'=> ['required',
                                    Rule::in(['hostel','guesthouse','budget_hotel','standard_hotel','boutique_hotel','luxury_hotel',
                                    'resort','apartment','bed_and_breakfast','villa','mountain_lodge','camping','glamping'])],
        ]);

        return DB::transaction(function () use ($data) {
            $plan = TravelPlan::create($data);

            // 1) obavezne aktivnosti (Activity zapisi) + 2) PlanItem-i za njih
            $this->generateMandatoryItems($plan);

            // 3) ostale aktivnosti po preferencijama, bez preklapanja i u okviru budžeta
            $this->fillWithMatchingActivities($plan);

            return (new TravelPlanResource($plan->load(['planItems.activity','user'])))->response()->setStatusCode(201);
            //return response()->json($plan->load('planItems'), 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(TravelPlan $travelPlan)
    {
        return new TravelPlanResource($travelPlan->load(['planItems.activity','user']));
        //return response()->json($travelPlan->load('planItems')); // Return a single travel plan with its items
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
            'transport_mode' => ['sometimes','required', Rule::in(['airplane','train','car','bus','ferry','cruise ship'])],
            'accommodation_class'=> ['sometimes','required',
                                    Rule::in(['hostel','guesthouse','budget_hotel','standard_hotel','boutique_hotel','luxury_hotel',
                                    'resort','apartment','bed_and_breakfast','villa','mountain_lodge','camping','glamping'])],
        ]);

        $travelPlan->update($data);

        return new TravelPlanResource($travelPlan->fresh()->load(['planItems.activity','user']));
       // return response()->json(['message' => 'Travel plan uspešno ažuriran.','data'    => $travelPlan->fresh()        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TravelPlan $travelPlan)
    {
        $travelPlan->delete();

        return response()->noContent();
        //return response()->json(['data'    => null,'message' => 'Travel plan deleted successfully.'], 200);
    }
    private function generateMandatoryItems(TravelPlan $plan): void
    {
        // podrazumevana vremena- admin posle moze rucno menjati PlanItem
        $outboundStart  = Setting::getValue('outbound_start', '08:00');
        $checkinTime    = Setting::getValue('checkin_time',   '14:00');
        $checkoutTime   = Setting::getValue('checkout_time',  '09:00');
        $returnStart    = Setting::getValue('return_start',   '15:00');

        DB::transaction(function () use (
            $plan,
            $outboundStart,
            $checkinTime,
            $checkoutTime,
            $returnStart,
        ) {
            // 1) Pronadji TRANSPORT varijantu (po enum transport_mode) za datu destinaciju
            $transport= Activity::query()
                ->where('type', 'Transport')
                ->where('location', $plan->destination)
                ->where('transport_mode', $plan->transport_mode) // enum match
                ->orderBy('price') // prioritet
                ->first();

            // 2) Pronadji ACCOMMODATION varijantu (po enum accommodation_class) za destinaciju
            $accommodation = Activity::query()
                ->where('type', 'Accommodation')
                ->where('location', $plan->destination)
                ->where('accommodation_class', $plan->accommodation_class) // enum match
                ->orderBy('price')
                ->first();

            if (!$transport || !$accommodation) {
                abort(422, 'Nedostaju obavezne aktivnosti za izabrani transport ili smeštaj. Dodajte odgovarajuće Activity varijante.');
            }

            // 3) Transport: odlazak (start_date)
            $departureFrom = Carbon::parse($plan->start_date.' '.$outboundStart);
            $departureTo   = (clone $departureFrom)->addMinutes($transport->duration);
            $transport->name = $transport->name ?: "Transport {$plan->start_location} → {$plan->destination} ({$plan->transport_mode})";
            $this->createItemIfBudgetAllows($plan, $transport, $departureFrom, $departureTo);

            // 4) Accommodation: ceo boravak
            $accFrom = Carbon::parse($plan->start_date.' '.$checkinTime);
            $accTo   = Carbon::parse($plan->end_date.' '.$checkoutTime);
            $accommodation->name = $accommodation->name ?: "Accommodation in {$plan->destination} ({$plan->accommodation_class})";
            $this->createItemIfBudgetAllows($plan, $accommodation, $accFrom, $accTo, /*ignoreOverlap*/ true);

            // 5) Transport: povratak (end_date)
            $backFrom = Carbon::parse($plan->end_date.' '.$returnStart);
            $backTo   = (clone $backFrom)->addMinutes($transport->duration);
            $transport->name = $transport->name ?: "Transport {$plan->destination} → {$plan->start_location} ({$plan->transport_mode})";
            $this->createItemIfBudgetAllows($plan, $transport, $backFrom, $backTo);
        });
    }
    private function fillWithMatchingActivities(TravelPlan $plan): void
    {
        // 0) Pročitaj podešavanja, uz fallback vrednosti
        $hasSetting = class_exists(Setting::class);
        $defaultDayStart = $hasSetting ? Setting::getValue('default_day_start', '09:00') : '09:00';
        $defaultDayEnd   = $hasSetting ? Setting::getValue('default_day_end',   '20:00') : '20:00';
        $bufferAfterOutboundMin = (int) ($hasSetting ? Setting::getValue('buffer_after_outbound_min', 30) : 30);
        $bufferBeforeReturnMin  = (int) ($hasSetting ? Setting::getValue('buffer_before_return_min', 0)  : 0);
        $gapBetweenItemsMin     = 20; // buffer između aktivnosti pri traženju slota

        $start = Carbon::parse($plan->start_date);
        $end   = Carbon::parse($plan->end_date);

        // 1) Nađi TRANSPORT stavke (outbound/return) za određivanje dnevnih granica
        $items = $plan->planItems()->with('activity')->get();

        $outbound = $items->filter(fn($pi) =>
            $pi->activity && $pi->activity->type === 'Transport' &&
            Carbon::parse($pi->time_from)->isSameDay($start)
        )->sortBy('time_from')->first();

        $return = $items->filter(fn($pi) =>
            $pi->activity && $pi->activity->type === 'Transport' &&
            Carbon::parse($pi->time_from)->isSameDay($end)
        )->sortByDesc('time_from')->first();

        // 2) Pripremi kandidate: destinacija + poklapanje bar jedne preferencije, isključi Transport/Accommodation
        $prefs = $plan->preferences ?? [];
        $candidates = Activity::where('location', $plan->destination)
            ->whereNotIn('type', ['Transport', 'Accommodation'])
            ->inRandomOrder() // mešanje već u upitu
            ->get()
            ->filter(function ($a) use ($prefs) {
                if (empty($prefs)) return true;
                $ap = $a->preference_types ?? [];
                return !empty(array_intersect($prefs, $ap));
            })
            ->shuffle() // dodatno mešanje posle filtera
            ->values();

        // 3) Iteriraj dane
        $day = $start->copy();
        while ($day->lte($end)) {

            // 3.1) Odredi dnevni prozor
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

            // 3.2) Pokušaj da spakuješ aktivnosti u (prvu moguću) rupu tog dana
            foreach ($candidates as $activity) {

                // nađi prvu slobodnu rupu u dnevnom prozoru za trajanje aktivnosti
                $slot = $this->findNextAvailableTimeSlot(
                    $plan->id,
                    $dayStart,
                    $dayEnd,
                    (int) $activity->duration,
                    $gapBetweenItemsMin
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

    private function findNextAvailableTimeSlot( int $planId, Carbon $windowStart, Carbon $windowEnd, int $durationMin, int $gapMin = 30 ): ?array 
    {   //trazi sledecu slobodnu "rupu" u planu - validna tek ako je slobodna dužina ≥ duration + 2×gap
        
        // 1) Uzmi sve intervale koji se IKAKO seku sa prozorom
        $busy = PlanItem::where('travel_plan_id', $planId)
            ->whereHas('activity', fn($q) => $q->where('type','!=','Accommodation'))
            ->where(function($q) use ($windowStart, $windowEnd) {
                // (time_from < windowEnd) AND (time_to > windowStart)
                $q->where('time_from', '<', $windowEnd)
                ->where('time_to',   '>', $windowStart);
            })
            ->orderBy('time_from')
            ->get(['time_from','time_to'])
            ->map(function($pi) use ($windowStart, $windowEnd, $gapMin) {
                // isečemo na prozor i proširimo za gap (da očuvamo „distance”)
                $from = Carbon::parse($pi->time_from)->max($windowStart)->copy()->subMinutes($gapMin);
                $to   = Carbon::parse($pi->time_to)->min($windowEnd)->copy()->addMinutes($gapMin);
                return [$from, $to];
            })
            ->values();

        // 2) Ako nema zauzetih, staje li cela aktivnost u window?
        if ($busy->isEmpty()) {
            $slotEnd = $windowStart->copy()->addMinutes($durationMin);
            return $slotEnd->lte($windowEnd) ? [$windowStart->copy(), $slotEnd] : null;
        }

        // 3) „Merge“ preklapajućih intervala
        $merged = [];
        foreach ($busy as [$from, $to]) {
            if (empty($merged)) {
                $merged[] = [$from->copy(), $to->copy()];
                continue;
            }
            [$lf, $lt] = $merged[count($merged)-1];
            if ($from->lte($lt)) {
                // overlap -> proširi prethodni
                $merged[count($merged)-1][1] = $to->max($lt);
            } else {
                $merged[] = [$from->copy(), $to->copy()];
            }
        }

        // 4) Traži rupu PRE prvog zauzeća
        [$f0, $t0] = $merged[0];
        $slotEnd = $windowStart->copy()->addMinutes($durationMin);
        if ($slotEnd->lte($f0)) {
            return [$windowStart->copy(), $slotEnd];
        }

        // 5) Traži rupu IZMEĐU zauzeća
        for ($i = 0; $i < count($merged)-1; $i++) {
            [$aFrom, $aTo] = $merged[$i];
            [$bFrom, $bTo] = $merged[$i+1];
            $gapStart = $aTo->copy();            // kraj prethodnog
            $gapEnd   = $bFrom->copy();          // početak sledećeg
            $slotEnd  = $gapStart->copy()->addMinutes($durationMin);
            if ($slotEnd->lte($gapEnd) && $gapStart->gte($windowStart)) {
                return [$gapStart, $slotEnd];
            }
        }

        // 6) Traži rupu POSLE poslednjeg zauzeća
        [$lf, $lt] = $merged[count($merged)-1];
        $gapStart = $lt->copy();
        $slotEnd  = $gapStart->copy()->addMinutes($durationMin);
        if ($slotEnd->lte($windowEnd)) {
            return [$gapStart, $slotEnd];
        }

        return null; // nema mesta    
        
    }

    private function createItemIfBudgetAllows(TravelPlan $plan, Activity $activity, Carbon $from, Carbon $to, bool $ignoreOverlap = false): ?PlanItem
    {
        // okvir putovanja
        if ($from->lt(Carbon::parse($plan->start_date)->startOfDay()) || //less than
            $to->gt(Carbon::parse($plan->end_date)->endOfDay())) {       //greater than
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


/**public function search(Request $request): JsonResponse //kada zavrsimo autentifikaciju
    {
        // Validacija ulaznih parametara
        $data = $request->validate([
            'user_id'        => ['sometimes','integer','exists:users,id'], //kada zavrsimo autentifikaciju
            'destination'   => ['sometimes','string','max:255'],
            'q'             => ['sometimes','string','max:255'], // tekstualna pretraga
            'date_from'     => ['sometimes','date'],
            'date_to'       => ['sometimes','date','after_or_equal:date_from'],
            'budget_min'    => ['sometimes','numeric','min:0'],
            'budget_max'    => ['sometimes','numeric','gte:budget_min'],
            'total_cost_max'=> ['sometimes','numeric','min:0'],
            'passengers'    => ['sometimes','integer','min:1'],
            'preference'    => ['sometimes','array'],            // npr. preference[]=want_culture
            'preference.*'  => ['string','max:100'],
            'sort_by'       => ['sometimes', Rule::in(['start_date','end_date','budget','total_cost','destination'])],
            'sort_dir'      => ['sometimes', Rule::in(['asc','desc'])],
            'per_page'      => ['sometimes','integer','min:1','max:100'],
        ]);

       //plan sa stavkama, sortiran po vremenu
        $q = TravelPlan::with(['planItems' => function($q) {
            $q->orderBy('time_from');
        }, 'planItems.activity','user']);

        if (!empty($data['user_id'])) {
            $q->where('user_id', $data['user_id']);
        }

        // preklapanje sa [date_from, date_to]
        $from = $data['date_from'] ?? null;
        $to   = $data['date_to']   ?? null;
        if ($from || $to) {
            $q->where(function ($qq) use ($from, $to) {
                if ($from) $qq->where('end_date', '>=', $from);
                if ($to)   $qq->where('start_date', '<=', $to);
            });
        }

        // Višestruke preferencije
        if (!empty($data['preference'])) {
            $prefs = (array) $data['preference'];
            $q->where(function ($qq) use ($prefs) {
                foreach ($prefs as $p) {
                    $qq->orWhereJsonContains('preferences', $p);
                }
            });
        }

        //Tekstualna pretraga po polju start_location/destination
        if (!empty($data['q'])) {
            $term = trim($data['q']);
            $q->where(function ($qq) use ($term) {
                $qq->where('start_location', 'LIKE', "%{$term}%")
                ->orWhere('destination', 'LIKE', "%{$term}%");
            });
        }

        //  Paginacija
        $perPage = $data['per_page'] ?? 10;

        return TravelPlanResource::collection($q->paginate($perPage)->appends($request->query()));
       // return response()->json($q->paginate($perPage)->appends($request->query()));
    }
*/
    public function exportPdf(Request $request, TravelPlan $travelPlan)
    {
        $travelPlan->load(['user','planItems.activity']);

        // sortiraj stavke po vremenu
        $items = $travelPlan->planItems->sortBy('time_from');


        //  agregarane stavke
        $totals = [
            'count'        => $items->count(),
            'duration_min' => $items->sum(function ($i) {
                $from = Carbon::parse($i->time_from);
                $to   = Carbon::parse($i->time_to);
                return $from->diffInMinutes($to);
            }),
            'amount'       => $items->sum('amount'),
        ];

        // bezbedno ime fajla: travel-plan-UserName-Destination.pdf
        $safeUser = preg_replace('/[^A-Za-z0-9_\-]/', '_', optional($travelPlan->user)->name ?? 'user');
        $safeDest = preg_replace('/[^A-Za-z0-9_\-]/', '_', $travelPlan->destination);
        $filename = "travel-plan-{$safeUser}-{$safeDest}.pdf";

        $pdf = Pdf::loadView('pdf.travel-plan-full', [
            'plan'    => $travelPlan,
            'items'   => $items,
            'totals'  => $totals,
        ])->setPaper('a4');

        // ?inline=1 za pregled u browseru/Postman-u
        return $request->boolean('inline')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
        }

}

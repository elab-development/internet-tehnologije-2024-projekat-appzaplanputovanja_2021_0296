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
use App\Services\TravelPlanStoreService;
use App\Services\TravelPlanUpdateService;

class TravelPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', TravelPlan::class);

        $me = $request->user();
        
        $q = TravelPlan::with(['planItems' => function($q) {
            $q->orderBy('time_from');
        }, 'planItems.activity','user']);

        // Filter by user_id, if not admin
       if ($me->is_admin) {
            $q->where('user_id', '!=', $me->id);   // admin vidi sve TUĐE
        } else {
            $q->where('user_id', $me->id);         // user vidi SVOJE
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
    public function store(Request $request, TravelPlanStoreService $storeService)
    {
        $this->authorize('create', TravelPlan::class);


        $data = $request->validate([
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

        $data['user_id'] = $request->user()->id;
        
        $plan = $storeService->createWithGeneratedItems($data);
        return new TravelPlanResource($plan);

    }

    /**
     * Display the specified resource.
     */
    public function show(TravelPlan $travelPlan)
    {
        $this->authorize('view', $travelPlan);   
        return new TravelPlanResource($travelPlan->load(['planItems.activity','user']));
        //return response()->json($travelPlan->load('planItems')); // Return a single travel plan with its items
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TravelPlan $travelPlan, TravelPlanUpdateService $svc)
    {
        $this->authorize('update', $travelPlan);

        return DB::transaction(function () use ($request, $travelPlan, $svc) {

            //Zaključaj plan u transakciji
            $travelPlan->lockForUpdate();
            $travelPlan->load(['planItems.activity']);

            //Trenutne vrednosti i pomoćne kolekcije
            $currentTotal = (float) $travelPlan->total_cost;

            $data = $request->validate([
                'user_id'         => ['prohibited'], 
                'start_location'  => ['prohibited'],
                'destination'     => ['prohibited'],
                'preferences'     => ['prohibited'],
                'transport_mode'  => ['prohibited'],
                'accommodation_class' => ['prohibited'],
                'start_date'      => ['sometimes','required','date','after:now'],
                'end_date'        => ['sometimes','required','date','after_or_equal:start_date'],
                'passenger_count' => ['sometimes','required','integer','min:1'],
                'budget'          => ['sometimes','numeric','min:0', 
                // atribut-budget, nova vrednost atributa, fail callback-ako je greska
                                    function($attr,$value,$fail) use ($currentTotal){
                                        if ($value < $currentTotal) {
                                            $fail("The budget cannot be less than the current total cost ({$currentTotal}).");
                                        }
                                    }],
            ],[
            'user_id.prohibited'                => 'User cannot be changed after creation.',
            'start_location.prohibited'         => 'Start location cannot be changed after creation.',
            'destination.prohibited'            => 'Destination cannot be changed after creation.',
            'preferences.prohibited'            => 'Preferences cannot be changed after creation.',
            'transport_mode.prohibited'         => 'Transport mode cannot be changed after creation.',
            'accommodation_class.prohibited'    => 'Accommodation class cannot be changed after creation.',
        ]);

            // stare vrednosti
            $oldStart  = Carbon::parse($travelPlan->start_date);
            $oldEnd    = Carbon::parse($travelPlan->end_date);
            $oldPax    = (int) $travelPlan->passenger_count;
            $oldBudget = (float) $travelPlan->budget;

            // primeni nove vrednosti
            $travelPlan->fill($data);
            $travelPlan->save();
            $travelPlan->refresh();

            $changedDates  = (array_key_exists('start_date',$data) || array_key_exists('end_date',$data)) &&
                             ($travelPlan->start_date !== $oldStart->toDateTimeString() ||
                              $travelPlan->end_date   !== $oldEnd->toDateTimeString());

            $changedPax    = array_key_exists('passenger_count',$data) &&
                             ((int)$travelPlan->passenger_count !== $oldPax);

            $changedBudget = array_key_exists('budget',$data) &&
                             ((float)$travelPlan->budget !== $oldBudget);

            // 1) Datumi
            if ($changedDates) {
                $svc->adjustDates($travelPlan, $oldStart, $oldEnd);
            }

            // 2) Putnici
            if ($changedPax) {
                $svc->adjustPassengerCount($travelPlan, $oldPax, (int)$travelPlan->passenger_count);
            }

            // 3) Budžet
            if ($changedBudget) {
                $svc->adjustBudget($travelPlan, $oldBudget, (float)$travelPlan->budget);
            }

            return new TravelPlanResource($travelPlan->fresh(['planItems.activity']));
        });
    }
     /**
     * Remove the specified resource from storage.
     */
    public function destroy(TravelPlan $travelPlan)
    {
        $this->authorize('delete', $travelPlan);   
        $travelPlan->delete();

        return response()->noContent();
        //return response()->json(['data'    => null,'message' => 'Travel plan deleted successfully.'], 200);
    }

    public function search(Request $request) //: JsonResponse 
    {
        $this->authorize('search', TravelPlan::class);

        //plan sa stavkama, sortiran po vremenu
        $q = TravelPlan::with(['planItems' => function($q) { $q->orderBy('time_from');}, 
                            'planItems.activity','user'])->where('user_id', $request->user()->id);

        $me = $request->user();

        if ($me->is_admin) {
            $q->where('user_id', '!=', $me->id);
        } else {
            $q->where('user_id', $me->id);
        }

        // Validacija ulaznih parametara
        $data = $request->validate([
            'user_id'        => ['sometimes','integer','exists:users,id'], 
            'destination'   => ['sometimes','string','max:255'],
            'q'             => ['sometimes','string','max:255'], // tekstualna pretraga
            'date_from'     => ['sometimes','date'],
            'date_to'       => ['sometimes','date','after_or_equal:date_from'],
            //'budget_min'    => ['sometimes','numeric','min:0'],
            //'budget_max'    => ['sometimes','numeric','gte:budget_min'],
           // 'total_cost_max'=> ['sometimes','numeric','min:0'],
           // 'passengers'    => ['sometimes','integer','min:1'],
            'preference'    => ['sometimes','array'],            // npr. preference[]=want_culture
            'preference.*'  => ['string','max:100'],
            //'sort_by'       => ['sometimes', Rule::in(['start_date','end_date','budget','total_cost','destination'])],
           // 'sort_dir'      => ['sometimes', Rule::in(['asc','desc'])],
            'per_page'      => ['sometimes','integer','min:1','max:100'],
        ]);

        if (!empty($data['destination'])) {
            $q->where('destination', $data['destination']);
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

        return TravelPlanResource::collection($q->paginate($perPage)->appends($request->query()))->response();
       // return response()->json($q->paginate($perPage)->appends($request->query()));
    }

    public function exportPdf(Request $request, TravelPlan $travelPlan)
    {
        $this->authorize('export', $travelPlan);   

        $travelPlan->load(['user','planItems.activity']);

        // sortiraj stavke po vremenu
        $items = $travelPlan->planItems->sortBy('time_from');


        //  agregirane stavke
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

        // ?inline=1 za pregled u browseru/Postman
        return $request->boolean('inline')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
        }

}

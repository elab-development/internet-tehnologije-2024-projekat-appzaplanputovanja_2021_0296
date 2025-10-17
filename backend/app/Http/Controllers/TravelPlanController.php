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

         $destinations = Activity::distinct()->pluck('location')->filter()->values();
         // Uzimamo sve jedinstvene destinacije (location) iz aktivnosti, bez duplikata i praznih vrednosti.

         $startLocations = Activity::whereNotNull('start_location')->distinct()->pluck('start_location')->values();
         // Uzimamo sve jedinstvene start lokacije (start_location) iz aktivnosti koje ih imaju popunjene (Transport).

        $data = $request->validate([
            'destination'     => ['required', 'string', Rule::in($destinations)],
            'start_location'  => ['required', 'string', Rule::in($startLocations)],
            'transport_mode'  => ['required', 'string', Rule::in(Activity::availableTransportModes())],
            'accommodation_class' => ['required', 'string', Rule::in(Activity::availableAccommodationClasses())],
            'preferences'     => 'required|array',
            'preferences.*'   => [
                                Rule::in(Activity::availablePreferenceTypes()),],
            
            'start_date'      => ['required', 'date', 'after:today'],
            'end_date'        => 'required|date|after_or_equal:start_date',
            'budget'          => 'required|numeric|min:1',
            'passenger_count' => 'required|integer|min:1',
        ]);

        $data['user_id'] = $request->user()->id;
        
        $plan = $storeService->createWithGeneratedItems($data);
        return (new TravelPlanResource($plan))->response()
                                                ->setStatusCode(201)
                                                ->header('Location', route('travel-plans.show', $plan->id));

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

        $data = $request->validate([
            'user_id'               => ['prohibited'],
            'start_location'        => ['prohibited'],
            'destination'           => ['prohibited'],
            'preferences'           => ['prohibited'],
            'transport_mode'        => ['prohibited'],
            'accommodation_class'   => ['prohibited'],

            'start_date'            => ['sometimes','required','date','after:today'],
            'end_date'              => ['sometimes','required','date','after_or_equal:start_date'],
            'passenger_count'       => ['sometimes','required','integer','min:1'],
            'budget'                => ['sometimes','numeric','min:1'],
        ],[
            'user_id.prohibited'                => 'User cannot be changed after creation.',
            'start_location.prohibited'         => 'Start location cannot be changed after creation.',
            'destination.prohibited'            => 'Destination cannot be changed after creation.',
            'preferences.prohibited'            => 'Preferences cannot be changed after creation.',
            'transport_mode.prohibited'         => 'Transport mode cannot be changed after creation.',
            'accommodation_class.prohibited'    => 'Accommodation class cannot be changed after creation.',
        ]);

        return DB::transaction(function () use ($data, $travelPlan, $svc) {

            
            $travelPlan->load(['planItems.activity']);

            // stare vrednosti
            $oldStart  = Carbon::parse($travelPlan->start_date);
            $oldEnd    = Carbon::parse($travelPlan->end_date);
            $oldPax    = (int) $travelPlan->passenger_count;
            $oldBudget = (float) $travelPlan->budget;

            // BUDŽET – OBAVEZNO PRVO i ISKLJUČIVO kroz servis
            if (array_key_exists('budget', $data)) {
                $newBudget = (float) $data['budget'];
                // adjustBudget će baciti BusinessRuleException('BUDGET_EXCEEDED', 422) ako je < total_cost
                $svc->adjustBudget($travelPlan, $oldBudget, $newBudget);
                unset($data['budget']); // da ne prođe kasnije kroz plain update
                $travelPlan->refresh();
            }

            //  Primeni ostala polja (datumi, pax...) – bez budžeta
            if (!empty($data)) {
                $travelPlan->fill($data);
                $travelPlan->save();
                $travelPlan->refresh();
            }

            //Odredi koje su izmene stvarno nastale
            $changedDates  = (array_key_exists('start_date',$data) || array_key_exists('end_date',$data)) &&
                             ($travelPlan->start_date !== $oldStart->toDateTimeString() ||
                              $travelPlan->end_date   !== $oldEnd->toDateTimeString());

            $changedPax    = array_key_exists('passenger_count',$data) &&
                             ((int)$travelPlan->passenger_count !== $oldPax);


            //  Datumi
            if ($changedDates) {
                $svc->adjustDates($travelPlan, $oldStart, $oldEnd);
                $travelPlan->refresh();
            }

            // Putnici
            if ($changedPax) {
                $svc->adjustPassengerCount($travelPlan, $oldPax, (int)$travelPlan->passenger_count);
                $travelPlan->refresh();
            }

            // Konačno: re-račun ukupne cene (sigurnost)
            $svc->recalcTotal($travelPlan);
            $travelPlan->refresh();

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

    
    public function search(Request $request)
    {
        $this->authorize('search', TravelPlan::class);

        $me = $request->user();

        // 1) VALIDACIJA
        $data = $request->validate([
            'q'         => ['sometimes','string','max:255'],
            'date_from' => ['sometimes','date'],
            'date_to'   => ['sometimes','date','after_or_equal:date_from'],
            'per_page'  => ['sometimes','integer','min:1','max:100'],
        ]);

        // 2) BAZNI UPIT – SAMO SVOJI PLANOVI (admin vidi tuđe, korisnik svoje)
        $q = TravelPlan::with(['planItems'=>fn($qq)=>$qq->orderBy('time_from'),'planItems.activity','user']);

        if ($me->is_admin) {
            $q->where('user_id','!=',$me->id);
        } else {
            $q->where('user_id',$me->id);
        }

        // 3) FILTER: destinacija (tekstualna pretraga po destination)
        $destExists = null;
        if (!empty($data['q'])) {
            $term = trim($data['q']);
            $q->where('destination','LIKE',"%{$term}%");

            // signalizuj da li takva lokacija uopšte postoji u Activities
            $destExists = Activity::where('location','LIKE',"%{$term}%")->exists();
        }

        // 4) FILTER: interval (overlap logika)
        $from = $data['date_from'] ?? null;
        $to   = $data['date_to']   ?? null;
        if ($from || $to) {
            $q->where(function ($qq) use ($from,$to) {
                if ($from) $qq->where('end_date','>=',$from);
                if ($to)   $qq->where('start_date','<=',$to);
            });
        }

        // 5) PAGINACIJA + META (destination_exists)
        $perPage = $data['per_page'] ?? 10;
        $paginator = $q->paginate($perPage)->appends($request->query());

        return TravelPlanResource::collection($paginator)
            ->additional(['meta'=>['destination_exists'=>$destExists]])    //ako postoji q, vrati i destination_exists
            ->response();
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

<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Database\QueryException;
use App\Http\Resources\ActivityResource;
use Carbon\Carbon;
use App\Services\TravelPlanUpdateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ActivityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Activity::query();

        // Filter by type (enum)
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        // Filter by location
        if ($loc = $request->query('location')) {
            $query->where('location', $loc);
        }

        // Filter by min price
        if (!is_null($request->query('price_min'))) {
            $query->where('price', '>=', (float)$request->query('price_min'));
        }

        // Filter by max price
        if (!is_null($request->query('price_max'))) {
        $query->where('price', '<=', (float)$request->query('price_max'));
        }

        // Filter by preferences
        if ($prefs = $request->query('preference')) {
            $prefs = (array) $prefs;
            $query->where(function ($qq) use ($prefs) {
                foreach ($prefs as $p) {
                    $qq->orWhereJsonContains('preference_types', $p);
                }
            });
        }

        $sortBy  = $request->query('sort_by', 'location');
        $sortDir = $request->query('sort_dir', 'asc');

        if (in_array($sortBy, ['name','price','duration','location']) &&
            in_array($sortDir, ['asc','desc'])) {
            $query->orderBy($sortBy, $sortDir);
        }

        return ActivityResource::collection($query->paginate(10)->appends($request->query()));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'type'               => ['required', Rule::in(Activity::availableTypes())],
            'location'           => ['required', Rule::in(Activity::availableLocations())],
            'preference_types'   => ['required','array'],
            'preference_types.*' => [Rule::in(Activity::availablePreferenceTypes())],

            'transport_mode'     => [ 'required_if:type,Transport','prohibited_unless:type,Transport',
                                        Rule::in(Activity::availableTransportModes())
                                    ],
            'accommodation_class'=> ['required_if:type,Accommodation','prohibited_unless:type,Accommodation',
                                        Rule::in(Activity::availableAccommodationClasses())
                                    ],       
            'start_location'     => ['required_if:type,Transport','prohibited_unless:type,Transport',
                                        Rule::in(Activity::availableStartLocations())
                                    ],   
            'name'               => 'required|string|max:255',
            'price'              => 'required|numeric|min:0',
            'duration'           => 'required|integer|min:0',
            'content'            => 'nullable|string',
            'image_url'          => 'nullable|url|max:2048',
        ]);

        $activity = Activity::create($data);

       // return response()->json($activity, 201);
        return (new ActivityResource($activity))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Activity $activity)
    {
        //return response()->json($activity);
        return new ActivityResource($activity);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Activity $activity)
    {
        $data = $request->validate([
            'type'                => ['prohibited'],
            'location'            => ['prohibited'],
            'transport_mode'      => ['prohibited'],
            'accommodation_class' => ['prohibited'],
            'name'               => 'sometimes|required|string|max:255', //Ovo polje nije obavezno da se salje, ali ako se posalje, ne sme biti prazno i mora biti ispravnog tipa.
            'price'              => 'sometimes|required|numeric|min:0',
            'duration'           => 'sometimes|required|integer|min:0',
            'content'            => 'sometimes|nullable|string',
            'image_url'          => 'sometimes|nullable|url|max:2048',
            'preference_types'   => 'sometimes|required|array',
            'preference_types.*' => [
                                    Rule::in(Activity::availablePreferenceTypes()),],
            [
            'type.prohibited'                => 'Type cannot be changed after creation.',
            'location.prohibited'            => 'Location cannot be changed after creation.',
            'transport_mode.prohibited'      => 'Transport mode cannot be changed after creation.',
            'accommodation_class.prohibited' => 'Accommodation class cannot be changed after creation.',]
        ]);

        //$activity->update($data);

        //return response()->json($activity);
       // return new ActivityResource($activity);

        return \DB::transaction(function () use ($request, $activity, $data) {

            // snapshot pre izmene
            $old = $activity->replicate(['id']); // zbog starih vrednosti (name, price, duration ...)
            $activity->update($data);
            $activity->refresh();

            // Ako menjaš preference_types: za neobavezne tipove mora postojati presek sa plan.preferences
            if ($activity->wasChanged('preference_types') && !in_array($activity->type, ['Transport','Accommodation'], true)) {
                $bad = $activity->planItems()->with('travelPlan:id,preferences')->get()
                    ->filter(function($pi) use ($activity) {
                        $p = collect($pi->travelPlan->preferences ?? []);
                        $a = collect($activity->preference_types ?? []);
                        return $p->isNotEmpty() && $p->intersect($a)->isEmpty();
                    });
                if ($bad->isNotEmpty()) {
                    abort(422, 'Updated preferences no longer match some plans. Adjust plan preferences or remove those items.');
                }
            }

            // Re-kalkulacije za price/duration/name
            $touchTime   = $activity->wasChanged('duration');
            $touchAmount = $activity->wasChanged('price'); 
            $touchName   = $activity->wasChanged('name');

            if ($touchTime || $touchAmount || $touchName) {
                // Učitaj sve plan stavke sa planom (treba nam budget, pax, end_date i retur transport guard)
                $items = $activity->planItems()->with(['travelPlan.planItems.activity'])->get();

                foreach ($items as $pi) {
                    $plan = $pi->travelPlan;

                    // 1) TIME_TO ako se menja duration
                    if ($touchTime) {
                        $newTimeTo = Carbon::parse($pi->time_from)->copy()->addMinutes($activity->duration);

                        // guard na return transport (mora da završi pre polaska nazad)
                        $returnTransport = $plan->planItems
                            ->first(fn($x) => optional($x->activity)->type === 'Transport'
                                        && Carbon::parse($x->time_from)->isSameDay(Carbon::parse($plan->end_date)));

                        if ($returnTransport && $newTimeTo->gt(Carbon::parse($returnTransport->time_from))) {
                            abort(422, "Activity '{$activity->name}' would end after the return transport in plan #{$plan->id}.");
                        }

                        // provera overlapa (Accommodation se ne računa)
                        if ($activity->type !== 'Accommodation') {
                            $overlap = $plan->planItems()
                                ->where('id','!=',$pi->id)
                                ->whereHas('activity', fn($q) => $q->where('type','!=','Accommodation'))
                                ->where(function($q) use ($pi,$newTimeTo) {
                                    $from = Carbon::parse($pi->time_from);
                                    $q->whereBetween('time_from', [$from, $newTimeTo])
                                    ->orWhereBetween('time_to',   [$from, $newTimeTo])
                                    ->orWhere(function($qq) use ($from,$newTimeTo){
                                        $qq->where('time_from','<=',$from)->where('time_to','>=',$newTimeTo);
                                    });
                                })->exists();
                            if ($overlap) {
                                abort(409, "Updating duration would cause overlap in plan #{$plan->id}.");
                            }
                        }

                        $pi->time_to = $newTimeTo;
                    }

                    // 2) AMOUNT ako se menja price (price * passenger_count)
                    $delta = 0;
                    if ($touchAmount) {
                        $newAmount = $activity->price * $plan->passenger_count;
                        $delta = $newAmount - (float)$pi->amount;

                        // ako bi total_cost probio budget: ili rebalance ili zabrani
                        if ($delta > 0 && ($plan->total_cost + $delta) > $plan->budget) {
                            if ($request->boolean('auto_rebalance')) {
                                // pozovi svoj rebalans servis da oslobodi prostor
                                app(TravelPlanUpdateService::class)->adjustBudget($plan, (float)$plan->budget, (float)$plan->budget);
                            } else {
                                abort(422, "Price increase would exceed budget in plan #{$plan->id}.");
                            }
                            $plan->refresh();
                            if (($plan->total_cost + $delta) > $plan->budget) {
                                abort(422, "Unable to rebalance plan #{$plan->id} to fit the new price.");
                            }
                        }

                        $pi->amount = $newAmount;
                    }

                    // 3) NAME – ažuriraj samo ako je identično starom imenu Activity (da ne prepišeš korisničke izmene)
                    if ($touchName && $pi->name === $old->name) {
                        $pi->name = $activity->name;
                    }

                    $pi->save();

                    // sinkronizuj total_cost na nivou plana (samo kad se menja amount)
                    if ($delta !== 0) {
                        $plan->increment('total_cost', $delta);
                    }
                }
            }

            return new ActivityResource($activity);
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Activity $activity)
    {
        try {
            $activity->delete();
            //return response()->json(['data' => null, 'message' => 'Activity deleted successfully.'], 200);
            return response()->noContent();
        } catch (QueryException $e) {
            return response()->json([  // Handle foreign key constraint violation- Plan items are linked to this activity
                'message' => 'Activity is linked to plan items and cannot be deleted.'
            ], 409);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to delete activity.'], 500);
            
        }
    }
    public function options()
    {
        try {
            $data = Cache::remember('activity_options_v1', 600, function () {

                // Destinacije
                $destinations = Activity::query()
                    ->whereNotNull('location')
                    ->distinct()->orderBy('location')
                    ->pluck('location');

                // Start lokacije
                $startLocations = Activity::query()
                    ->where('type', 'Transport')
                    ->whereNotNull('start_location')
                    ->distinct()->orderBy('start_location')
                    ->pluck('start_location');

                // Transport modovi
                $transportModes = Activity::query()
                    ->where('type', 'Transport')
                    ->whereNotNull('transport_mode')
                    ->distinct()->orderBy('transport_mode')
                    ->pluck('transport_mode');

                // Smeštajne klase
                $accommodationCls = Activity::query()
                    ->where('type', 'Accommodation')
                    ->whereNotNull('accommodation_class')
                    ->distinct()->orderBy('accommodation_class')
                    ->pluck('accommodation_class');

                // PREFS: unija iz JSON kolone preference_types
                $prefRows = Activity::query()
                    ->whereNotNull('preference_types')
                    ->pluck('preference_types');  // dobijas array ili JSON string (zavisi od $casts u modelu)

                $preferences = Activity::query()
                ->whereNotNull('preference_types')
                ->pluck('preference_types')
                ->flatten()
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();

                return compact(
                    'destinations',
                    'startLocations',
                    'transportModes',
                    'accommodationCls',
                    'preferences'
                );
            });

            return response()->json($data);
        } catch (\Throwable $e) {
            \Log::error("Activity options failed: ".$e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destinationsFeed()
    {
        $perPage = (int) request('perPage', 12);

        // 1) izdvojimo PREGLED lokacija (distinct + paginate)
        $page = (int) request('page', 1);
        $cacheKey = "dest_feed_locs_v1:page={$page}:per={$perPage}";

        $payload = Cache::remember($cacheKey, 600, function () use ($perPage) {
            // DISTINCT + paginate — rešavamo paginaciju lokacija
            $locationsPage = DB::table('activities')
                ->select('location')
                ->whereNotNull('location')
                ->distinct()
                ->orderBy('location')
                ->paginate($perPage);

            $locations = collect($locationsPage->items())->pluck('location');

            // 2) za svaku lokaciju uzmi do 15 "slobodnih" aktivnosti
            //    (bez Transport/Accommodation). Ako želiš stabilniji izbor, zameni
            //    ->inRandomOrder() sa ->orderBy('popularity') ili sl.
            $cardsByLoc = [];
            foreach ($locations as $loc) {
                $cardsByLoc[$loc] = Activity::query()
                    ->where('location', $loc)
                    ->whereNotIn('type', ['Transport', 'Accommodation'])
                    ->select('id','name','type','price','duration','image_url','location')
                    ->inRandomOrder()            // brzi vizuelni miks; može i deterministički sort
                    ->limit(15)
                    ->get()
                    ->toArray();
            }

            return [
                'data' => [
                    'locations' => $locations->values(),
                    'cards'     => $cardsByLoc,
                ],
                'meta' => [
                    'current_page' => $locationsPage->currentPage(),
                    'last_page'    => $locationsPage->lastPage(),
                    'per_page'     => $locationsPage->perPage(),
                    'total'        => $locationsPage->total(), // broj lokacija, ne aktivnosti
                ],
            ];
        });

        return response()->json($payload);
    }
    


}

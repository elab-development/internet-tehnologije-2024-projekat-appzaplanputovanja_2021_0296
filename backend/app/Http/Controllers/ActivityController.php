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
            'type'               => ['required',
                                Rule::in(['Transport','Accommodation','Food&Drink','Culture&Sightseeing',
                                'Shopping&Souvenirs','Nature&Adventure','Relaxation&Wellness',
                                'Family-Friendly','Educational&Volunteering','Entertainment&Leisure','other'])],    
            'name'               => 'required|string|max:255',
            'price'              => 'required|numeric|min:0',
            'duration'           => 'required|integer|min:0',
            'location'           => 'required|string|max:255',
            'content'            => 'nullable|string',
            'preference_types'   => 'required|array',
            // Za validaciju svakog elementa preference_types niza:
            'preference_types.*' => [   
                                    Rule::in(Activity::availablePreferenceTypes()), ],
            'image_url'          => 'nullable|url|max:2048',
            'transport_mode'     => ['required_if:type,Transport','prohibited_unless:type,Transport',
                                     Rule::in(['airplane','train','car','bus'])],
            'accommodation_class'=> ['required_if:type,Accommodation','prohibited_unless:type,Accommodation',
                                    Rule::in(['hostel','guesthouse','budget_hotel','standard_hotel','boutique_hotel','luxury_hotel',
                                    'resort','apartment','bed_and_breakfast','villa','mountain_lodge','camping','glamping'])],
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
}

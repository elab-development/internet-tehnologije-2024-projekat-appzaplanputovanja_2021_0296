<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use Carbon\Carbon;
use App\Models\PlanItem;
use App\Models\Activity;
use App\Models\TravelPlan;
use App\Http\Resources\PlanItemResource;
use App\Http\Resources\ActivityResource;

class PlanItemController extends Controller
{
    // vraća sve stavke za dati plan
    public function index(TravelPlan $travelPlan, Request $request) //radimo  u okviru tacno odredjenog travel plana
    {
        $q = $travelPlan->planItems()->with('activity');  //vuce i relaciju activity

        $this->authorize('view', $travelPlan);  //proverava da li korisnik ima pravo da vidi ovaj plan
        
        // Filter by activity type
        if ($type = $request->query('type')) {
            $q->whereHas('activity', fn($a) => $a->where('type', $type));
        }
        // Filter by time interval
        $winFrom = $request->query('from');
        $winTo   = $request->query('to');
        if ($winFrom || $winTo) {
            $wf = $winFrom ? Carbon::parse($winFrom) : Carbon::minValue();
            $wt = $winTo   ? Carbon::parse($winTo)   : Carbon::maxValue();
            $q->where(function($qq) use ($wf,$wt){
                $qq->where('time_from','<=',$wt)
                ->where('time_to','>=',$wf);
            });
        }
        // Pagination with a maximum of 100 items per page
        $perPage = min(max($request->integer('per_page', 50), 1), 100);
        
        return PlanItemResource::collection($q->paginate($perPage)->appends($request->query()));
        //return response()->json($q->paginate($perPage)->appends($request->query()));
        //return response()->json($travelPlan->planItems()->with('activity')->get());
    }

    // validira activity_id i time_from, računa time_to, amount i name pa kreira novu stavku
    public function store(Request $request, TravelPlan $travelPlan)
    {
        // 1) Osnovna validacija unosa
        $data = $request->validate([
            'activity_id' => 'required|exists:activities,id',
            'time_from'   => ['required', 'date', 
            'after_or_equal:' . $travelPlan->start_date],       
        ]); 
        try {
            return DB::transaction(function () use ($data, $travelPlan) {
                $activity = Activity::findOrFail($data['activity_id']);
                 // Nađi povratni transport- provera da li je time_to unutar granica plana
                $returnTransport = $travelPlan->planItems()
                    ->whereHas('activity', fn($q) => $q->where('type', 'Transport'))
                    ->whereDate('time_from', $travelPlan->end_date)
                    ->orderBy('time_from','desc')
                    ->first();

                // 2) Poklapanje destinacije plana i lokacije aktivnosti
                if ($activity->location !== $travelPlan->destination) {
                    return response()->json([
                        'message' => 'The activity is not in the destination location of this plan.',
                        'code'    => 'location_mismatch',
                    ], 422);
                }

                // 3) Poklapanje preferencija (dozvoli ako plan nema preferencije)
                $planPrefs = $travelPlan->preferences ?? [];
                $actPrefs  = $activity->preference_types ?? [];
                if (!empty($planPrefs) && empty(array_intersect($planPrefs, $actPrefs))) {
                    return response()->json([
                        'message' => 'Activity does not match plan preferences.',
                        'code'    => 'preference_mismatch',
                    ], 422);
                }

                // 4) Računanje vremena
                $timeFrom = Carbon::parse($data['time_from']);
                $timeTo   = (clone $timeFrom)->addMinutes($activity->duration);

                // Provera da li je time_from unutar granica plana
                if ($returnTransport && $timeTo->gt($returnTransport->time_from)) {
                    return response()->json([
                        'message' => 'The activity must end before or at the start of return transport.'
                    ], 422);
                }

                // 5) Provera preklapanja (Accommodation sme da se preklapa sa svima)
                if ($activity->type !== 'Accommodation') {
                    $overlap = PlanItem::where('travel_plan_id', $travelPlan->id)
                        ->whereHas('activity', function ($q) {
                            $q->where('type', '!=', 'Accommodation');
                        })
                        ->where(function ($q) use ($timeFrom, $timeTo) {
                            $q->whereBetween('time_from', [$timeFrom, $timeTo])
                            ->orWhereBetween('time_to', [$timeFrom, $timeTo])
                            ->orWhere(function ($qq) use ($timeFrom, $timeTo) {
                                $qq->where('time_from', '<=', $timeFrom)
                                    ->where('time_to', '>=', $timeTo);
                            });
                        })
                        ->exists();

                    if ($overlap) {
                        return response()->json([
                            'message' => 'It overlaps in time with the existing activity.',
                            'code'    => 'time_overlap',
                        ], 409);
                    }
                }

                // 6) Budžet i amount
                $amount = $activity->price * $travelPlan->passenger_count;
                if (($travelPlan->total_cost + $amount) > $travelPlan->budget) {
                    return response()->json([
                        'message' => 'Plan budget overrun.',
                        'code'    => 'budget_exceeded',
                    ], 422);
                }

                // 7) Kreiraj PlanItem + ažuriraj total_cost
                $planItem = $travelPlan->planItems()->create([
                    'activity_id' => $activity->id,
                    'time_from'   => $timeFrom,
                    'time_to'     => $timeTo,
                    'amount'      => $amount,
                    'name'        => $activity->name,
                ]);

                $travelPlan->increment('total_cost', $amount);

                return (new PlanItemResource($planItem->load('activity')))->response()->setStatusCode(Response::HTTP_CREATED);
                //return response()->json($planItem, Response::HTTP_CREATED);
            });
        } catch (\Throwable $e) {
            // Neočekivana greska
            return response()->json([
                'message' => 'Error adding plan item.',
                'error'   => app()->isProduction() ? null : $e->getMessage(),
            ], 500);
        }
    }

    //omogućava izmenu time_from (i preračunavanje time_to)
    public function update(Request $request, TravelPlan $travelPlan, PlanItem $planItem)
    {
        if ($planItem->travel_plan_id !== $travelPlan->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $data = $request->validate([
            'time_from' => ['sometimes', 'required', 'date', 'after_or_equal:' . $travelPlan->start_date],
        ]);

        if (isset($data['time_from'])) {
            $activity = $planItem->activity;
            $timeFrom = Carbon::parse($data['time_from']);
            $timeTo   = $timeFrom->copy()->addMinutes($activity->duration);

            // Nađi povratni transport
            $returnTransport = $travelPlan->planItems()
                    ->whereHas('activity', fn($q) => $q->where('type', 'Transport'))
                    ->whereDate('time_from', $travelPlan->end_date)
                    ->orderBy('time_from','desc')
                    ->first();
            // Provera da li je time_from unutar granica plana
            if ($returnTransport && $timeTo->gt($returnTransport->time_from)) {
                return response()->json([
                    'message' => 'The activity must end before or at the start of return transport.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            // provera preklapanja osim za ovu stavku
            $overlap = $travelPlan->planItems()
                ->where('id', '!=', $planItem->id)
                ->whereHas('activity', fn($q) => $q->where('type', '!=', 'Accommodation'))
                ->where(function ($query) use ($timeFrom, $timeTo) {
                    $query->whereBetween('time_from', [$timeFrom, $timeTo])
                        ->orWhereBetween('time_to', [$timeFrom, $timeTo])
                        ->orWhere(function ($q) use ($timeFrom, $timeTo) {
                            $q->where('time_from', '<=', $timeFrom)
                                ->where('time_to', '>=', $timeTo);
                        });
                })
                ->exists();

            if ($overlap) {
                return response()->json(['message' => 'The updated time overlaps with another activity.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $data['time_to'] = $timeTo;
        }

        $planItem->update($data);

        return new PlanItemResource($planItem->load('activity'));
        //return response()->json($planItem);
    }


    //briše stavku nakon verifikacije pripadnosti planu
    public function destroy(TravelPlan $travelPlan, PlanItem $planItem)
    {
        if ($planItem->travel_plan_id !== $travelPlan->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $travelPlan->decrement('total_cost', $planItem->amount);
        $travelPlan->refresh();
        if ($travelPlan->total_cost < 0) { //u slucaju da se stavka obrise vise puta
            $travelPlan->update(['total_cost' => 0]);
        }

        $planItem->delete();

        return response()->noContent();
        //return response()->json([
          //  'data'    => null,
           // 'message' => 'Plan item deleted successfully.'
        //], 200);
    }
}

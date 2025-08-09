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

class PlanItemController extends Controller
{
    // vraća sve stavke za dati TravelPlan
    public function index(TravelPlan $travelPlan) //radimo  u okviru tacno odredjenog travel plana
    {
        return response()->json($travelPlan->planItems);
    }

    // validira activity_id i time_from, računa time_to, amount i name pa kreira novu stavku
    public function store(Request $request, TravelPlan $travelPlan)
    {
        // 1) Osnovna validacija unosa
        $data = $request->validate([
            'activity_id' => 'required|exists:activities,id',
            'time_from'   => ['required', 'date', 
            'after_or_equal:' . $travelPlan->start_date, 'before_or_equal:' . $travelPlan->end_date],
        ]);

        try {
            return DB::transaction(function () use ($data, $travelPlan) {
                $activity = Activity::findOrFail($data['activity_id']);

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

                // TravelPlan end_date je date; dozvoli do kraja dana
                $planEndEod = Carbon::parse($travelPlan->end_date)->endOfDay();
                if ($timeTo->greaterThan($planEndEod)) {
                    return response()->json([
                        'message' => 'The activity time goes outside the travel period.',
                        'code'    => 'time_out_of_range',
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

                return response()->json($planItem, Response::HTTP_CREATED);
            });
        } catch (\Throwable $e) {
            // Neočekivano – globalni Handler bi vratio 500, ali vraćamo jasnu poruku
            return response()->json([
                'message' => 'Error adding plan item.',
                'error'   => app()->isProduction() ? null : $e->getMessage(),
            ], 500);
        }
    }



    //prikazuje pojedinačnu stavku, sa proverom da pripada zadatom planu
    public function show(TravelPlan $travelPlan, PlanItem $planItem)
    {
        if ($planItem->travel_plan_id !== $travelPlan->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json($planItem);
    }

    //omogućava izmenu time_from (i preračunavanje time_to)
    public function update(Request $request, TravelPlan $travelPlan, PlanItem $planItem)
    {
        // Da li stavka pripada ovom planu?
        if ($planItem->travel_plan_id !== $travelPlan->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        // moze da se menja samo time_from
        $data = $request->validate([
            'time_from' => ['sometimes', 'required', 'date', 
            'after_or_equal:' . $travelPlan->start_date, 'before_or_equal:' . $travelPlan->end_date],
        ]);

        try {
            return DB::transaction(function () use ($data, $travelPlan, $planItem) {
                if (!isset($data['time_from'])) {
                    // Nema promene – samo vrati aktuelno stanje
                    return response()->json($planItem);
                }

                $timeFrom = Carbon::parse($data['time_from']);
                $activity = $planItem->activity;
                $timeTo   = (clone $timeFrom)->addMinutes($activity->duration);

                // Proveri da li time_to ostaje u okviru putovanja
                $planEndEod = Carbon::parse($travelPlan->end_date)->endOfDay();
                if ($timeTo->greaterThan($planEndEod)) {
                    return response()->json([
                        'message' => 'The activity time goes outside the travel period.',
                        'code'    => 'time_out_of_range',
                    ], 422);
                }

                // Provera preklapanja (ignoriši Accommodation i ignoriši samu stavku)
                if ($activity->type !== 'Accommodation') {
                    $overlap = PlanItem::where('travel_plan_id', $travelPlan->id)
                        ->where('id', '!=', $planItem->id)
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

                // Upis promene
                $planItem->update([
                    'time_from' => $timeFrom,
                    'time_to'   => $timeTo,
                ]);

                return response()->json($planItem);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error when modifying a plan item.',
                'error'   => app()->isProduction() ? null : $e->getMessage(),
            ], 500);
        }
    }


    //briše stavku nakon verifikacije pripadnosti planu
    public function destroy(TravelPlan $travelPlan, PlanItem $planItem)
    {
        if ($planItem->travel_plan_id !== $travelPlan->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $planItem->delete();

        //return response()->noContent();
        return response()->json([
            'data'    => null,
            'message' => 'Plan item deleted successfully.'
        ], 200);
    }
}

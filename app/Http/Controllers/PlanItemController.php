<?php

namespace App\Http\Controllers;
use App\Models\Activity;
use App\Models\TravelPlan;
use App\Models\PlanItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Carbon\Carbon;

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
        $data = $request->validate([
            'activity_id' => 'required|exists:activities,id',
            'time_from'   => ['required', 'date', 'after_or_equal:' . $travelPlan->start_date, 'before_or_equal:' . $travelPlan->end_date],
        ]);

        $activity = Activity::findOrFail($data['activity_id']);
        $timeFrom = Carbon::parse($data['time_from']);

        $planItem = $travelPlan->planItems()->create([
            'activity_id' => $activity->id,
            'time_from'   => $timeFrom,
            'time_to'     => $timeFrom->copy()->addMinutes($activity->duration),
            'amount'      => $activity->price * $travelPlan->passenger_count,
            'name'        => $activity->name,
        ]);

        return response()->json($planItem, Response::HTTP_CREATED);
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
        if ($planItem->travel_plan_id !== $travelPlan->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $data = $request->validate([
            'time_from' => ['sometimes', 'required', 'date', 'after_or_equal:' . $travelPlan->start_date, 'before_or_equal:' . $travelPlan->end_date],
        ]);

        if (isset($data['time_from'])) {
            $timeFrom = Carbon::parse($data['time_from']);
            $activity = $planItem->activity;
            $data['time_to'] = $timeFrom->copy()->addMinutes($activity->duration);
        }

        $planItem->update($data);

        return response()->json($planItem);
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

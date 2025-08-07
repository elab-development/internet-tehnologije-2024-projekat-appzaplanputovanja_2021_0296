<?php

namespace App\Http\Controllers;

use App\Models\TravelPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use illuminate\Http\JsonResponse;
use Carbon\Carbon;

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
            'start_location'  => 'required|string',
            'destination'     => 'required|string',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'budget'          => 'required|numeric|min:0',
            'passenger_count' => 'required|integer|min:1',
            'preferences'     => 'required|array',
            'preferences.*' => [
                Rule::in(TravelPlan::availablePreferences()),
            ],
        ]);

        $plan = TravelPlan::create($data);

        return response()->json($plan, 201);
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
        $data = $request->validate([
            'start_location'  => 'sometimes|required|string',
            'destination'     => 'sometimes|required|string',
            'start_date'      => 'sometimes|required|date',
            'end_date'        => 'sometimes|required|date|after_or_equal:start_date',
            'budget'          => 'sometimes|required|numeric|min:0',
            'passenger_count' => 'sometimes|required|integer|min:1',
            'preferences'     => 'sometimes|required|array',
            'preferences.*' => [
                Rule::in(TravelPlan::availablePreferences()),
            ],
        ]);

        $travelPlan->update($data);

        return response()->json($travelPlan);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TravelPlan $travelPlan)
    {
        $travelPlan->delete();

        return response()->noContent();
    }

    //GET /api/travel-plans/upcoming
    public function upcoming(Request $request): JsonResponse
    {
        $plans = TravelPlan::where('start_date', '>', Carbon::now())
                           ->orderBy('start_date', 'asc') //rastuce po datumu pocetka
                           ->get();

        return response()->json([
            'data' => $plans,
            'count' => $plans->count(),
        ]);
    }
}

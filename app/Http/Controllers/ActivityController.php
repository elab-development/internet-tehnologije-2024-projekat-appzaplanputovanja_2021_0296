<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Activity::query();

        // Filter by type (enum)
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        // Filter by a single preference type
        if ($pref = $request->query('preference')) {
            $query->whereJsonContains('preference_types', $pref);
        }

        return response()->json($query->paginate(10));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => [
                'required',
                Rule::in(Activity::availableTypes()),        // <–– ovde zovemo statičku f-ju koja vraća niz dozvoljenih tipova 
            ],
            'name'             => 'required|string|max:255',
            'price'            => 'required|numeric|min:0',
            'duration'         => 'required|integer|min:0',
            'location'         => 'required|string|max:255',
            'content'          => 'nullable|string',
            'preference_types'   => 'required|array',
            // Za validaciju svakog elementa preference_types niza:
            'preference_types.*' => [
                Rule::in(Activity::availablePreferenceTypes()),
            ],
        ]);

        $activity = Activity::create($data);

        return response()->json($activity, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Activity $activity)
    {
        return response()->json($activity);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Activity $activity)
    {
        $data = $request->validate([
            'type' => [
                'sometimes',
                'required',
                Rule::in(Activity::availableTypes()),
            ],
            'name'             => 'sometimes|required|string|max:255', //Ovo polje nije obavezno da se salje, ali ako se posalje, ne sme biti prazno i mora biti ispravnog tipa.
            'price'            => 'sometimes|required|numeric|min:0',
            'duration'         => 'sometimes|required|integer|min:0',
            'location'         => 'sometimes|required|string|max:255',
            'content'          => 'nullable|string',
            'preference_types'   => 'sometimes|required|array',
            'preference_types' => [
                Rule::in(Activity::availablePreferenceTypes()),

            ],
        ]);

        $activity->update($data);

        return response()->json($activity);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Activity $activity)
    {
        $activity->delete();

        return response()->noContent();
    }
}

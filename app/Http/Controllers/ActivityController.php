<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Database\QueryException;


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
            $q->where(function ($qq) use ($prefs) {
                foreach ($prefs as $p) {
                    $qq->orWhereJsonContains('preference_types', $p);
                }
            });
        }

        $sortBy  = $request->query('sort_by', 'location');
        $sortDir = $request->query('sort_dir', 'asc');

        if (in_array($sortBy, ['name','price','duration','location']) &&
            in_array($sortDir, ['asc','desc'])) {
            $q->orderBy($sortBy, $sortDir);
        }

        return response()->json($query->paginate(50));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'type'               => ['required',
                       'in:Transport,Accommodation,Food&Drink,Culture&Sightseeing,
                        Shopping&Souvenirs,Nature&Adventure,Relaxation&Wellness,
                        Family-Friendly,Educational&Volunteering,Entertainment&Leisure,other' ],    
            'name'               => 'required|string|max:255',
            'price'              => 'required|numeric|min:0',
            'duration'           => 'required|integer|min:0',
            'location'           => 'required|string|max:255',
            'content'            => 'nullable|string',
            'preference_types'   => 'required|array',
            // Za validaciju svakog elementa preference_types niza:
            'preference_types.*' => [   
                                    Rule::in(Activity::availablePreferenceTypes()), ],
            'transport_mode'     => ['required_if:type,Transport','prohibited_unless:type,Transport',
                                    'in:airplane,train,car,bus,ferry,cruise ship','required_if:type,Transport'],
            'accommodation_class'=> ['required_if:type,Accommodation','prohibited_unless:type,Accommodation',
                                    'in:hostel,guesthouse,budget_hotel,standard_hotel,boutique_hotel,luxury_hotel,
                                    resort,apartment,bed_and_breakfast,villa,mountain_lodge,camping,glamping',],
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
            'type'               => ['sometimes','required',
                       'in:Transport,Accommodation,Food&Drink,Culture&Sightseeing,
                        Shopping&Souvenirs,Nature&Adventure,Relaxation&Wellness,
                        Family-Friendly,Educational&Volunteering,Entertainment&Leisure,other' ],   
            'name'               => 'sometimes|required|string|max:255', //Ovo polje nije obavezno da se salje, ali ako se posalje, ne sme biti prazno i mora biti ispravnog tipa.
            'price'              => 'sometimes|required|numeric|min:0',
            'duration'           => 'sometimes|required|integer|min:0',
            'location'           => 'sometimes|required|string|max:255',
            'content'            => 'sometimes|nullable|string',
            'preference_types'   => 'sometimes|required|array',
            'preference_types.*' => [
                                    Rule::in(Activity::availablePreferenceTypes()),],
            'transport_mode'     => ['sometimes','required_if:type,Transport','prohibited_unless:type,Transport',
                                    'in:airplane,train,car,bus,ferry,cruise ship','required_if:type,Transport'],
            'accommodation_class'=> ['sometimes','required_if:type,Accommodation','prohibited_unless:type,Accommodation',
                                    'in:hostel,guesthouse,budget_hotel,standard_hotel,boutique_hotel,luxury_hotel,
                                    resort,apartment,bed_and_breakfast,villa,mountain_lodge,camping,glamping','required_if:type,Accommodation'],
        ]);

        $activity->update($data);

        return response()->json($activity);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Activity $activity)
    {
        try {
            $activity->delete();
            return response()->json(['data' => null, 'message' => 'Activity deleted successfully.'], 200);
        } catch (QueryException $e) {
            return response()->json([  // Handle foreign key constraint violation- Plan items are linked to this activity
                'message' => 'Activity is linked to plan items and cannot be deleted.'
            ], 409);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to delete activity.'], 500);
        }
    }
}

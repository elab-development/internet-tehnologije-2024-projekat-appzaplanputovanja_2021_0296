<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Carbon\Carbon;
use App\Http\Resources\UserResource;
use App\Http\Resources\TravelPlanResource;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() 
    { 
        //$users=User::all(); 
        //return response()->json($users);

        $users=User::paginate(15); 
        return UserResources::collection($users);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show($user_id) 
    { 
        $user = User::find($user_id); 
        if (is_null($user)){ 
            return response()->json(['message' => 'Data not found'], 404);
        } 
        return new UserResource($user->travelPlans()->with(['planItems.activity']));
        //return response()->json($user);      
    } 

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        // Provera da li postoje budući planovi putovanja
        $hasFuturePlans = $user->travelPlans()
            ->where('start_date', '>', now())
            ->exists();

        if ($hasFuturePlans) {
            return response()->json([
                'error' => 'The user cannot be deleted because they have future travel plans.'
            ], 409); // 409 Conflict
        }

        // Ako nema budućih planova, obriši korisnika
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully.'
        ], 200);
    }

    //GET /api/users/{user}/travel-plans
    public function plans(User $user, Request $request): JsonResponse
    {
        //filter by upcoming plans

        $query = $user->travelPlans()
                ->with(['planItems.activity'])
                ->whereDate('start_date', '>=', Carbon::today())
                ->orderBy('start_date', 'asc');

        $perPage = $request->integer('per_page', 15);
        
        return TravelPlanResource::collection($query->paginate($perPage)->appends($request->$query()));
        //return response()->json($query->paginate($perPage));

        // bez paginacije: return response()->json(['data' => $query->get()]);
        //sve planove korisnika: return response()->json($user->travelPlans);
    }

}

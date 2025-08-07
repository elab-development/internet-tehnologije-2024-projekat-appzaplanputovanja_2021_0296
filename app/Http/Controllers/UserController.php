<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() 
    { 
        $users=User::all(); 
        return $users; 
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
            return response()->json('Data not found', 404); 
        } 
        return response()->json($user);      
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
        $user->delete();
        return response()->noContent();
    }

    //GET /api/users/{user}/travel-plans
    public function plans(User $user): JsonResponse
    {
        return response()->json($user->travelPlans);
    }

}

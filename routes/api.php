<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TravelPlanController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\PlanItemController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// RESOURCE ruta za TravelPlans
Route::apiResource('travel-plans', TravelPlanController::class); //kreira sve osnovne REST rute  

// RESOURCE ruta za Activities
Route::apiResource('activities', ActivityController::class);

//uzima sve stavke za jedan TravelPlan
Route::get(
    'travel-plans/{travel_plan}/items',
    [PlanItemController::class, 'index']
)->name('travel-plans.items.index');

// kreiranje nove stavke unutar konkretnog TravelPlan-a
Route::post(
    'travel-plans/{travel_plan}/items',
    [PlanItemController::class, 'store']
)->name('travel-plans.items.store');

//ažuriranje jedne stavke plana
Route::patch(
    'travel-plans/{travel_plan}/items/{plan_item}', 
    [PlanItemController::class, 'update']
)->name('travel-plans.items.update');

//brisanje stavke plana
Route::delete(
    'travel-plans/{travel_plan}/items/{plan_item}', 
    [PlanItemController::class, 'destroy']
)->name('travel-plans.items.destroy');

// uzima sve nadolazece planove određenog korisnika
Route::get(
    'users/{user}/travel-plans',
    [UserController::class, 'plans']
)->name('users.travel-plans');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
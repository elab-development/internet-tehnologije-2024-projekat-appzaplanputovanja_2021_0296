<?php

// routes/api.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController, TravelPlanController, ActivityController, PlanItemController, UserController, SettingController};

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

// PUBLIC (auth)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('login',    [AuthController::class, 'login'])->name('auth.login');
});

// AUTHENTICATED (samo ulogovani)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    // Korisnik upravlja SAMO svojim TravelPlan-ovima i njihovim stavkama
    Route::apiResource('travel-plans', TravelPlanController::class); // RESOURCE ruta za TravelPlans

    // Listanje stavki unutar konkretnog TravelPlan-a
    Route::get('travel-plans/{travel_plan}/items',  [PlanItemController::class, 'index'])->name('travel-plans.items.index'); 
    
    // Pretraga sopstvenih planova
    Route::get('travel-plans/search', [TravelPlanController::class, 'search']);

    // Export PDF sopstvenog plana
    Route::get('travel-plans/{travel_plan}/export/pdf', [TravelPlanController::class, 'exportPdf'])->name('travel-plans.export.pdf');

    // Vidi samo svoje nadolazeće planove
    Route::get('users/{user}/travel-plans', [UserController::class, 'plans'])->name('users.travel-plans');
});

// ADMIN-ONLY
Route::middleware(['auth:sanctum','admin'])->group(function () {
    Route::apiResource('activities', ActivityController::class);   // CRUD nad aktivnostima samo admin
    Route::apiResource('users', UserController::class)->only(['index','show','destroy']); // pregled/brisanje korisnika
    Route::get('settings',        [SettingController::class, 'index']);
    Route::get('settings/{key}',  [SettingController::class, 'show']);
    Route::post('settings',       [SettingController::class, 'upsert']);
    Route::post('settings/batch', [SettingController::class, 'batch']);

    Route::post('travel-plans/{travel_plan}/items', [PlanItemController::class, 'store'])->name('travel-plans.items.store');                    // kreiranje nove stavke unutar konkretnog TravelPlan-a
    Route::patch('travel-plans/{travel_plan}/items/{plan_item}', [PlanItemController::class, 'update'])->name('travel-plans.items.update');     // ažuriranje jedne stavke plana//ažuriranje jedne stavke plana
    Route::delete('travel-plans/{travel_plan}/items/{plan_item}',[PlanItemController::class, 'destroy'])->name('travel-plans.items.destroy');   //brisanje stavke plana

    // Admin može videti SVE travel-planove (nema ograničenja na vlasništvo)
});

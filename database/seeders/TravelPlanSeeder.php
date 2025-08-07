<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TravelPlan;
use App\Models\User;
class TravelPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //za svakog korisnika kreira po dva plana
        User::all()->each(function ($user) {
            TravelPlan::factory(2)->create([
                'user_id' => $user->id,
            ]);
        });
    }
}

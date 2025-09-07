<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            UserSeeder::class,
            ActivitySeeder::class,
            TravelPlanSeeder::class, // kreira planove + obavezne stavke
            PlanItemSeeder::class,   // dodaje dnevne aktivnosti bez preklapanja
        ]);
    }
}

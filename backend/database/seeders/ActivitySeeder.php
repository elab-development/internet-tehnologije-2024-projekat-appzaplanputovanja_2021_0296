<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Activity;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        $locations = ['Prague','Vienna','Budapest','Belgrade','Zagreb'];

        // Za svaku lokaciju obavezne varijante Transport/Accommodation,
        // plus nekoliko slobodnih aktivnosti drugih tipova.
        foreach ($locations as $loc) {
            // Transport varijante (najmanje jedna po modu)
            foreach (['airplane','train','car','bus'] as $mode) {
                Activity::factory()->transport($mode, $loc)->create([
                    'price' => match ($mode) {
                        'airplane' => 180,
                        'train'    => 70,
                        'car'      => 90,
                        'bus'      => 60,
                        default    => 100,
                    },
                    'name'  => "Transport {$loc} ({$mode})",
                ]);
            }

            // Accommodation varijante (par klasa)
            foreach (['budget_hotel','standard_hotel','apartment'] as $cls) {
                Activity::factory()->accommodation($cls, $loc)->create([
                    'price' => match ($cls) {
                        'budget_hotel'   => 35,
                        'standard_hotel' => 55,
                        'apartment'      => 50,
                        default          => 45,
                    },
                ]);
            }

            // 10+ random aktivnosti (Food, Culture, Nature...)
            Activity::factory()
                ->count(12)
                ->forLocation($loc)
                ->create();
        }
    }
}

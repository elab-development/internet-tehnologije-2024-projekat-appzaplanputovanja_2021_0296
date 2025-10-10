<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Activity;

class ActivitySeeder extends Seeder
{
    private function img(string $path): string
    {
        $base = rtrim(config('app.url') ?: env('APP_URL', 'http://127.0.0.1:8000'), '/');
        return $base . $path;
    }
    public function run(): void
    {
        $locations = ['Prague','Budapest','Amsterdam','Lisbon','Valencia'];

        $startLocations = ['Belgrade','Ljubljana','Zagreb','Sarajevo','Novi Sad','Niš'];

        // VIŠE transport moda i smeštajnih klasa 
        $transportModes = ['airplane','train','bus','car'];
        $accClasses = [
            'hostel','guesthouse','budget_hotel','standard_hotel','boutique_hotel',
            'luxury_hotel','resort','apartment','bed_and_breakfast','villa',
            'mountain_lodge','camping','glamping'
        ];

        $freeTypes = [
            'Food&Drink','Culture&Sightseeing','Shopping&Souvenirs',
            'Nature&Adventure','Relaxation&Wellness'
        ];

        // 1) TRANSPORT SLIKE
        $transportImagesByMode = [
            'airplane' => [ 
                $this->img('/activity-images/airplane1.jpg'),
                $this->img('/activity-images/airplane2.jpg'),
            ],
            'train' => [
                $this->img('/activity-images/train1.jpg'),
                $this->img('/activity-images/train2.jpg'),
            ],
            'bus' => [
                $this->img('/activity-images/bus1.jpg'),
                $this->img('/activity-images/bus2.jpg'),
            ],
            'car' => [
                $this->img('/activity-images/car1.jpg'),
                $this->img('/activity-images/car2.jpg'),
            ],
        ];

        // 2) SMEŠTAJ SLIKE
        $accommodationImages = [
            'hostel' => [
                $this->img('/activity-images/hostel1.jpg'),
                $this->img('/activity-images/hostel2.jpg'),
            ],
            'guesthouse' => [
                $this->img('/activity-images/guesthouse1.jpg'),
                $this->img('/activity-images/guesthouse2.jpg'),
            ],
            'budget_hotel' => [
                $this->img('/activity-images/budget_hotel1.jpg'),
                $this->img('/activity-images/budget_hotel2.jpg'),
            ],
            'standard_hotel' => [
                $this->img('/activity-images/standard_hotel1.jpg'),
                $this->img('/activity-images/standard_hotel2.jpg'),
            ],
            'boutique_hotel' => [
                $this->img('/activity-images/boutique_hotel1.jpg'),
                $this->img('/activity-images/boutique_hotel2.jpg'),
            ],
            'luxury_hotel' => [
                $this->img('/activity-images/luxury_hotel1.jpg'),
                $this->img('/activity-images/luxury_hotel2.jpg'),
            ],
            'resort' => [
                $this->img('/activity-images/resort1.jpg'),
                $this->img('/activity-images/resort2.jpg'),
            ],
            'apartment' => [
                $this->img('/activity-images/apartment1.jpg'),
                $this->img('/activity-images/apartment2.jpg'),
            ],
            'bed_and_breakfast' => [
                $this->img('/activity-images/bed_and_breakfast1.jpg'),
                $this->img('/activity-images/bed_and_breakfast2.jpg'),
            ],
            'villa' => [
                $this->img('/activity-images/villa1.jpg'),
                $this->img('/activity-images/villa2.jpg'),
            ],
            'mountain_lodge' => [
                $this->img('/activity-images/mountain_lodge1.jpg'),
                $this->img('/activity-images/mountain_lodge2.jpg'),
            ],
            'camping' => [
                $this->img('/activity-images/camping1.jpg'),
                $this->img('/activity-images/camping2.jpg'),
            ],
            'glamping' => [
                $this->img('/activity-images/glamping1.jpg'),
                $this->img('/activity-images/glamping2.jpg'),
            ],
        ];

        // 3) FREE AKTIVNOSTI 
        $activityImages = [
            'Food&Drink' => [
                $this->img('/activity-images/Food&Drink1.jpg'),
                $this->img('/activity-images/Food&Drink2.jpg'),
                $this->img('/activity-images/Food&Drink3.jpg'),
                $this->img('/activity-images/Food&Drink4.jpg'),
                $this->img('/activity-images/Food&Drink5.jpg'),
            ],
            'Nature&Adventure' => [
                $this->img('/activity-images/Nature&Adventure1.jpg'),
                $this->img('/activity-images/Nature&Adventure2.jpg'),
                $this->img('/activity-images/Nature&Adventure3.jpg'),
                $this->img('/activity-images/Nature&Adventure4.jpg'),
                $this->img('/activity-images/Nature&Adventure5.jpg'),
            ],
            'Relaxation&Wellness' => [
                $this->img('/activity-images/Relaxation&Wellness1.jpg'),
                $this->img('/activity-images/Relaxation&Wellness2.jpg'),
                $this->img('/activity-images/Relaxation&Wellness3.jpg'),
                $this->img('/activity-images/Relaxation&Wellness4.jpg'),
                $this->img('/activity-images/Relaxation&Wellness5.jpg'),
            ],
            'Shopping&Souvenirs' => [
                $this->img('/activity-images/Shopping&Souvenirs1.jpg'),
                $this->img('/activity-images/Shopping&Souvenirs2.jpg'),
                $this->img('/activity-images/Shopping&Souvenirs3.jpg'),
                $this->img('/activity-images/Shopping&Souvenirs4.jpg'),
                $this->img('/activity-images/Shopping&Souvenirs5.jpg'),
            ],
        ];

        // Culture&Sightseeing: TAČNO PO LOKACIJAMA 
        $cultureImagesByLocation = [
            'Amsterdam' => $this->img('/activity-images/Culture&SightseeingAmsterdam.jpg'),
            'Budapest'  => $this->img('/activity-images/Culture&SightseeingBudapest.jpg'),
            'Lisbon'    => $this->img('/activity-images/Culture&SightseeingLisbon.jpg'),
            'Prague'    => $this->img('/activity-images/Culture&SightseeingPrague.jpg'),
            'Valencia'  => $this->img('/activity-images/Culture&SightseeingValencia.jpg'),
        ];

        foreach ($startLocations as $from) {
            foreach ($locations as $to) {
                foreach ($transportModes as $mode) {
                    $imgPool = $transportImagesByMode[$mode] ?? [];

                    // Odlazak
                    Activity::create([
                        'type'            => 'Transport',
                        'name'            => "Transport {$from} → {$to} ({$mode})",
                        'location'        => $to,
                        'transport_mode'  => $mode,
                        'price'           => fake()->numberBetween(20, 140),
                        'duration'        => fake()->numberBetween(60, 360),
                        'preference_types'=> ['comfortable_travel'],
                        'image_url'       => $imgPool ? fake()->randomElement($imgPool) : null,
                    ]);

                    // Povratak
                    Activity::create([
                        'type'            => 'Transport',
                        'name'            => "Transport {$to} → {$from} ({$mode})",
                        'location'        => $to,
                        'transport_mode'  => $mode,
                        'price'           => fake()->numberBetween(20, 140),
                        'duration'        => fake()->numberBetween(60, 360),
                        'preference_types'=> ['comfortable_travel'],
                        'image_url'       => $imgPool ? fake()->randomElement($imgPool) : null,
                    ]);
                }
            }
        }


        // Smeštaj
        foreach ($locations as $loc) {
            foreach ($accClasses as $cls) {
                $imgPool = $accommodationImages[$cls] ?? [];
                Activity::create([
                    'type'                 => 'Accommodation',
                    'name'                 => "Accommodation in {$loc} ({$cls})",
                    'location'             => $loc,
                    'transport_mode'       => null,
                    'accommodation_class'  => $cls,
                    'price'                => fake()->numberBetween(20, 80),
                    'duration'             => 24 * 60,
                    'preference_types'     => ['comfortable_travel'],
                    'image_url'            => $imgPool ? fake()->randomElement($imgPool) : null,
                ]);
            }
        }

        // Slobodne aktivnosti
        foreach ($locations as $loc) {
            foreach ($freeTypes as $t) {
                Activity::factory()
                    ->count(5)
                    ->state(function () use ($loc, $t, $activityImages, $cultureImagesByLocation) {
                        $img = $activityImages[$t] ?? null;
                        $img = is_array($img) ? fake()->randomElement($img) : null;
                        // ako je Culture&Sightseeing – uzmi TAČNO sliku za lokaciju (ako postoji)
                        if ($t === 'Culture&Sightseeing' && isset($cultureImagesByLocation[$loc])) {
                            $img = $cultureImagesByLocation[$loc];
                        }
                        return [
                            'type'       => $t,
                            'location'   => $loc,
                            'image_url'  => $img,
                            'transport_mode' => null,
                            'accommodation_class' => null,
                        ];
                    })
                    ->create();
            }
        }
    }
}

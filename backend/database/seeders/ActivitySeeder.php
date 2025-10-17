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

        // 3) SLOBODNE  AKTIVNOSTI 
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

        $accPriceByClass = [
            'hostel'              => [20, 40],
            'guesthouse'          => [30, 60],
            'budget_hotel'        => [40, 70],
            'standard_hotel'      => [60, 100],
            'boutique_hotel'      => [80, 140],
            'luxury_hotel'        => [120, 220],
            'resort'              => [100, 180],
            'apartment'           => [50, 120],
            'bed_and_breakfast'   => [40, 80],
            'villa'               => [150, 300],
            'mountain_lodge'      => [60, 110],
            'camping'             => [15, 35],
            'glamping'            => [40, 90],
        ];

       foreach ($startLocations as $start) {
            foreach ($locations as $dest) {
                if ($start === $dest) continue;

                foreach ($transportModes as $mode) {
                    $imgs = $transportImagesByMode[$mode] ?? [];
                    $img  = $imgs ? $imgs[array_rand($imgs)] : null;

                    // bazna cena za par
                    $base = fake()->numberBetween(40, 180);

                    // 1) OUTBOUND: skuplji
                    Activity::factory()
                        ->transport($mode, $start)   // setuje start_location
                        ->forLocation($dest)         // setuje location
                        ->state([
                        'name'      => "Transport {$start} → {$dest} ({$mode})",
                        'price'     => (int) round($base * 1.15), // skuplji
                        'image_url' => $img,
                        ])->create();

                    // 2) RETURN: jeftiniji (polja OSTAJU ista!)
                    Activity::factory()
                        ->transport($mode, $start)
                        ->forLocation($dest)
                        ->state([
                        'name'      => "Transport {$dest} → {$start} ({$mode})",
                        'price'     => $base, // jeftiniji
                        'image_url' => $img,
                        ])->create();
                }
            }
        }


        // 2) SMEŠTAJ: za svaku destinaciju i klasу
        foreach ($locations as $loc) {
            foreach ($accClasses as $cls) {
                $imgs = $accommodationImages[$cls] ?? [];
                [$min, $max] = $accPriceByClass[$cls] ?? [50, 120]; 
                $price = rand($min, $max);

                Activity::factory()
                    ->accommodation($cls)
                    ->forLocation($loc)
                    ->state([
                        'name'      => "Accommodation in $loc ($cls)",
                        'image_url' => $imgs ? $imgs[array_rand($imgs)] : null,
                        'price'     => $price, 
                        'duration'  => 24*60, 
                    ])->create();
            }
        }

         foreach ($locations as $loc) {
            foreach ($freeTypes as $t) {
                for ($i = 0; $i < 5; $i++) {
                    // --- IZBOR SLIKE ---
                    $img = null;

                    if ($t === 'Culture&Sightseeing' && isset($cultureImagesByLocation[$loc])) {
                        // Culture&Sightseeing: fiksno po lokaciji
                        $img = $cultureImagesByLocation[$loc];
                    } else {
                        // Ostali tipovi: round-robin kroz liste slika
                        $imgs = $activityImages[$t] ?? [];
                        if (!empty($imgs)) {
                            // Za sve ostale tipove aktivnosti (osim Culture&Sightseeing):
                            // prolazi redom kroz dostupne slike tog tipa (round-robin),
                            // a pomoću offseta (crc32 lokacije) svaka lokacija počinje od druge slike
                            $offset = count($imgs) ? (crc32($loc) % count($imgs)) : 0;
                            $img = $imgs[($i + $offset) % count($imgs)];
                        }
                    }

                    // --- CENA PO TIPU ---
                    // Nature & Culture oko 0; za ostale tipove okvirne realne vrednosti
                    $price = match ($t) {
                        'Culture&Sightseeing' => (rand(1,10) <= 3) ? 0 : rand(5, 20),   // ~30% free, inače 5–20
                        'Nature&Adventure'    => (rand(1,10) <= 3) ? 0 : rand(10, 30),  // ~30% free, inače 10–30
                        'Shopping&Souvenirs'  => rand(12, 45), 
                        'Relaxation&Wellness' => rand(8, 30),  
                        'Food&Drink'          => rand(6, 25),  
                        default                => rand(5, 40),
                    };

                    // --- TRAJANJE (free kraće, da ne “pojedu” dan) ---
                    $duration = match ($t) {
                        'Culture&Sightseeing' => rand(45, 90),
                        'Nature&Adventure'    => rand(60, 100),
                        default               => rand(60, 180),
                    };
                    Activity::factory()
                    ->typed($t)
                    ->forLocation($loc)
                    ->state([
                        // sufiks #1..#5 da se nazivi razlikuju vizuelno
                        'name'                => "$t in $loc #" . ($i + 1),
                        'image_url'           => $img,
                        'transport_mode'      => null,
                        'accommodation_class' => null,
                        'price'               => $price,
                        'duration'            => $duration,
                    ])->create();
                    }
            
            }
        }
}
}
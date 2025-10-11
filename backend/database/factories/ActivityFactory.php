<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Activity;

class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    private array $types = [
        'Transport','Accommodation','Food&Drink','Culture&Sightseeing',
        'Shopping&Souvenirs','Nature&Adventure','Relaxation&Wellness'
    ];

    private array $transportModes = [
        'airplane','train','bus','car'
    ];
   
    private array $accClasses = [
        'hostel','guesthouse','budget_hotel','standard_hotel','boutique_hotel',
        'luxury_hotel','resort','apartment','bed_and_breakfast','villa',
        'mountain_lodge','camping','glamping'
    ];

    private array $locations = ['Prague','Budapest','Amsterdam','Lisbon','Valencia'];
    private array $startLocations = ['Belgrade','Ljubljana','Zagreb','Sarajevo','Novi Sad','Niš'];


    public function definition(): array
    {
         $type = $this->faker->randomElement($this->types);

        $prefsAll = Activity::availablePreferenceTypes();
        shuffle($prefsAll);
        $prefs = array_slice($prefsAll, 0, $this->faker->numberBetween(2, min(4, count($prefsAll))));
        if ($type === 'Transport' || $type === 'Accommodation') {
            $prefs[] = 'comfortable_travel';
            $prefs = array_values(array_unique($prefs));
        }

        $isTransport     = $type === 'Transport';
        $isAccommodation = $type === 'Accommodation';

        return [
            'type'               => $type,

            // Lokacija: SAMO ako NIJE Transport. Za Transport ostavi null.
            // (Seeder/helper posle eksplicitno postavlja ->forLocation($dest).)
            'location'           => $isTransport
                                    ? null
                                    : $this->faker->randomElement($this->locations),

            // Transport mode: samo za Transport, inače null
            'transport_mode'     => $isTransport
                                    ? $this->faker->randomElement($this->transportModes)
                                    : null,

            // Accommodation class: samo za Accommodation, inače null
            'accommodation_class'=> $isAccommodation
                                    ? $this->faker->randomElement($this->accClasses)
                                    : null,

            // start_location NE diramo u definition(); postavlja ga helper transport()
            // 'start_location'  => null,

            'name'               => $this->faker->sentence(3),
            'price'              => $this->faker->numberBetween(15, 200),
            'duration'           => $this->faker->numberBetween(60, 480), // min
            'preference_types'   => $prefs,
        ];
    }
    

    // helperi
    public function forLocation(string $loc) { return $this->state(['location' => $loc]); } 
    public function transport(string $mode, ?string $startLoc = null) {
        return $this->state(function () use ($mode, $startLoc) {
            return [
                'type' => 'Transport',
                'transport_mode' => $mode,
                'start_location' => $startLoc ?: $this->faker->randomElement($this->startLocations),
                'duration' => $this->faker->numberBetween(60, 480),
            ];
        });
    }
    public function accommodation(string $cls){ return $this->state(['type' => 'Accommodation','accommodation_class' => $cls,'duration' => 24*60]); }
    public function typed(string $t)         { return $this->state(['type' => $t]); }
}

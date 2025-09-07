<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Activity;

class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    private array $types = [
        'Transport','Accommodation','Food&Drink','Culture&Sightseeing',
        'Shopping&Souvenirs','Nature&Adventure','Relaxation&Wellness',
        'Family-Friendly','Educational&Volunteering','Entertainment&Leisure','other'
    ];

    private array $transportModes = ['airplane','train','car','bus','ferry','cruise ship'];
    private array $accClasses = [
        'hostel','guesthouse','budget_hotel','standard_hotel','boutique_hotel','luxury_hotel',
        'resort','apartment','bed_and_breakfast','villa','mountain_lodge','camping','glamping'
    ];

    public function definition(): array
    {
        $type = $this->faker->randomElement($this->types);

        // random skup preferencija iz Activity::availablePreferenceTypes()
        $prefsAll = Activity::availablePreferenceTypes(); 
        shuffle($prefsAll);
        $prefs = array_slice($prefsAll, 0, $this->faker->numberBetween(1, min(4, count($prefsAll))));

        $data = [
            'type'             => $type,
            'name'             => $this->faker->words(3, true),
            'price'            => $this->faker->randomFloat(2, 5, 300),
            'duration'         => $this->faker->numberBetween(30, 240), // min
            'location'         => $this->faker->randomElement(['Prague','Vienna','Budapest','Belgrade','Zagreb']),
            'content'          => $this->faker->optional()->sentence(12),
            'preference_types' => $prefs,
        ];

        if ($type === 'Transport') {
            $data['transport_mode'] = $this->faker->randomElement($this->transportModes);
            $data['duration'] = $this->faker->numberBetween(60, 240);
            $data['name'] = "Transport ".$this->faker->randomElement(['From','']) ." " . $data['location'];
        } elseif ($type === 'Accommodation') {
            $data['accommodation_class'] = $this->faker->randomElement($this->accClasses);
            $data['duration'] = $this->faker->numberBetween(60, 120);
            $data['name'] = "Accommodation in ".$data['location'];
        }

        return $data;
    }

    public function forLocation(string $location): self
    {
        return $this->state(fn() => ['location' => $location]);
    }

    public function transport(string $mode, string $location): self
    {
        return $this->state(fn() => [
            'type' => 'Transport',
            'transport_mode' => $mode,
            'location' => $location,
            'duration' => 120,
            'name' => "Transport {$location}",
        ]);
    }

    public function accommodation(string $class, string $location): self
    {
        return $this->state(fn() => [
            'type' => 'Accommodation',
            'accommodation_class' => $class,
            'location' => $location,
            'duration' => 90,
            'name' => "Accommodation in {$location}",
        ]);
    }
}

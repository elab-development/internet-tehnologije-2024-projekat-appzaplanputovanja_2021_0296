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

        $imgByType = [
        'Culture&Sightseeing'      => 'https://images.unsplash.com/photo-1505761671935-60b3a7427bad',
        'Shopping&Souvenirs'       => 'https://images.unsplash.com/photo-1521335629791-ce4aec67dd53',
        'Relaxation&Wellness'      => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e',
        'Educational&Volunteering' => 'https://images.unsplash.com/photo-1513258496099-48168024aec0',
        'Nature&Adventure'         => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470',
        'Food&Drink'               => 'https://images.unsplash.com/photo-1478145046317-39f10e56b5e9',
        'Entertainment&Leisure'    => 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d',
        'Family-Friendly'          => 'https://images.unsplash.com/photo-1519681393784-d120267933ba',
        'Accommodation'            => 'https://images.unsplash.com/photo-1560066984-138dadb4c035',
        'Transport'                => 'https://images.unsplash.com/photo-1501706362039-c06b2d715385',
        'other'                    => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee',
        ];
        $data = [
            'type'             => $type,
            'name'             => $this->faker->words(3, true),
            'price'            => $this->faker->randomFloat(2, 5, 300),
            'duration'         => $this->faker->numberBetween(30, 240), // min
            'location'         => $this->faker->randomElement(['Prague','Vienna','Budapest','Belgrade','Zagreb']),
            'content'          => $this->faker->optional()->sentence(12),
            'preference_types' => $prefs,
            'image_url'        => $imgByType[$type] ?? $imgByType['other'],
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

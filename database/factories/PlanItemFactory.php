<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\PlanItem;

class PlanItemFactory extends Factory //u seeder-u se pravi logikom
{
    protected $model = PlanItem::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'time_from' => now(),
            'time_to'   => now()->addHour(),
            'amount'    => $this->faker->randomFloat(2, 10, 500),
        ];
    }
}

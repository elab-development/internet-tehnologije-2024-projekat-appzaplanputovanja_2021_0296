<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'price',
        'duration',
        'location',
        'content', // Updated from 'description' to 'content'
    ];

    // Define the relationship with PlanItem model
    public function planItems()
    {
        return $this->hasMany(PlanItem::class); // 1 activity can have many plan items
    }

    public static function availablePreferenceTypes(): array
    {
        return [
            'travel_with_children',
            'enjoy_nature',
            'love_food_and_drink',
            'want_to_relax',
            'want_culture',
            'seek_fun',
            'adventurous',
            'avoid_crowds',
            'comfortable_travel',
            'cafe_sitting',
            'shopping',
            'want_to_learn',
            'active_vacation',
            'research_of_tradition',
        ];
    }
    protected $casts = [
        'preference_types' => 'array', // Ensure preference_types is cast to an array
    ];


}

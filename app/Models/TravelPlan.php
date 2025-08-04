<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TravelPlan extends Model
{
    use HasFactory; #for the test

    protected $fillable = [
        'start_location',
        'destination',
        'start_date',
        'end_date',
        'preferences',
        'budget',
        'passenger_count',
    ];

    //Get the user that owns the travel plan.
    public function user()
    {
        return $this->belongsTo(User::class); //1 travel plan belongs to 1 user  
    }

    //Get the plan items associated with the travel plan.
    public function planItems()
    {
        return $this->hasMany(PlanItem::class); //1 travel plan can have many plan items
    }


    public static function availablePreferences()
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
}

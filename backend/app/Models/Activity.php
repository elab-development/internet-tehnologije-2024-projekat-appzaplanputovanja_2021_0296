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
        'preference_types', 
        'transport_mode',
        'accommodation_class',
        'image_url',
        'start_location' 
    ];

    // Define the relationship with PlanItem model
    public function planItems()
    {
        return $this->hasMany(PlanItem::class); // 1 activity can have many plan items
    }

    public static function availableTypes(): array {
        return [
            'Transport','Accommodation','Food&Drink','Culture&Sightseeing',
            'Shopping&Souvenirs','Nature&Adventure','Relaxation&Wellness',
            'Family-Friendly','Educational&Volunteering','Entertainment&Leisure','other'
        ];
    }

    public static function availableTransportModes(): array {
        return ['airplane','train','car','bus'];
    }

    public static function availableAccommodationClasses(): array {
        return [
            'hostel','guesthouse','budget_hotel','standard_hotel','boutique_hotel',
            'luxury_hotel','resort','apartment','bed_and_breakfast','villa',
            'mountain_lodge','camping','glamping'
        ];
    }

    public static function availablePreferenceTypes(): array {
        return [
            'travel_with_children','enjoy_nature','love_food_and_drink','want_to_relax',
            'want_culture','seek_fun','adventurous','avoid_crowds','comfortable_travel',
            'cafe_sitting','shopping','want_to_learn','active_vacation','research_of_tradition'
        ];
    }

    public static function availableStartLocations(): array {
        return ['Belgrade','Ljubljana','Zagreb','Sarajevo','Novi Sad','Niš'];
    }

    public static function availableLocations(): array {
        return ['Prague','Budapest','Amsterdam','Lisbon','Valencia'];
    }

    protected $casts = [
        'preference_types' => 'array', // Ensure preference_types is cast to an array
    ];


    public function scopeTransportRoute($q, string $mode, string $from, string $to) {
        return $q->where('type','Transport')
                ->where('transport_mode',$mode)
                ->where('start_location',$from)
                ->where('location',$to);
    }
}

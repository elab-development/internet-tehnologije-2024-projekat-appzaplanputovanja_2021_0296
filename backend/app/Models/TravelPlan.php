<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TravelPlan extends Model
{
    use HasFactory; #for the test

    protected $fillable = [
        'user_id',
        'start_location',
        'destination',
        'start_date',
        'end_date',
        'preferences',
        'budget',
        'passenger_count',
        'transport_mode',
        'accommodation_class',
        'total_cost',
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

    protected $casts = [
        'preferences' => 'array', // Ensure preferences is cast to an array
    ];
}

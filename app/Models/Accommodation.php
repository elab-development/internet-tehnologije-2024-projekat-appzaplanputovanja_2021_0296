<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\TravelPlan;
use App\Models\Review;

class Accommodation extends Model
{
    use HasFactory; // for the test

    protected $fillable = [
        'travel_plan_id',
        'name',
        'location',
        'country',
        'email',
        'price_per_night',
        'number_of_nights',
        'passenger_count',
        'total_price',
    ];
    public function travelPlans()
    {
        return $this->belongsTo(TravelPlan::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }




}

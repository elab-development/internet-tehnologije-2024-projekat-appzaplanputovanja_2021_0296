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
        'name',
        'address',
        'city',
        'zip_code',
        'country',
        'email',
        'price_per_night',
        'capacity',
    ];
    public function travelPlans()
    {
        return $this->hasMany(TravelPlan::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }




}

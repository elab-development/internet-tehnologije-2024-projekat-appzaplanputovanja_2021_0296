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
        'location',
        'country',
        'email',
        'price_per_night',
    ];
    public function travelPlans()
    {
        return $this->belongsToMany(TravelPlan::class)
                    ->withPivot('check_in', 'check_out')
                    ->withTimestamps();

    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }




}

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
}

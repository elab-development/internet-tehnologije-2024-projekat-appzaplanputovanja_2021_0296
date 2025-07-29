<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

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
        'total_cost',
    ];

    /**
     * Get the user that owns the travel plan.
     * 1 travel plan belongs to 1 user
     */
    public function user()
    {
        return $this->belongsTo(User::class);     
    }
        

}

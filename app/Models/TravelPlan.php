<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Accommodation;
use App\Models\PlanItem;
use App\Models\Ticket;

class TravelPlan extends Model
{
    use HasFactory; #for the test

    protected $fillable = [  
        'user_id',
        'start_location',
        'destination',
        'passenger_count',
        'start_date',
        'end_date',
        'preferences',
        'budget',
        'total_cost',
    ];

    //Get the user that owns the travel plan.
    public function user()
    {
        return $this->belongsTo(User::class);   //1 travel plan belongs to 1 user  
    }

    /**public function transport()
    {
        return $this->hasOne(Transport::class); // 1 travel plan can have one transport (e.g., flight, train, etc.)
    }
**/
   
    public function activities()
    {
        return $this->hasMany(PlanItem::class); // 1 travel plan can have many plan items (1 plan item represents one activity)
    }
   
    public function tickets()
    {
       return $this->hasMany(Ticket::class); // 1 travel plan can have many tickets (e.g., for different modes of transport)
    }
    public function accommodation()
    {
        return $this->hasOne(Accommodation::class); // 1 travel plan can have one accommodation (e.g., hotel, Airbnb, etc.)
    }



        

}

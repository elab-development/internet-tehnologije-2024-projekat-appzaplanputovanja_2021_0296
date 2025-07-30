<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\TravelPlan;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'travel_plan_id',
        'transport_type',
        'departure_city',
        'arrival_city',
        'departure_time',
        'price',
        'passenger_count',
    ];
    public function travelPlan()
    {
        return $this->belongsTo(TravelPlan::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'time_from',
        'time_to',
        'amount',
        'name', // Added name field
    ];

    public function travelPlan()
    {
        return $this->belongsTo(TravelPlan::class);
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Activity;
use App\Models\TravelPlan;

class PlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'name',
        'time_from',
        'time_to',
        'amount',
        'activity_id',
        'travel_plan_id',
    ];
    public function travelPlan() {
    return $this->belongsTo(TravelPlan::class);
    }

    public function activity() {
        return $this->belongsTo(Activity::class);
    }
}

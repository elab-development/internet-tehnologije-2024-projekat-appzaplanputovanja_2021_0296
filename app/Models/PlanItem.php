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
        'name',
        'time_from',
        'time_to',
        'amount',
        'activity_id',
        'travel_plan_id',
    ];
    public function travelPlan() {
        return $this->belongsTo(TravelPlan::class);  // 1 plan item belongs to one travel plan
                                                     //svaka stavka plana se veže za jedan konkretan plan putovanja
    }

    public function activity() {
        return $this->belongsTo(Activity::class); // 1 plan item belongs to one activity
                                                     //svaka stavka predstavlja jednu aktivnost
    }
}

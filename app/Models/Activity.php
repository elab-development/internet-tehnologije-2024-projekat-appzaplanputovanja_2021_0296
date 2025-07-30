<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\PlanItem;

class Activity extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'price',
        'duration',
        'description',
    ];
    
    public function planItems()
    {
        return $this->hasMany(PlanItem::class);
    }

}

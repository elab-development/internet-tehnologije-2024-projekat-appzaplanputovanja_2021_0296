<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'price',
        'duration',
        'location',
        'content', // Updated from 'description' to 'content'
    ];

    // Define the relationship with PlanItem model
    public function planItems()
    {
        return $this->hasMany(PlanItem::class); // 1 activity can have many plan items
    }
}

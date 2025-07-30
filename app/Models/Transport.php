<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transport extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'departure',
        'destination',
        'departure_time',
        'arrival_time',
        'ticket_price',
    ];

    /**
     * Get the travel plans that include this transport.
     * 1 transport can be part of multiple travel plans
      **/

}

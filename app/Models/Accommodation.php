<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Accommodation extends Model
{
    use HasFactory; // for the test

    protected $fillable = [
        'name',
        'address',
        'city',
        //'state',
        'zip_code',
        'country',
        //'phone_number',
        'email',
        //'website',
        //'description',
        'price_per_night',
        //'amenities',
    ];



}

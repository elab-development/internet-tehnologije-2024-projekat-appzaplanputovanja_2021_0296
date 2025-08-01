<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Accommodation;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'accommodation_id',
        'content',
        'rating',
        'date',
    ];
    /**
     * Get the user that owns the review.
     * 1 review belongs to 1 user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    /**
     * Get the accommodation that the review is for.
     * 1 review belongs to 1 accommodation
     */
    public function accommodation()
    {
        return $this->belongsTo(Accommodation::class);
    }
}

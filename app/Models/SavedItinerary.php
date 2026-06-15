<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedItinerary extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'days',
        'interests',
        'budget_type',
        'itinerary_data',
        'total_destinations',
        'estimated_budget',
    ];

    protected $casts = [
        'interests' => 'array',
        'itinerary_data' => 'array',
    ];

    // Relasi ke User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
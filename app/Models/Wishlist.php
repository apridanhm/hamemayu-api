<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends Model
{
    protected $fillable = [
        'user_id',
        'content_id',
        'notes',
        'visited',
        'priority',
    ];

    protected $casts = [
        'visited' => 'boolean',
        'priority' => 'integer',
    ];

    // Relasi: 1 Wishlist dimiliki oleh 1 User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relasi: 1 Wishlist berisi 1 Content (Destinasi)
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'type', 'order'];

    protected $casts = [
        'order' => 'integer',
    ];

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }

    // Helper: hanya hitung konten yang published
    public function publishedContents(): HasMany
    {
        return $this->contents()->where('status', 'published');
    }
}
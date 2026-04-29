<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            // whenLoaded mencegah N+1 query kalau category nggak di-eager load
            'category' => new CategoryResource($this->whenLoaded('category')),
            'location' => $this->lat && $this->lng ? [
                'lat' => $this->lat,
                'lng' => $this->lng,
            ] : null,
            'info' => [
                'opening_hours' => $this->opening_hours,
                'ticket_price' => $this->ticket_price,
            ],
            'is_featured' => $this->is_featured,
            'cover_image' => $this->cover_image ?? asset('images/placeholder.jpg'),
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
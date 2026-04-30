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

            'virtual_tour' => $this->street_view_id && $this->lat && $this->lng 
            ? [
                'panorama_id' => $this->street_view_id,
                'embed_url' => "https://www.google.com/maps/embed?pb=!4m14!1m13!4m12!1m3!1d3953!2d" . number_format($this->lng, 6) . "!3d" . number_format($this->lat, 6) . "!2m1!1s{$this->street_view_id}!3m8!1m2!1d" . number_format($this->lat, 6) . "!2d" . number_format($this->lng, 6) . "!3m4!1s2!1s0!8m2!3d" . number_format($this->lat, 6) . "!4d" . number_format($this->lng, 6),
                'google_maps_url' => $this->google_maps_url,
            ] 
            : null,
        ];
    }
}
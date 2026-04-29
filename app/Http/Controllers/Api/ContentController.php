<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContentResource;
use App\Models\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ContentController extends Controller
{
    // GET /api/v1/contents?category=sejarah&search=prambanan
    public function index(Request $request)
    {
        $query = Content::query()->published()->with('category');

        if ($request->has('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
        }
        if ($request->has('search')) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        $cacheKey = 'api_contents_' . md5(json_encode($request->only(['category', 'search', 'page'])));

        return Cache::remember($cacheKey, 3600, function () use ($query) {
            return ContentResource::collection($query->latest()->paginate(12));
        });
    }

    // GET /api/v1/contents/{slug}
    public function show($slug)
    {
        $content = Cache::remember("content_{$slug}", 3600, function () use ($slug) {
            return Content::published()->with('category')->where('slug', $slug)->firstOrFail();
        });

        return new ContentResource($content);
    }

    // GET /api/v1/contents/featured (untuk hero section)
    public function featured()
    {
        return Cache::remember('api_featured', 3600, function () {
            return ContentResource::collection(
                Content::published()->featured()->with('category')->limit(4)->get()
            );
        });
    }

    // GET /api/v1/map-markers (khusus Leaflet/Peta, ringan)
    public function mapMarkers()
    {
        return Cache::remember('api_map_markers', 3600, function () {
            return Content::published()
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->with('category') // Eager load category
                ->get(['id', 'title', 'slug', 'lat', 'lng', 'category_id'])
                ->map(fn($item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'lat' => $item->lat,
                    'lng' => $item->lng,
                    'category' => $item->category?->name ?? 'Umum', // Null-safe
                ]);
        });
    }
}
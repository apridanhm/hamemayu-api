<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index()
    {
        // Cache 1 jam biar response API cepat & hemat DB
        return Cache::remember('api_categories', 3600, function () {
            return CategoryResource::collection(
                Category::orderBy('order')->get()
            );
        });
    }
}
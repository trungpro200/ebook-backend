<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use App\Http\Resources\CategoryResource;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $bookQuery = fn () => Book::query()
            ->with(['author:id,name', 'category:id,name,description'])
            ->withCount('chapters');

        $newBooks = $bookQuery()->latest()->limit(8)->get();
        $popularBooks = $bookQuery()
            ->orderByDesc('view_count')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();
        $categories = Category::query()
            ->withCount('books')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => [
                'new_books' => BookResource::collection($newBooks)->resolve(),
                'popular_books' => BookResource::collection($popularBooks)->resolve(),
                'categories' => CategoryResource::collection($categories)->resolve(),
            ],
        ]);
    }
}

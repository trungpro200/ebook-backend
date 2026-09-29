<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookIndexRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookController extends Controller
{
    public function index(BookIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $books = Book::query()
            ->with(['category:id,name,description', 'author:id,name'])
            ->withCount('chapters')
            ->when(
                $validated['category_id'] ?? null,
                fn ($query, int $categoryId) => $query->where('category_id', $categoryId),
            )
            ->when(
                ($validated['sort'] ?? 'newest') === 'popular',
                fn ($query) => $query->orderByDesc('view_count')->orderByDesc('created_at'),
                fn ($query) => $query->orderByDesc('created_at'),
            )
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return BookResource::collection($books);
    }

    public function show(Book $book): BookResource
    {
        $book->load([
            'category:id,name,description',
            'author:id,name',
            'chapters' => fn ($query) => $query
                ->select(['id', 'book_id', 'title', 'chapter_number'])
                ->orderBy('chapter_number'),
        ])->loadCount('chapters');

        return new BookResource($book);
    }

    /**
     * POST /api/books
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],

            'author_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'cover' => [
                'nullable',
                'string',
                'max:255',
            ],

            'language' => [
                'nullable',
                'string',
                'max:50',
            ],

            'status' => [
                'nullable',
                'string',
                'max:50',
            ],

            'view_count' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);

        $book = Book::create($validated);

        $book->load(['category', 'author']);

        return response()->json([
            'success' => true,
            'message' => 'Book created successfully.',
            'data' => $book,
        ], 201);
    }

    /**
     * PUT /api/books/{book}
     */
    public function update(
        Request $request,
        Book $book
    ): JsonResponse {
        $validated = $request->validate([
            'category_id' => [
                'sometimes',
                'integer',
                'exists:categories,id',
            ],

            'author_id' => [
                'sometimes',
                'integer',
                'exists:users,id',
            ],

            'title' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'cover' => [
                'nullable',
                'string',
                'max:255',
            ],

            'language' => [
                'nullable',
                'string',
                'max:50',
            ],

            'status' => [
                'nullable',
                'string',
                'max:50',
            ],

            'view_count' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);

        $book->update($validated);

        $book->load(['category', 'author']);

        return response()->json([
            'success' => true,
            'message' => 'Book updated successfully.',
            'data' => $book,
        ]);
    }

    /**
     * DELETE /api/books/{book}
     */
    public function destroy(Book $book): JsonResponse
    {
        $book->delete();

        return response()->json([
            'success' => true,
            'message' => 'Book deleted successfully.',
        ]);
    }
}

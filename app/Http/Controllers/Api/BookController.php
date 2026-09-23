<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    /**
     * GET /api/books
     */
    public function index(): JsonResponse
    {
        $books = Book::with(['category', 'author'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $books,
        ]);
    }

    /**
     * GET /api/books/{book}
     */
    public function show(Book $book): JsonResponse
    {
        $book->load(['category', 'author']);

        return response()->json([
            'success' => true,
            'data' => $book,
        ]);
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Bookmark;
use App\Models\Chapter;
use App\Models\Favorite;
use App\Models\ReadingProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalLibraryController extends Controller
{
    public function progressIndex(Request $request): JsonResponse
    {
        return response()->json(['data' => ReadingProgress::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_read_at')
            ->get(['book_id', 'chapter_id', 'progress', 'last_read_at'])]);
    }

    public function saveProgress(Request $request, Book $book): JsonResponse
    {
        $validated = $request->validate([
            'chapter_id' => ['required', 'integer', 'exists:chapters,id'],
            'progress' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        abort_unless(Chapter::where('book_id', $book->id)->whereKey($validated['chapter_id'])->exists(), 422, 'Chương không thuộc sách này.');

        $progress = ReadingProgress::updateOrCreate(
            ['user_id' => $request->user()->id, 'book_id' => $book->id],
            ['chapter_id' => $validated['chapter_id'], 'progress' => $validated['progress'], 'last_read_at' => now()],
        );

        return response()->json(['data' => $progress], 200);
    }

    public function favorites(Request $request): JsonResponse
    {
        return response()->json(['data' => Favorite::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->pluck('book_id')]);
    }

    public function addFavorite(Request $request, Book $book): JsonResponse
    {
        Favorite::firstOrCreate(['user_id' => $request->user()->id, 'book_id' => $book->id]);

        return response()->json(['book_id' => $book->id], 201);
    }

    public function removeFavorite(Request $request, Book $book): JsonResponse
    {
        Favorite::where('user_id', $request->user()->id)->where('book_id', $book->id)->delete();

        return response()->noContent();
    }

    public function bookmarks(Request $request): JsonResponse
    {
        return response()->json(['data' => Bookmark::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get(['id', 'book_id', 'chapter_id', 'position', 'percent', 'quote', 'note', 'created_at'])]);
    }

    public function saveBookmark(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'chapter_id' => ['required', 'integer', 'exists:chapters,id'],
            'position' => ['required', 'integer', 'min:0'],
            'percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'quote' => ['required', 'string', 'max:20000'],
            'note' => ['nullable', 'string', 'max:240'],
        ]);
        abort_unless(Chapter::where('book_id', $validated['book_id'])->whereKey($validated['chapter_id'])->exists(), 422, 'Chương không thuộc sách này.');

        $bookmark = Bookmark::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'chapter_id' => $validated['chapter_id'],
                'position' => $validated['position'],
            ],
            $validated,
        );

        return response()->json(['data' => $bookmark], 201);
    }

    public function removeBookmark(Request $request, Bookmark $bookmark): JsonResponse
    {
        abort_unless($bookmark->user_id === $request->user()->id, 404);
        $bookmark->delete();

        return response()->noContent();
    }
}

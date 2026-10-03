<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use Illuminate\Http\JsonResponse;

class ChapterController extends Controller
{
    public function show(Chapter $chapter): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $chapter->id,
                'book_id' => $chapter->book_id,
                'number' => $chapter->chapter_number,
                'title' => $chapter->title,
                'content' => $chapter->content,
            ],
        ]);
    }
}

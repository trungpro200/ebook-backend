<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateChapterAudio;
use App\Models\Chapter;
use App\Services\ChapterAudio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChapterAudioController extends Controller
{
    public function store(Request $request, Chapter $chapter, ChapterAudio $audio): JsonResponse
    {
        if (! $this->supports($chapter)) {
            return response()->json(['message' => 'Giọng đọc hiện chỉ hỗ trợ sách tiếng Anh.'], 422);
        }

        $segments = $audio->segments($chapter);
        $input = $request->validate(['index' => ['sometimes', 'integer', 'min:0']]);
        $index = $input['index'] ?? 0;
        if ($segments !== [] && $index >= count($segments)) {
            return response()->json(['message' => 'Đoạn đọc không tồn tại.'], 422);
        }

        if (! $audio->exists($chapter) && ! $this->allSegmentsReady($chapter, $audio)) {
            Cache::put($audio->requestKey($chapter), $index, now()->addHour());
            if (Cache::get($audio->statusKey($chapter)) === 'failed') {
                Cache::forget($audio->statusKey($chapter));
            }

            $this->queueIfNeeded($chapter, $audio, $segments);
        }

        return $this->status($chapter, $audio);
    }

    public function status(Chapter $chapter, ChapterAudio $audio): JsonResponse
    {
        if (! $this->supports($chapter)) {
            return response()->json(['message' => 'Giọng đọc hiện chỉ hỗ trợ sách tiếng Anh.'], 422);
        }

        if ($audio->exists($chapter)) {
            return response()->json(['data' => [
                'status' => 'ready',
                'audio_url' => route('chapters.audio.file', $chapter),
                'ready_count' => 1,
                'total_count' => 1,
                'segments' => [[
                    'index' => 0,
                    'text' => $chapter->content,
                    'audio_url' => route('chapters.audio.file', $chapter),
                    'duration' => null,
                    'word_starts' => null,
                    'timing_quality' => 'estimated',
                ]],
            ]]);
        }

        $segments = array_map(function (array $segment) use ($chapter, $audio): array {
            $ready = $audio->cachedFileExists($chapter, $segment['key'], 'mp3');
            $metadataPath = $audio->segmentMetadataPath($chapter, $segment['key']);
            $metadata = $ready && $audio->cachedFileExists($chapter, $segment['key'], 'json')
                ? json_decode(Storage::disk('local')->get($metadataPath), true)
                : null;
            $metadata = is_array($metadata) ? $metadata : [];

            return [
                'index' => $segment['index'],
                'text' => $segment['text'],
                'audio_url' => $ready ? route('chapters.audio.segment', [$chapter, $segment['index']]) : null,
                'duration' => $metadata['duration'] ?? null,
                'word_starts' => is_array($metadata['word_starts'] ?? null) ? $metadata['word_starts'] : null,
                'timing_quality' => $metadata['timing_quality'] ?? 'estimated',
            ];
        }, $audio->segments($chapter));
        $readyCount = count(array_filter($segments, fn (array $segment): bool => $segment['audio_url'] !== null));
        $totalCount = count($segments);
        $data = [
            'status' => $readyCount === $totalCount ? 'ready' : 'processing',
            'ready_count' => $readyCount,
            'total_count' => $totalCount,
            'segments' => $segments,
        ];

        if ($readyCount === $totalCount) {
            return response()->json(['data' => $data]);
        }

        $this->queueIfNeeded($chapter, $audio, $audio->segments($chapter));
        $status = Cache::get($audio->statusKey($chapter));

        return match ($status) {
            'queued', 'processing' => response()->json(['data' => [...$data, 'status' => $status]], 202),
            'failed' => response()->json(['data' => [...$data, 'status' => 'failed'], 'message' => 'Không thể tạo âm thanh. Hãy thử lại.'], 503),
            default => response()->json(['data' => [...$data, 'status' => 'paused']], 200),
        };
    }

    public function segment(Chapter $chapter, int $index, ChapterAudio $audio): BinaryFileResponse
    {
        abort_unless($this->supports($chapter), 404);
        $segments = $audio->segments($chapter);
        abort_unless(isset($segments[$index]), 404);

        $path = $audio->segmentPath($chapter, $segments[$index]['key']);
        abort_unless($audio->cachedFileExists($chapter, $segments[$index]['key'], 'mp3'), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function file(Chapter $chapter, ChapterAudio $audio): BinaryFileResponse
    {
        abort_unless($this->supports($chapter) && $audio->exists($chapter), 404);

        return response()->file(Storage::disk('local')->path($audio->path($chapter)), [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function supports(Chapter $chapter): bool
    {
        return preg_match('/^en(?:-|$)/i', (string) $chapter->book->language) === 1
            && trim($chapter->content) !== '';
    }

    private function allSegmentsReady(Chapter $chapter, ChapterAudio $audio): bool
    {
        $segments = $audio->segments($chapter);

        return $segments !== [] && array_all($segments, fn (array $segment): bool => $audio->cachedFileExists($chapter, $segment['key'], 'mp3'));
    }

    /** @param array<int, array{index: int, text: string, key: string}> $segments */
    private function queueIfNeeded(Chapter $chapter, ChapterAudio $audio, array $segments): void
    {
        $requested = Cache::get($audio->requestKey($chapter));
        if ($requested === null || Cache::get($audio->statusKey($chapter)) !== null) {
            return;
        }

        $window = array_slice($segments, (int) $requested, ChapterAudio::PREFETCH_COUNT);
        if ($window === [] || array_all($window, fn (array $segment): bool => $audio->cachedFileExists($chapter, $segment['key'], 'mp3'))) {
            return;
        }

        if (Cache::add($audio->statusKey($chapter), 'queued', now()->addHour())) {
            GenerateChapterAudio::dispatch($chapter->id, $audio->key($chapter));
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\Chapter;
use App\Services\ChapterAudio;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateChapterAudio implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $chapterId, public string $key)
    {
        $this->onConnection('database');
        $this->onQueue('tts');
    }

    public function handle(ChapterAudio $audio): void
    {
        $chapter = Chapter::find($this->chapterId);
        if ($chapter === null || $audio->key($chapter) !== $this->key) {
            Cache::forget('chapter-audio:'.$this->key);

            return;
        }

        if ($audio->exists($chapter)) {
            Cache::forget($audio->statusKey($chapter));

            return;
        }

        $token = (string) config('tts.token');
        if ($token === '') {
            throw new RuntimeException('TTS_SHARED_TOKEN is not configured.');
        }

        Cache::put($audio->statusKey($chapter), 'processing', now()->addHour());

        $segments = $audio->segments($chapter);
        while (true) {
            $start = (int) Cache::get($audio->requestKey($chapter), 0);
            $next = null;
            foreach (array_slice($segments, $start, ChapterAudio::PREFETCH_COUNT) as $segment) {
                if (! $audio->cachedFileExists($chapter, $segment['key'], 'mp3')) {
                    $next = $segment;

                    break;
                }
            }

            if ($next === null) {
                break;
            }

            $path = $audio->segmentPath($chapter, $next['key']);

            Http::withToken($token)
                ->timeout(300)
                ->connectTimeout(10)
                ->post(rtrim((string) config('tts.url'), '/').'/synthesize', [
                    'key' => $next['key'],
                    'directory' => $audio->directory($chapter),
                    'text' => $next['text'],
                    'voice' => $audio->voice($chapter),
                    'language' => $audio->language($chapter),
                ])->throw();

            if (! Storage::disk('local')->exists($path)) {
                throw new RuntimeException('TTS server returned before the segment MP3 was written.');
            }
        }

        Cache::forget($audio->statusKey($chapter));
    }

    public function failed(?Throwable $exception): void
    {
        Cache::put('chapter-audio:'.$this->key, 'failed', now()->addMinutes(5));
    }
}

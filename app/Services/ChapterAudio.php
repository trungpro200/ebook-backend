<?php

namespace App\Services;

use App\Models\Chapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChapterAudio
{
    public const VOICE = 'af_heart';

    public const VIETNAMESE_VOICE = 'huu_dat';

    public const PREFETCH_COUNT = 9;

    private const AUDIO_CACHE_FORMAT_VERSION = 'trailing-period-pause-v1';

    private const MAX_SEGMENT_LENGTH = 350;

    public function key(Chapter $chapter): string
    {
        return hash('sha256', implode("\0", [
            (string) $chapter->id,
            $chapter->content,
            $this->voice($chapter),
            (string) config($this->language($chapter) === 'vi' ? 'tts.vietnamese_model_version' : 'tts.model_version'),
            self::AUDIO_CACHE_FORMAT_VERSION,
        ]));
    }

    public function language(Chapter $chapter): string
    {
        return preg_match('/^vi(?:-|$)/i', (string) $chapter->book->language) === 1 ? 'vi' : 'en';
    }

    public function voice(Chapter $chapter): string
    {
        return $this->language($chapter) === 'vi' ? self::VIETNAMESE_VOICE : self::VOICE;
    }

    public function path(Chapter $chapter): string
    {
        return $this->cachedPath($chapter, $this->key($chapter), 'mp3');
    }

    public function statusKey(Chapter $chapter): string
    {
        return 'chapter-audio:'.$this->key($chapter);
    }

    public function requestKey(Chapter $chapter): string
    {
        return $this->statusKey($chapter).':requested';
    }

    public function exists(Chapter $chapter): bool
    {
        return $this->cachedFileExists($chapter, $this->key($chapter), 'mp3');
    }

    /** @return array<int, array{index: int, text: string, key: string}> */
    public function segments(Chapter $chapter): array
    {
        preg_match_all('/\S+/u', $chapter->content, $matches);
        $segments = [];
        $buffer = '';
        $isVietnamese = $this->language($chapter) === 'vi';
        $maxLength = $isVietnamese ? 260 : self::MAX_SEGMENT_LENGTH;

        foreach ($matches[0] as $word) {
            $bufferLength = $isVietnamese ? mb_strlen($buffer) : strlen($buffer);
            $wordLength = $isVietnamese ? mb_strlen($word) : strlen($word);
            if ($buffer !== '' && $bufferLength + $wordLength + 1 > $maxLength) {
                $segments[] = $buffer;
                $buffer = '';
            }

            $buffer .= ($buffer === '' ? '' : ' ').$word;

            if (($isVietnamese ? mb_strlen($buffer) : strlen($buffer)) >= ($isVietnamese ? 160 : 220)
                && preg_match('/[.!?]["”’]?$/u', $word)) {
                $segments[] = $buffer;
                $buffer = '';
            }
        }

        if ($buffer !== '') {
            $segments[] = $buffer;
        }

        return array_map(fn (string $text, int $index): array => [
            'index' => $index,
            'text' => $text,
            'key' => hash('sha256', $this->key($chapter)."\0".$index."\0".$text),
        ], $segments, array_keys($segments));
    }

    public function directory(Chapter $chapter): string
    {
        $book = $chapter->book;

        return $book->id.'_'.(substr(Str::slug($book->title), 0, 60) ?: 'book').'/'.$chapter->id.'_'.(substr(Str::slug($chapter->title), 0, 60) ?: 'chapter');
    }

    public function segmentPath(Chapter $chapter, string $key): string
    {
        return $this->cachedPath($chapter, $key, 'mp3');
    }

    public function segmentMetadataPath(Chapter $chapter, string $key): string
    {
        return $this->cachedPath($chapter, $key, 'json');
    }

    public function cachedFileExists(Chapter $chapter, string $key, string $extension): bool
    {
        $disk = Storage::disk('local');
        $path = $this->cachedPath($chapter, $key, $extension);
        if ($disk->exists($path)) {
            return true;
        }

        $legacyPath = 'tts/'.$key.'.'.$extension;
        if (! $disk->exists($legacyPath)) {
            return false;
        }

        $disk->makeDirectory(dirname($path));

        return $disk->move($legacyPath, $path) || $disk->exists($path);
    }

    private function cachedPath(Chapter $chapter, string $key, string $extension): string
    {
        return 'tts/'.$this->directory($chapter).'/'.$extension.'/'.$key.'.'.$extension;
    }
}

<?php

namespace App\Services;

use App\Models\Chapter;
use Illuminate\Support\Facades\Storage;

class ChapterAudio
{
    public const VOICE = 'af_heart';

    private const MAX_SEGMENT_LENGTH = 350;

    public function key(Chapter $chapter): string
    {
        return hash('sha256', implode("\0", [
            (string) $chapter->id,
            $chapter->content,
            self::VOICE,
            (string) config('tts.model_version'),
        ]));
    }

    public function path(Chapter $chapter): string
    {
        return 'tts/'.$this->key($chapter).'.mp3';
    }

    public function statusKey(Chapter $chapter): string
    {
        return 'chapter-audio:'.$this->key($chapter);
    }

    public function exists(Chapter $chapter): bool
    {
        return Storage::disk('local')->exists($this->path($chapter));
    }

    /** @return array<int, array{index: int, text: string, key: string}> */
    public function segments(Chapter $chapter): array
    {
        preg_match_all('/\S+/u', $chapter->content, $matches);
        $segments = [];
        $buffer = '';

        foreach ($matches[0] as $word) {
            if ($buffer !== '' && strlen($buffer) + strlen($word) + 1 > self::MAX_SEGMENT_LENGTH) {
                $segments[] = $buffer;
                $buffer = '';
            }

            $buffer .= ($buffer === '' ? '' : ' ').$word;

            if (strlen($buffer) >= 220 && preg_match('/[.!?]["”’]?$/u', $word)) {
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

    public function segmentPath(string $key): string
    {
        return 'tts/'.$key.'.mp3';
    }

    public function segmentMetadataPath(string $key): string
    {
        return 'tts/'.$key.'.json';
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\GenerateChapterAudio;
use App\Models\Book;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\User;
use App\Services\ChapterAudio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChapterAudioTest extends TestCase
{
    use RefreshDatabase;

    private function chapter(string $language = 'en'): Chapter
    {
        $book = Book::create([
            'title' => 'Book',
            'language' => $language,
            'category_id' => Category::create(['name' => 'Fiction'])->id,
            'author_id' => User::factory()->create()->id,
        ]);

        return Chapter::create([
            'book_id' => $book->id,
            'chapter_number' => 1,
            'title' => 'Chapter one',
            'content' => 'This is the chapter text.',
        ]);
    }

    public function test_audio_is_queued_once_then_served_from_private_cache(): void
    {
        Queue::fake();
        Storage::fake('local');
        Cache::flush();
        $chapter = $this->chapter();
        $audio = app(ChapterAudio::class);

        $this->postJson('/api/chapters/'.$chapter->id.'/audio')->assertStatus(202)->assertJsonPath('data.status', 'queued');
        $this->postJson('/api/chapters/'.$chapter->id.'/audio')->assertStatus(202);
        Queue::assertPushed(GenerateChapterAudio::class, 1);
        $this->getJson('/api/chapters/'.$chapter->id.'/audio')->assertStatus(202);
        $this->get('/api/chapters/'.$chapter->id.'/audio/file')->assertNotFound();

        Storage::disk('local')->put($audio->path($chapter), 'ID3 audio bytes');
        $this->getJson('/api/chapters/'.$chapter->id.'/audio')->assertOk()->assertJsonPath('data.status', 'ready');
        $this->get('/api/chapters/'.$chapter->id.'/audio/file')->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->postJson('/api/chapters/'.$chapter->id.'/audio')->assertOk();
        Queue::assertPushed(GenerateChapterAudio::class, 1);

        $chapter->update(['content' => 'The chapter has changed.']);
        $this->postJson('/api/chapters/'.$chapter->id.'/audio')->assertStatus(202);
        Queue::assertPushed(GenerateChapterAudio::class, 2);
    }

    public function test_unsupported_languages_are_rejected(): void
    {
        $chapter = $this->chapter('fr');

        $this->postJson('/api/chapters/'.$chapter->id.'/audio')->assertUnprocessable();
        $this->getJson('/api/chapters/'.$chapter->id.'/audio')->assertUnprocessable();
    }

    public function test_vietnamese_chapter_uses_huu_dat_and_a_separate_cache_key(): void
    {
        Queue::fake();
        Storage::fake('local');
        Cache::flush();
        $chapter = $this->chapter('vi-VN');
        $chapter->update(['content' => str_repeat('Đây là một đoạn văn tiếng Việt để nghe thử. ', 15)]);
        $audio = app(ChapterAudio::class);
        $segments = $audio->segments($chapter);

        $this->assertGreaterThan(1, count($segments));
        foreach ($segments as $segment) {
            $this->assertLessThanOrEqual(260, mb_strlen($segment['text']));
        }

        $this->postJson('/api/chapters/'.$chapter->id.'/audio')->assertAccepted();
        Queue::assertPushed(GenerateChapterAudio::class, 1);

        config(['tts.token' => 'test-secret', 'tts.url' => 'http://127.0.0.1:8765']);
        Http::fake(function (Request $request) {
            Storage::disk('local')->put('tts/'.$request['directory'].'/mp3/'.$request['key'].'.mp3', 'ID3 audio bytes');
            Storage::disk('local')->put('tts/'.$request['directory'].'/json/'.$request['key'].'.json', json_encode([
                'duration' => 4.5,
                'word_starts' => null,
                'timing_quality' => 'estimated',
            ]));

            return Http::response(['status' => 'ready']);
        });
        (new GenerateChapterAudio($chapter->id, $audio->key($chapter)))->handle($audio);

        Http::assertSent(fn (Request $request): bool => $request['language'] === 'vi'
            && $request['voice'] === ChapterAudio::VIETNAMESE_VOICE);
        $this->getJson('/api/chapters/'.$chapter->id.'/audio')->assertOk()
            ->assertJsonPath('data.segments.0.duration', 4.5)
            ->assertJsonPath('data.segments.0.timing_quality', 'estimated');

        $vietnameseKey = $audio->key($chapter);
        $chapter->book->update(['language' => 'en']);
        $this->assertNotSame($vietnameseKey, $audio->key($chapter->fresh()));
    }

    public function test_job_sends_chapter_text_and_confirms_generated_file(): void
    {
        Storage::fake('local');
        Cache::flush();
        config(['tts.token' => 'test-secret', 'tts.url' => 'http://127.0.0.1:8765']);
        $chapter = $this->chapter();
        $chapter->update(['content' => str_repeat('A short sentence for the listener. ', 300)]);
        $audio = app(ChapterAudio::class);
        $segments = $audio->segments($chapter);
        $this->assertGreaterThan(1, count($segments));
        Http::fake(function (Request $request) {
            Storage::disk('local')->put('tts/'.$request['directory'].'/mp3/'.$request['key'].'.mp3', 'ID3 audio bytes');

            return Http::response(['status' => 'ready']);
        });

        (new GenerateChapterAudio($chapter->id, $audio->key($chapter)))->handle($audio);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://127.0.0.1:8765/synthesize'
            && $request->hasHeader('Authorization', 'Bearer test-secret')
            && $request['text'] === $segments[0]['text']
            && $request['directory'] === $audio->directory($chapter)
            && $request['language'] === 'en'
            && $request['voice'] === ChapterAudio::VOICE);
        Http::assertSentCount(ChapterAudio::PREFETCH_COUNT);
        foreach (array_slice($segments, 0, ChapterAudio::PREFETCH_COUNT) as $segment) {
            $this->assertTrue(Storage::disk('local')->exists($audio->segmentPath($chapter, $segment['key'])));
        }

        $this->getJson('/api/chapters/'.$chapter->id.'/audio')->assertOk()
            ->assertJsonPath('data.status', 'paused')
            ->assertJsonPath('data.total_count', count($segments));

        (new GenerateChapterAudio($chapter->id, $audio->key($chapter)))->handle($audio);
        Http::assertSentCount(ChapterAudio::PREFETCH_COUNT);
    }

    public function test_first_generated_segment_is_available_before_chapter_finishes(): void
    {
        Queue::fake();
        Storage::fake('local');
        Cache::flush();
        $chapter = $this->chapter();
        $chapter->update(['content' => str_repeat('A short sentence for the listener. ', 30)]);
        $audio = app(ChapterAudio::class);
        $segments = $audio->segments($chapter);

        $this->postJson('/api/chapters/'.$chapter->id.'/audio')->assertAccepted();
        Storage::disk('local')->put($audio->segmentPath($chapter, $segments[0]['key']), 'ID3 audio bytes');
        Storage::disk('local')->put($audio->segmentMetadataPath($chapter, $segments[0]['key']), json_encode([
            'duration' => 5.2,
            'word_starts' => [0.0, 0.4],
            'timing_quality' => 'phoneme',
        ]));

        $this->getJson('/api/chapters/'.$chapter->id.'/audio')->assertAccepted()
            ->assertJsonPath('data.ready_count', 1)
            ->assertJsonPath('data.total_count', count($segments))
            ->assertJsonPath('data.segments.0.text', $segments[0]['text'])
            ->assertJsonPath('data.segments.0.duration', 5.2)
            ->assertJsonPath('data.segments.0.word_starts.1', 0.4)
            ->assertJsonPath('data.segments.0.timing_quality', 'phoneme');
        $this->get('/api/chapters/'.$chapter->id.'/audio/segments/0')->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->get('/api/chapters/'.$chapter->id.'/audio/segments/1')->assertNotFound();
    }

    public function test_seeking_to_an_uncached_segment_queues_only_its_window(): void
    {
        Queue::fake();
        Storage::fake('local');
        Cache::flush();
        $chapter = $this->chapter();
        $chapter->update(['content' => str_repeat('A short sentence for the listener. ', 300)]);
        $audio = app(ChapterAudio::class);
        $segments = $audio->segments($chapter);
        $index = ChapterAudio::PREFETCH_COUNT + 2;
        $this->assertGreaterThan($index, count($segments));

        $this->postJson('/api/chapters/'.$chapter->id.'/audio', ['index' => $index])
            ->assertAccepted()
            ->assertJsonPath('data.ready_count', 0);
        $this->assertSame($index, Cache::get($audio->requestKey($chapter)));
        Queue::assertPushed(GenerateChapterAudio::class, 1);

        config(['tts.token' => 'test-secret', 'tts.url' => 'http://127.0.0.1:8765']);
        Http::fake(function (Request $request) {
            Storage::disk('local')->put('tts/'.$request['directory'].'/mp3/'.$request['key'].'.mp3', 'ID3 audio bytes');

            return Http::response(['status' => 'ready']);
        });
        (new GenerateChapterAudio($chapter->id, $audio->key($chapter)))->handle($audio);

        Http::assertSentCount(min(ChapterAudio::PREFETCH_COUNT, count($segments) - $index));
        $this->assertFalse(Storage::disk('local')->exists($audio->segmentPath($chapter, $segments[0]['key'])));
        $this->assertTrue(Storage::disk('local')->exists($audio->segmentPath($chapter, $segments[$index]['key'])));
        $this->getJson('/api/chapters/'.$chapter->id.'/audio')->assertOk()->assertJsonPath('data.status', 'paused');
        $this->postJson('/api/chapters/'.$chapter->id.'/audio', ['index' => 0])->assertAccepted();
        Queue::assertPushed(GenerateChapterAudio::class, 2);
    }

    public function test_legacy_flat_files_are_moved_into_book_and_chapter_folders(): void
    {
        Storage::fake('local');
        Cache::flush();
        $chapter = $this->chapter();
        $audio = app(ChapterAudio::class);
        $segment = $audio->segments($chapter)[0];
        Storage::disk('local')->put('tts/'.$segment['key'].'.mp3', 'ID3 audio bytes');
        Storage::disk('local')->put('tts/'.$segment['key'].'.json', json_encode(['duration' => 3.0]));

        $this->getJson('/api/chapters/'.$chapter->id.'/audio')->assertOk()
            ->assertJsonPath('data.segments.0.duration', 3);
        $this->assertTrue(Storage::disk('local')->exists($audio->segmentPath($chapter, $segment['key'])));
        $this->assertTrue(Storage::disk('local')->exists($audio->segmentMetadataPath($chapter, $segment['key'])));
        $this->assertFalse(Storage::disk('local')->exists('tts/'.$segment['key'].'.mp3'));
    }
}

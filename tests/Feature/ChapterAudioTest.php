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

    public function test_non_english_chapters_are_rejected(): void
    {
        $chapter = $this->chapter('vi');

        $this->postJson('/api/chapters/'.$chapter->id.'/audio')->assertUnprocessable();
        $this->getJson('/api/chapters/'.$chapter->id.'/audio')->assertUnprocessable();
    }

    public function test_job_sends_chapter_text_and_confirms_generated_file(): void
    {
        Storage::fake('local');
        Cache::flush();
        config(['tts.token' => 'test-secret', 'tts.url' => 'http://127.0.0.1:8765']);
        $chapter = $this->chapter();
        $chapter->update(['content' => str_repeat('A short sentence for the listener. ', 30)]);
        $audio = app(ChapterAudio::class);
        $segments = $audio->segments($chapter);
        $this->assertGreaterThan(1, count($segments));
        Http::fake(function (Request $request) use ($audio) {
            Storage::disk('local')->put($audio->segmentPath($request['key']), 'ID3 audio bytes');

            return Http::response(['status' => 'ready']);
        });

        (new GenerateChapterAudio($chapter->id, $audio->key($chapter)))->handle($audio);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://127.0.0.1:8765/synthesize'
            && $request->hasHeader('Authorization', 'Bearer test-secret')
            && $request['text'] === $segments[0]['text']
            && $request['voice'] === ChapterAudio::VOICE);
        Http::assertSentCount(count($segments));
        foreach ($segments as $segment) {
            $this->assertTrue(Storage::disk('local')->exists($audio->segmentPath($segment['key'])));
        }

        $this->getJson('/api/chapters/'.$chapter->id.'/audio')->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.total_count', count($segments));

        (new GenerateChapterAudio($chapter->id, $audio->key($chapter)))->handle($audio);
        Http::assertSentCount(count($segments));
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
        Storage::disk('local')->put($audio->segmentPath($segments[0]['key']), 'ID3 audio bytes');
        Storage::disk('local')->put($audio->segmentMetadataPath($segments[0]['key']), json_encode([
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
}

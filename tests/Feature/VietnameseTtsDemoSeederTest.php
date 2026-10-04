<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Services\ChapterAudio;
use Database\Seeders\VietnameseTtsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VietnameseTtsDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_books_are_readable_by_vietnamese_tts_and_seeding_is_idempotent(): void
    {
        $this->seed(VietnameseTtsDemoSeeder::class);
        $this->seed(VietnameseTtsDemoSeeder::class);

        $owner = User::query()->where('email', 'vietnamese-tts-demo@mocthu.invalid')->firstOrFail();
        $this->assertSame(User::ROLE_ADMIN, $owner->role);
        $this->assertDatabaseCount('books', 3);
        $this->assertDatabaseCount('chapters', 6);

        foreach (Book::query()->with('chapters')->get() as $book) {
            $this->assertSame('vi', $book->language);
            $this->assertSame($owner->id, $book->author_id);
            $this->assertSame('Mộc Thư (truyện mẫu)', $book->author_name);
            $this->assertCount(2, $book->chapters);

            foreach ($book->chapters as $chapter) {
                $this->assertSame('vi', app(ChapterAudio::class)->language($chapter));
                $this->assertGreaterThan(3, count(app(ChapterAudio::class)->segments($chapter)));
            }
        }

        $this->getJson('/api/books?q=%5BTh%E1%BB%AD%20TTS%5D')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }
}

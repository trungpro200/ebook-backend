<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalLibraryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_endpoints_require_authentication(): void
    {
        $this->getJson('/api/favorites')->assertUnauthorized();
        $this->getJson('/api/bookmarks')->assertUnauthorized();
        $this->getJson('/api/reading-progress')->assertUnauthorized();
        $this->patchJson('/api/me', ['name' => 'Reader'])->assertUnauthorized();
        $this->patchJson('/api/reader-preferences', ['theme' => 'dark'])->assertUnauthorized();
    }

    public function test_reader_can_add_list_and_remove_favorites_idempotently(): void
    {
        $user = User::factory()->create();
        $book = $this->createBookWithChapter();
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/books/'.$book->id.'/favorite')->assertCreated();
        $this->postJson('/api/books/'.$book->id.'/favorite')->assertOk();
        $this->assertDatabaseCount('favorites', 1);
        $this->getJson('/api/favorites')->assertOk()->assertJsonPath('data.0', $book->id);

        $this->deleteJson('/api/books/'.$book->id.'/favorite')->assertNoContent();
        $this->getJson('/api/favorites')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_reader_can_save_and_load_progress_and_percent_per_book(): void
    {
        $user = User::factory()->create();
        $book = $this->createBookWithChapter();
        $chapter = $book->chapters()->firstOrFail();
        $otherBook = $this->createBookWithChapter();
        $otherChapter = $otherBook->chapters()->firstOrFail();
        $this->actingAs($user, 'sanctum');

        $this->putJson('/api/books/'.$book->id.'/reading-progress', [
            'chapter_id' => $chapter->id,
            'progress' => 42,
        ])->assertOk();
        $this->putJson('/api/books/'.$book->id.'/reading-progress', [
            'chapter_id' => $otherChapter->id,
            'progress' => 80,
        ])->assertUnprocessable();

        $this->getJson('/api/reading-progress')
            ->assertOk()
            ->assertJsonPath('data.0.book_id', $book->id)
            ->assertJsonPath('data.0.chapter_id', $chapter->id)
            ->assertJsonPath('data.0.progress', 42);
        $this->assertDatabaseCount('reading_progress', 1);
    }

    public function test_reader_can_save_list_and_delete_bookmarks_with_quote_and_note(): void
    {
        $reader = User::factory()->create();
        $otherReader = User::factory()->create();
        $book = $this->createBookWithChapter();
        $chapter = $book->chapters()->firstOrFail();
        $this->actingAs($reader, 'sanctum');

        $payload = [
            'book_id' => $book->id,
            'chapter_id' => $chapter->id,
            'position' => 3,
            'percent' => 25,
            'quote' => 'A saved paragraph.',
            'note' => 'Remember this idea.',
        ];
        $created = $this->postJson('/api/bookmarks', $payload)->assertCreated()
            ->assertJsonPath('data.position', 3)
            ->assertJsonPath('data.quote', 'A saved paragraph.')
            ->assertJsonPath('data.note', 'Remember this idea.');
        $bookmarkId = $created->json('data.id');

        $this->postJson('/api/bookmarks', array_merge($payload, ['note' => 'Updated note']))->assertCreated();
        $this->assertDatabaseCount('bookmarks', 1);
        $this->getJson('/api/bookmarks')->assertOk()
            ->assertJsonPath('data.0.id', $bookmarkId)
            ->assertJsonPath('data.0.note', 'Updated note');

        $this->actingAs($otherReader, 'sanctum');
        $this->deleteJson('/api/bookmarks/'.$bookmarkId)->assertNotFound();
        $this->actingAs($reader, 'sanctum');
        $this->deleteJson('/api/bookmarks/'.$bookmarkId)->assertNoContent();
        $this->assertDatabaseCount('bookmarks', 0);
    }

    public function test_reader_can_update_profile_and_persist_reader_preferences(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->patchJson('/api/me', [
            'name' => 'Updated Reader',
            'avatar_url' => 'https://example.com/avatar.png',
        ])->assertOk()
            ->assertJsonPath('user.name', 'Updated Reader')
            ->assertJsonPath('user.avatar_url', 'https://example.com/avatar.png');

        $this->patchJson('/api/reader-preferences', [
            'font_size' => 22,
            'line_height' => 1.8,
            'theme' => 'sepia',
            'speed' => 1.25,
        ])->assertOk()->assertJsonPath('preferences.theme', 'sepia');

        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.name', 'Updated Reader')
            ->assertJsonPath('user.preferences.font_size', 22)
            ->assertJsonPath('user.preferences.line_height', 1.8)
            ->assertJsonPath('user.preferences.theme', 'sepia')
            ->assertJsonPath('user.preferences.speed', 1.25);

        $this->patchJson('/api/reader-preferences', ['theme' => 'neon'])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Reader']);
    }

    private function createBookWithChapter(): Book
    {
        $book = Book::create([
            'title' => 'Test book',
            'category_id' => Category::create(['name' => 'Test category'])->id,
            'author_id' => User::factory()->create()->id,
        ]);
        Chapter::create([
            'book_id' => $book->id,
            'title' => 'Chapter 1',
            'chapter_number' => 1,
            'content' => 'Test chapter content.',
        ]);

        return $book;
    }
}

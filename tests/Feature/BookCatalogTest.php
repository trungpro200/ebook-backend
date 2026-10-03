<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function createBook(array $attributes = [], int $chapters = 0): Book
    {
        $book = Book::create(array_merge([
            'title' => 'Test book',
            'category_id' => Category::create(['name' => 'Technology'])->id,
            'author_id' => User::factory()->create()->id,
        ], $attributes));

        for ($chapterNumber = 1; $chapterNumber <= $chapters; $chapterNumber++) {
            Chapter::create([
                'book_id' => $book->id,
                'title' => 'Chapter '.$chapterNumber,
                'chapter_number' => $chapterNumber,
                'content' => 'Chapter content',
            ]);
        }

        return $book;
    }

    public function test_guests_can_browse_paginated_books_and_filter_by_category(): void
    {
        $technology = Category::create(['name' => 'Technology']);
        $fiction = Category::create(['name' => 'Fiction']);
        $author = User::factory()->create(['name' => 'Book Author']);

        $included = $this->createBook([
            'title' => 'Laravel Handbook',
            'category_id' => $technology->id,
            'author_id' => $author->id,
            'view_count' => 50,
        ], 2);
        $this->createBook(['title' => 'A Novel', 'category_id' => $fiction->id]);

        $response = $this->getJson('/api/books?category_id='.$technology->id.'&per_page=10');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $included->id)
            ->assertJsonPath('data.0.author.name', 'Book Author')
            ->assertJsonPath('data.0.category.name', 'Technology')
            ->assertJsonPath('data.0.chapters_count', 2)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_book_filters_validate_unknown_categories_and_page_size(): void
    {
        $this->getJson('/api/books?category_id=999')->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->getJson('/api/books?per_page=51')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/books?sort=oldest')->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->getJson('/api/books?search_by=description')->assertUnprocessable()->assertJsonValidationErrors('search_by');
    }

    public function test_books_can_be_searched_by_title_or_display_author_across_pages(): void
    {
        $author = User::factory()->create(['name' => 'Import Account']);
        $book = $this->createBook(['title' => 'Pride and Prejudice', 'author_id' => $author->id, 'author_name' => 'Jane Austen']);
        $this->createBook(['title' => 'Another Story', 'author_id' => $author->id, 'author_name' => 'Different Writer']);
        $legacyAuthor = User::factory()->create(['name' => 'Legacy Writer']);
        $legacyBook = $this->createBook(['title' => 'Old Story', 'author_id' => $legacyAuthor->id]);

        $this->getJson('/api/books?q=Pride&search_by=title&per_page=1')
            ->assertOk()->assertJsonPath('data.0.id', $book->id)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/books?q=Austen&search_by=author&per_page=1')
            ->assertOk()->assertJsonPath('data.0.id', $book->id)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/books?q=Import%20Account&search_by=author')
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/books?q=Legacy%20Writer&search_by=author')
            ->assertOk()->assertJsonPath('data.0.id', $legacyBook->id)->assertJsonPath('meta.total', 1);
    }

    public function test_guests_can_read_real_chapter_content(): void
    {
        $book = $this->createBook(chapters: 1);
        $chapter = $book->chapters()->firstOrFail();

        $this->getJson('/api/chapters/'.$chapter->id)
            ->assertOk()->assertJsonPath('data.book_id', $book->id)
            ->assertJsonPath('data.content', 'Chapter content');
    }

    public function test_book_detail_contains_ordered_chapter_summaries(): void
    {
        $book = $this->createBook(chapters: 2);
        Chapter::query()
            ->where('book_id', $book->id)
            ->where('chapter_number', 1)
            ->update(['chapter_number' => 10]);

        $response = $this->getJson('/api/books/'.$book->id);

        $response->assertOk()
            ->assertJsonPath('data.chapters_count', 2)
            ->assertJsonPath('data.chapters.0.number', 2)
            ->assertJsonPath('data.chapters.1.number', 10)
            ->assertJsonMissingPath('data.chapters.0.content');
    }

    public function test_categories_include_book_counts(): void
    {
        $book = $this->createBook();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $book->category_id,
                'name' => 'Technology',
                'books_count' => 1,
            ]);
    }

    public function test_home_returns_new_popular_and_category_sections(): void
    {
        $popular = $this->createBook(['title' => 'Popular book', 'view_count' => 500], 1);
        $this->createBook(['title' => 'New book', 'view_count' => 10]);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('data.popular_books.0.id', $popular->id)
            ->assertJsonCount(2, 'data.new_books')
            ->assertJsonCount(2, 'data.popular_books')
            ->assertJsonCount(2, 'data.categories');
    }
}

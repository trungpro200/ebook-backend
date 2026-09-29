<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Services\StandardEbooksCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class StandardEbooksImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_the_default_import_owner(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response($this->epub(), 200)]);
        config()->set('standard_ebooks.catalog', [$this->definition()]);

        $owner = User::query()->where('email', 'standard-ebooks-import@mocthu.invalid')->firstOrFail();
        $this->assertSame(User::ROLE_ADMIN, $owner->role);

        $this->artisan('books:import-standard-ebooks', ['--limit' => 1])->assertSuccessful();

        $book = Book::query()->firstOrFail();
        $this->assertSame($owner->id, $book->author_id);
        $this->assertSame('Test Author', $book->author_name);
    }

    public function test_command_imports_an_allowlisted_epub_and_is_idempotent(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response($this->epub(), 200, ['Content-Type' => 'application/epub+zip'])]);
        $owner = User::factory()->create(['email' => 'admin@example.com', 'role' => User::ROLE_ADMIN]);
        config()->set('standard_ebooks.catalog', [$this->definition()]);

        $this->artisan('books:import-standard-ebooks', ['--limit' => 1, '--owner-email' => $owner->email])
            ->expectsOutputToContain('Test Book: imported (2 chapters)')
            ->assertSuccessful();

        $book = Book::query()->with(['chapters', 'source'])->firstOrFail();
        $this->assertSame('Test Author', $book->author_name);
        $this->assertSame('Chapter One', $book->chapters[0]->title);
        $this->assertSame("First paragraph.\n\nSecond paragraph.", $book->chapters[0]->content);
        $this->assertSame('Chapter Two', $book->chapters[1]->title);
        $this->assertSame('Third paragraph.', $book->chapters[1]->content);
        $this->assertSame('CC0 1.0 Universal', $book->source->license_name);
        $this->assertSame(1900, $book->source->author_death_year);
        Storage::disk('public')->assertExists('books/standard-ebooks/test-author_test-book.jpg');

        $this->artisan('books:import-standard-ebooks', ['--limit' => 1, '--owner-email' => $owner->email])
            ->expectsOutputToContain('Test Book: already present (2 chapters)')
            ->assertSuccessful();

        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseCount('chapters', 2);
        $this->assertDatabaseCount('book_sources', 1);
        Http::assertSentCount(1);
    }

    public function test_import_rejects_an_author_inside_the_conservative_rights_window(): void
    {
        Http::fake();
        $owner = User::factory()->create(['email' => 'admin@example.com', 'role' => User::ROLE_ADMIN]);
        config()->set('standard_ebooks.catalog', [array_merge($this->definition(), [
            'author_death_year' => now()->year,
        ])]);

        $this->artisan('books:import-standard-ebooks', ['--limit' => 1, '--owner-email' => $owner->email])
            ->expectsOutputToContain('has not passed the conservative Viet Nam life-plus-50 check')
            ->assertFailed();

        $this->assertDatabaseCount('books', 0);
        Http::assertNothingSent();
    }

    public function test_import_rejects_an_unreviewed_translation(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response($this->epub(translated: true), 200)]);
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        config()->set('standard_ebooks.catalog', [$this->definition()]);

        $this->artisan('books:import-standard-ebooks', ['--limit' => 1, '--owner-email' => $owner->email])
            ->expectsOutputToContain('Translated editions require a separate translator rights review.')
            ->assertFailed();

        $this->assertDatabaseCount('books', 0);
    }

    public function test_catalog_adds_reviewed_author_titles_from_the_public_sitemap(): void
    {
        config()->set('standard_ebooks.catalog', [$this->definition()]);
        config()->set('standard_ebooks.authors', [
            'test-author' => ['name' => 'Test Author', 'death_year' => 1900, 'category' => 'Fiction'],
        ]);
        Http::fake(['https://standardebooks.org/sitemap' => Http::response($this->sitemap(), 200)]);

        $definitions = app(StandardEbooksCatalog::class)->definitions(400);

        $this->assertCount(3, $definitions);
        $this->assertSame('test-author_another-book', $definitions[2]['identifier']);
        $this->assertSame(1900, $definitions[2]['author_death_year']);
        Http::assertSentCount(1);
    }

    public function test_command_continues_past_a_failed_title_until_the_target_is_met(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        config()->set('standard_ebooks.catalog', [$this->definition()]);
        config()->set('standard_ebooks.authors', [
            'test-author' => ['name' => 'Test Author', 'death_year' => 1900, 'category' => 'Fiction'],
        ]);
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/sitemap')) {
                return Http::response($this->sitemap(), 200);
            }

            if (str_contains($request->url(), 'bad-book.epub')) {
                return Http::response('Unavailable', 404);
            }

            return Http::response($this->epub(), 200);
        });

        $this->artisan('books:import-standard-ebooks', ['--limit' => 2, '--owner-email' => $owner->email])
            ->expectsOutputToContain('Standard Ebooks import completed: 2 books available (1 skipped).')
            ->assertSuccessful();

        $this->assertDatabaseCount('book_sources', 2);
        $this->assertDatabaseMissing('book_sources', ['source_identifier' => 'test-author_bad-book']);
    }

    public function test_command_stops_immediately_when_standard_ebooks_rate_limits_downloads(): void
    {
        Http::fake(['*' => Http::response('Rate limited', 429)]);
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        config()->set('standard_ebooks.catalog', [
            $this->definition(),
            array_merge($this->definition(), ['identifier' => 'test-author_second-book']),
        ]);

        $this->artisan('books:import-standard-ebooks', ['--limit' => 2, '--owner-email' => $owner->email])
            ->expectsOutputToContain('Standard Ebooks rate limit reached.')
            ->assertFailed();

        $this->assertDatabaseCount('books', 0);
        Http::assertSentCount(1);
    }

    public function test_command_stops_when_standard_ebooks_rate_limits_the_catalog(): void
    {
        Http::fake(['https://standardebooks.org/sitemap' => Http::response('Rate limited', 429)]);
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        config()->set('standard_ebooks.catalog', [$this->definition()]);

        $this->artisan('books:import-standard-ebooks', ['--limit' => 2, '--owner-email' => $owner->email])
            ->expectsOutputToContain('Standard Ebooks rate limit reached while loading the catalog.')
            ->assertFailed();

        $this->assertDatabaseCount('books', 0);
        Http::assertSentCount(1);
    }

    public function test_status_reports_count_without_an_owner_or_network_request(): void
    {
        Http::fake();

        $this->artisan('books:import-standard-ebooks', ['--status' => true])
            ->expectsOutputToContain('Standard Ebooks books imported: 0')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_command_rejects_a_reader_as_the_import_owner(): void
    {
        Http::fake();
        $reader = User::factory()->create(['email' => 'reader@example.com']);
        config()->set('standard_ebooks.catalog', [$this->definition()]);

        $this->artisan('books:import-standard-ebooks', ['--limit' => 1, '--owner-email' => $reader->email])
            ->expectsOutputToContain('The owner must have the admin role.')
            ->assertFailed();

        $this->assertDatabaseCount('books', 0);
        Http::assertNothingSent();
    }

    public function test_book_detail_exposes_source_and_display_author(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response($this->epub(), 200)]);
        $owner = User::factory()->create(['email' => 'admin@example.com', 'role' => User::ROLE_ADMIN]);
        config()->set('standard_ebooks.catalog', [$this->definition()]);
        $this->artisan('books:import-standard-ebooks', ['--limit' => 1, '--owner-email' => $owner->email])->assertSuccessful();
        $book = Book::query()->firstOrFail();

        $this->getJson('/api/books/'.$book->id)
            ->assertOk()
            ->assertJsonPath('data.author.name', 'Test Author')
            ->assertJsonPath('data.source.provider', 'Standard Ebooks')
            ->assertJsonPath('data.source.license_name', 'CC0 1.0 Universal')
            ->assertJsonPath('data.source.rights_jurisdiction', 'United States; Viet Nam');
    }

    /**
     * @return array<string, int|string>
     */
    private function definition(): array
    {
        return [
            'identifier' => 'test-author_test-book',
            'path' => 'test-author/test-book',
            'title' => 'Test Book',
            'author' => 'Test Author',
            'author_death_year' => 1900,
            'category' => 'Classic Fiction',
            'description' => 'A test description.',
        ];
    }

    private function epub(bool $translated = false): string
    {
        $path = tempnam(sys_get_temp_dir(), 'test-epub-');
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addFromString('mimetype', 'application/epub+zip');
        $archive->addFromString('META-INF/container.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
    <rootfiles><rootfile full-path="epub/content.opf" media-type="application/oebps-package+xml"/></rootfiles>
</container>
XML);
        $translatorRole = $translated ? '<meta property="role">trl</meta>' : '';
        $archive->addFromString('epub/content.opf', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf" version="3.0">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
        <dc:title>Test Book</dc:title>
        <dc:creator>Test Author</dc:creator>
        <dc:language>en</dc:language>
        <meta property="schema:abstract">A test description.</meta>
        {$translatorRole}
    </metadata>
    <manifest>
        <item id="cover" href="images/cover.jpg" media-type="image/jpeg" properties="cover-image"/>
        <item id="titlepage" href="text/titlepage.xhtml" media-type="application/xhtml+xml"/>
        <item id="chapter-1" href="text/chapter-1.xhtml" media-type="application/xhtml+xml"/>
        <item id="chapter-2" href="text/chapter-2.xhtml" media-type="application/xhtml+xml"/>
    </manifest>
    <spine><itemref idref="titlepage"/><itemref idref="chapter-1"/><itemref idref="chapter-2"/></spine>
</package>
XML);
        $archive->addFromString('epub/images/cover.jpg', 'fake-jpeg');
        $archive->addFromString('epub/text/titlepage.xhtml', $this->xhtml('frontmatter titlepage', 'Title', 'Not a chapter.'));
        $archive->addFromString('epub/text/chapter-1.xhtml', $this->xhtml('chapter', 'Chapter One', 'First paragraph.', 'Second paragraph.'));
        $archive->addFromString('epub/text/chapter-2.xhtml', $this->xhtml('se:short-story', 'Chapter Two', 'Third paragraph.'));
        $archive->close();
        $contents = file_get_contents($path);
        @unlink($path);

        return $contents === false ? '' : $contents;
    }

    private function xhtml(string $type, string $title, string ...$paragraphs): string
    {
        $content = implode('', array_map(fn (string $paragraph): string => '<p>'.$paragraph.'</p>', $paragraphs));

        return <<<XHTML
<?xml version="1.0" encoding="UTF-8"?>
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
    <body><section epub:type="{$type}"><h2>{$title}</h2>{$content}</section></body>
</html>
XHTML;
    }

    private function sitemap(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>https://standardebooks.org/ebooks/test-author/test-book</loc></url>
    <url><loc>https://standardebooks.org/ebooks/unreviewed-author/unknown-book</loc></url>
    <url><loc>https://standardebooks.org/ebooks/test-author/bad-book</loc></url>
    <url><loc>https://standardebooks.org/ebooks/test-author/another-book</loc></url>
    <url><loc>https://standardebooks.org/ebooks/test-author/translated-book/translator</loc></url>
</urlset>
XML;
    }
}

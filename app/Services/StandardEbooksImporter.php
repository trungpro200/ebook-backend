<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookSource;
use App\Models\Category;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class StandardEbooksImporter
{
    /**
     * @param  array{
     *     identifier: string,
     *     path: string,
     *     title: string,
     *     author: string,
     *     author_death_year: int,
     *     category: string,
     *     description: string
     * }  $definition
     * @return array{book: Book, imported: bool, chapters: int}
     */
    public function import(array $definition, User $owner): array
    {
        $existingSource = BookSource::query()
            ->where('source_identifier', $definition['identifier'])
            ->first();

        if ($existingSource !== null) {
            return [
                'book' => $existingSource->book()->firstOrFail(),
                'imported' => false,
                'chapters' => $existingSource->book()->withCount('chapters')->firstOrFail()->chapters_count,
            ];
        }

        $latestSafeDeathYear = now()->year - 51;

        if ($definition['author_death_year'] > $latestSafeDeathYear) {
            throw new RuntimeException("The author death year has not passed the conservative Viet Nam life-plus-50 check: {$definition['author']}");
        }

        $sourceUrl = 'https://standardebooks.org/ebooks/'.$definition['path'];
        $downloadUrl = $sourceUrl.'/downloads/'.$definition['identifier'].'.epub?source=download';
        $epub = Http::accept('application/epub+zip')
            ->withUserAgent('Moc Thu ebook importer (contact: admin@example.com)')
            ->timeout(90)
            ->retry(3, 750)
            ->get($downloadUrl)
            ->throw()
            ->body();

        if (! str_starts_with($epub, 'PK')) {
            throw new RuntimeException("Standard Ebooks did not return a valid EPUB for {$definition['identifier']}.");
        }

        $archivePath = tempnam(sys_get_temp_dir(), 'standard-ebook-');

        if ($archivePath === false || file_put_contents($archivePath, $epub) === false) {
            throw new RuntimeException('Unable to create a temporary EPUB file.');
        }

        try {
            $archive = new ZipArchive;

            if ($archive->open($archivePath) !== true) {
                throw new RuntimeException("Unable to open the EPUB for {$definition['identifier']}.");
            }

            try {
                $packagePath = $this->packagePath($archive);
                $bookFiles = $this->readBookFiles($archive, $packagePath);
            } finally {
                $archive->close();
            }
        } finally {
            @unlink($archivePath);
        }

        $coverExtension = pathinfo($bookFiles['cover_path'], PATHINFO_EXTENSION) ?: 'jpg';
        $coverPath = 'books/standard-ebooks/'.$definition['identifier'].'.'.strtolower($coverExtension);
        Storage::disk('public')->put($coverPath, $bookFiles['cover']);

        try {
            $book = DB::transaction(function () use ($bookFiles, $coverPath, $definition, $downloadUrl, $owner, $sourceUrl): Book {
                $category = Category::query()->firstOrCreate(
                    ['name' => $definition['category']],
                    ['description' => 'Rights-reviewed public-domain and CC0 editions.'],
                );

                $book = Book::query()->create([
                    'category_id' => $category->id,
                    'author_id' => $owner->id,
                    'author_name' => $definition['author'],
                    'title' => $definition['title'],
                    'description' => $definition['description'],
                    'cover' => $coverPath,
                    'language' => 'en',
                    'status' => 'completed',
                ]);

                $book->chapters()->createMany($bookFiles['chapters']);
                $book->source()->create([
                    'provider' => config('standard_ebooks.provider'),
                    'source_identifier' => $definition['identifier'],
                    'source_url' => $sourceUrl,
                    'download_url' => $downloadUrl,
                    'license_name' => config('standard_ebooks.license_name'),
                    'license_url' => config('standard_ebooks.license_url'),
                    'rights_jurisdiction' => 'United States; Viet Nam',
                    'rights_note' => 'Standard Ebooks contributions are dedicated under CC0. The underlying English-language work was reviewed conservatively under Viet Nam\'s author-life-plus-50 term. Attribution is retained because certain moral rights may continue.',
                    'author_death_year' => $definition['author_death_year'],
                    'translator_name' => null,
                    'translator_death_year' => null,
                    'cover_source_url' => $sourceUrl,
                    'cover_license_name' => config('standard_ebooks.license_name'),
                    'rights_checked_at' => now()->toDateString(),
                    'imported_at' => now(),
                ]);

                return $book;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($coverPath);

            throw $exception;
        }

        return [
            'book' => $book,
            'imported' => true,
            'chapters' => count($bookFiles['chapters']),
        ];
    }

    private function packagePath(ZipArchive $archive): string
    {
        $container = $archive->getFromName('META-INF/container.xml');

        if ($container === false) {
            throw new RuntimeException('The EPUB container manifest is missing.');
        }

        $document = $this->document($container);
        $path = (new DOMXPath($document))->evaluate('string(//*[local-name()="rootfile"]/@full-path)');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The EPUB package path is missing.');
        }

        return $path;
    }

    /**
     * @return array{
     *     chapters: list<array{title: string, chapter_number: int, content: string}>,
     *     cover: string,
     *     cover_path: string
     * }
     */
    private function readBookFiles(ZipArchive $archive, string $packagePath): array
    {
        $package = $archive->getFromName($packagePath);

        if ($package === false) {
            throw new RuntimeException('The EPUB package manifest cannot be read.');
        }

        $document = $this->document($package);
        $xpath = new DOMXPath($document);
        $basePath = dirname($packagePath);
        $manifest = [];
        $coverPath = null;

        foreach ($xpath->query('//*[local-name()="manifest"]/*[local-name()="item"]') ?: [] as $item) {
            if (! $item instanceof DOMElement) {
                continue;
            }

            $href = $this->archivePath($basePath, $item->getAttribute('href'));
            $manifest[$item->getAttribute('id')] = [
                'href' => $href,
                'media_type' => $item->getAttribute('media-type'),
            ];

            if (str_contains($item->getAttribute('properties'), 'cover-image')) {
                $coverPath = $href;
            }
        }

        $chapters = [];

        foreach ($xpath->query('//*[local-name()="spine"]/*[local-name()="itemref"]') ?: [] as $itemReference) {
            if (! $itemReference instanceof DOMElement) {
                continue;
            }

            $item = $manifest[$itemReference->getAttribute('idref')] ?? null;

            if ($item === null || $item['media_type'] !== 'application/xhtml+xml') {
                continue;
            }

            $chapter = $this->chapter($archive, $item['href'], count($chapters) + 1);

            if ($chapter !== null) {
                $chapters[] = $chapter;
            }
        }

        if ($chapters === []) {
            throw new RuntimeException('No readable chapters were found in the EPUB.');
        }

        if ($coverPath === null) {
            throw new RuntimeException('The EPUB cover image is missing.');
        }

        $cover = $archive->getFromName($coverPath);

        if ($cover === false) {
            throw new RuntimeException('The EPUB cover image cannot be read.');
        }

        return [
            'chapters' => $chapters,
            'cover' => $cover,
            'cover_path' => $coverPath,
        ];
    }

    /**
     * @return array{title: string, chapter_number: int, content: string}|null
     */
    private function chapter(ZipArchive $archive, string $path, int $number): ?array
    {
        $xhtml = $archive->getFromName($path);

        if ($xhtml === false) {
            return null;
        }

        $document = $this->document($xhtml);
        $xpath = new DOMXPath($document);
        $chapterTypes = 'chapter|z3998:short-story|prologue|epilogue';
        $type = '';

        foreach ($xpath->query('//*[@*[local-name()="type"]]') ?: [] as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            $type .= ' '.$element->getAttribute('epub:type').' '.$element->getAttributeNS('http://www.idpf.org/2007/ops', 'type');
        }

        if (preg_match('/(^|\s)('.$chapterTypes.')(?=\s|$)/u', trim($type)) !== 1) {
            return null;
        }

        $paragraphs = [];

        foreach ($xpath->query('//*[local-name()="p"]') ?: [] as $paragraph) {
            $text = $this->normalizeText($paragraph->textContent);

            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }

        if ($paragraphs === []) {
            return null;
        }

        $title = '';

        foreach ($xpath->query('//*[local-name()="h1" or local-name()="h2" or local-name()="h3"]') ?: [] as $heading) {
            $title = $this->normalizeText($heading->textContent);

            if ($title !== '') {
                break;
            }
        }

        return [
            'title' => $title !== '' ? $title : 'Chapter '.$number,
            'chapter_number' => $number,
            'content' => implode("\n\n", $paragraphs),
        ];
    }

    private function document(string $xml): DOMDocument
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('Invalid XML was found in the EPUB.');
        }

        return $document;
    }

    private function archivePath(string $basePath, string $relativePath): string
    {
        $parts = [];

        foreach (explode('/', str_replace('\\', '/', $basePath.'/'.$relativePath)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }

        return implode('/', $parts);
    }

    private function normalizeText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}

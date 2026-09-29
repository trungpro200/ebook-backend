<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class StandardEbooksCatalog
{
    /**
     * @return list<array<string, int|string>>
     */
    public function definitions(int $target): array
    {
        /** @var list<array<string, int|string>> $definitions */
        $definitions = config('standard_ebooks.catalog', []);

        if ($target <= count($definitions)) {
            return array_slice($definitions, 0, $target);
        }

        /** @var array<string, array{name: string, death_year: int, category: string}> $authors */
        $authors = config('standard_ebooks.authors', []);
        $knownIdentifiers = array_fill_keys(array_column($definitions, 'identifier'), true);
        $sitemap = Http::accept('application/xml')
            ->withUserAgent('Moc Thu ebook importer')
            ->timeout(60)
            ->retry(3, 1000, fn (\Throwable $exception): bool => ! $exception instanceof RequestException || $exception->response->serverError())
            ->get('https://standardebooks.org/sitemap')
            ->throw()
            ->body();

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($sitemap, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('The Standard Ebooks sitemap is not valid XML.');
        }

        foreach ((new DOMXPath($document))->query('//*[local-name()="url"]/*[local-name()="loc"]') ?: [] as $location) {
            if (! $location instanceof DOMElement) {
                continue;
            }

            if (preg_match('~^https://standardebooks\.org/ebooks/([a-z0-9-]+)/([a-z0-9-]+)$~', trim($location->textContent), $matches) !== 1) {
                continue;
            }

            $author = $authors[$matches[1]] ?? null;

            if ($author === null) {
                continue;
            }

            $identifier = $matches[1].'_'.$matches[2];

            if (isset($knownIdentifiers[$identifier])) {
                continue;
            }

            $knownIdentifiers[$identifier] = true;
            $definitions[] = [
                'identifier' => $identifier,
                'path' => $matches[1].'/'.$matches[2],
                'title' => Str::headline($matches[2]),
                'author' => $author['name'],
                'author_death_year' => $author['death_year'],
                'category' => $author['category'],
                'description' => '',
            ];
        }

        return $definitions;
    }
}

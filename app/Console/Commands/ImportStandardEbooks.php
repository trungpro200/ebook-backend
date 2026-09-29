<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StandardEbooksImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('books:import-standard-ebooks
    {--limit=10 : Maximum number of allowlisted books to import}
    {--owner-email=admin@example.com : Existing user that owns the imported records}')]
#[Description('Import the rights-reviewed Standard Ebooks allowlist')]
class ImportStandardEbooks extends Command
{
    public function handle(StandardEbooksImporter $importer): int
    {
        $owner = User::query()->where('email', $this->option('owner-email'))->first();

        if ($owner === null) {
            $this->error('The owner email does not belong to an existing user.');

            return self::FAILURE;
        }

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 10],
        ]);

        if ($limit === false) {
            $this->error('The limit must be an integer between 1 and 10.');

            return self::FAILURE;
        }

        /** @var list<array<string, mixed>> $catalog */
        $catalog = config('standard_ebooks.catalog', []);
        $failures = 0;

        foreach (array_slice($catalog, 0, $limit) as $definition) {
            try {
                $result = $importer->import($definition, $owner);
                $status = $result['imported'] ? 'imported' : 'already present';
                $this->info("{$definition['title']}: {$status} ({$result['chapters']} chapters)");
            } catch (Throwable $exception) {
                $failures++;
                $this->error("{$definition['title']}: {$exception->getMessage()}");
            }
        }

        if ($failures > 0) {
            $this->error("Import finished with {$failures} failure(s).");

            return self::FAILURE;
        }

        $this->info('Standard Ebooks import completed.');

        return self::SUCCESS;
    }
}

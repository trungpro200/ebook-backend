<?php

namespace App\Console\Commands;

use App\Models\BookSource;
use App\Models\User;
use App\Services\StandardEbooksCatalog;
use App\Services\StandardEbooksImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Throwable;

#[Signature('books:import-standard-ebooks
    {--limit=10 : Target number of eligible books (1 to 1000)}
    {--delay-ms=40000 : Pause between new ebook downloads during bulk imports}
    {--status : Show the number of books already imported from Standard Ebooks}
    {--owner-email=standard-ebooks-import@mocthu.invalid : Existing user that owns the imported records}')]
#[Description('Import eligible Standard Ebooks from the public catalog')]
class ImportStandardEbooks extends Command
{
    public function handle(StandardEbooksCatalog $catalog, StandardEbooksImporter $importer): int
    {
        if ($this->option('status')) {
            $count = BookSource::query()->where('provider', config('standard_ebooks.provider'))->count();
            $this->info("Standard Ebooks books imported: {$count}");

            return self::SUCCESS;
        }

        $owner = User::query()->where('email', $this->option('owner-email'))->first();

        if ($owner === null) {
            $this->error('The owner email does not belong to an existing user.');

            return self::FAILURE;
        }

        if ($owner->role !== User::ROLE_ADMIN) {
            $this->error('The owner must have the admin role.');

            return self::FAILURE;
        }

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000],
        ]);

        if ($limit === false) {
            $this->error('The limit must be an integer between 1 and 1000.');

            return self::FAILURE;
        }

        $delayMilliseconds = filter_var($this->option('delay-ms'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 60000],
        ]);

        if ($delayMilliseconds === false) {
            $this->error('The delay must be an integer between 0 and 60000 milliseconds.');

            return self::FAILURE;
        }

        try {
            $definitions = $catalog->definitions($limit);
        } catch (Throwable $exception) {
            if ($exception instanceof RequestException && $exception->response->status() === 429) {
                $this->error('Standard Ebooks rate limit reached while loading the catalog. Resume later with the same command.');

                return self::FAILURE;
            }

            $this->error('Unable to load the Standard Ebooks catalog: '.$exception->getMessage());

            return self::FAILURE;
        }

        $completed = 0;
        $failures = 0;

        foreach ($definitions as $definition) {
            if ($completed >= $limit) {
                break;
            }

            try {
                $result = $importer->import($definition, $owner);
                $status = $result['imported'] ? 'imported' : 'already present';
                $this->info("{$result['book']->title}: {$status} ({$result['chapters']} chapters)");
                $completed++;

                if ($limit > 10 && $result['imported']) {
                    usleep($delayMilliseconds * 1000);
                }
            } catch (Throwable $exception) {
                if ($exception instanceof RequestException && $exception->response->status() === 429) {
                    $retryAfter = $exception->response->header('Retry-After');
                    $wait = $retryAfter !== null ? " Retry after {$retryAfter}." : '';
                    $this->error("Standard Ebooks rate limit reached. Stop this run and resume later with the same command.{$wait}");

                    return self::FAILURE;
                }

                $failures++;
                $this->error("{$definition['title']}: {$exception->getMessage()}");

                if ($limit > 10) {
                    usleep($delayMilliseconds * 1000);
                }
            }
        }

        if ($completed < $limit) {
            $this->error("Only {$completed} of {$limit} eligible books are present after this run ({$failures} failure(s)).");

            return self::FAILURE;
        }

        $this->info("Standard Ebooks import completed: {$completed} books available ({$failures} skipped).");

        return self::SUCCESS;
    }
}

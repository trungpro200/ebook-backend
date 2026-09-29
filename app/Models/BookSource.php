<?php

namespace App\Models;

use Database\Factories\BookSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookSource extends Model
{
    /** @use HasFactory<BookSourceFactory> */
    use HasFactory;

    protected $fillable = [
        'book_id',
        'provider',
        'source_identifier',
        'source_url',
        'download_url',
        'license_name',
        'license_url',
        'rights_jurisdiction',
        'rights_note',
        'author_death_year',
        'translator_name',
        'translator_death_year',
        'cover_source_url',
        'cover_license_name',
        'rights_checked_at',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'author_death_year' => 'integer',
            'translator_death_year' => 'integer',
            'rights_checked_at' => 'date',
            'imported_at' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}

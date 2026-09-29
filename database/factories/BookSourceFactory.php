<?php

namespace Database\Factories;

use App\Models\BookSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookSource>
 */
class BookSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'Standard Ebooks',
            'source_identifier' => fake()->unique()->slug(3),
            'source_url' => fake()->url(),
            'download_url' => fake()->url(),
            'license_name' => 'CC0 1.0 Universal',
            'license_url' => 'https://creativecommons.org/publicdomain/zero/1.0/',
            'rights_jurisdiction' => 'United States; Viet Nam',
            'rights_note' => 'Rights reviewed for testing.',
            'author_death_year' => 1900,
            'translator_name' => null,
            'translator_death_year' => null,
            'cover_source_url' => fake()->url(),
            'cover_license_name' => 'CC0 1.0 Universal',
            'rights_checked_at' => now(),
            'imported_at' => now(),
        ];
    }
}

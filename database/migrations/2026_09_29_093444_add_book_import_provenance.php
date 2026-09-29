<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->string('author_name')->nullable()->after('author_id');
        });

        Schema::create('book_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('source_identifier')->unique();
            $table->string('source_url');
            $table->string('download_url');
            $table->string('license_name');
            $table->string('license_url');
            $table->string('rights_jurisdiction');
            $table->text('rights_note');
            $table->unsignedSmallInteger('author_death_year');
            $table->string('translator_name')->nullable();
            $table->unsignedSmallInteger('translator_death_year')->nullable();
            $table->string('cover_source_url');
            $table->string('cover_license_name');
            $table->date('rights_checked_at');
            $table->timestamp('imported_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_sources');

        Schema::table('books', function (Blueprint $table): void {
            $table->dropColumn('author_name');
        });
    }
};

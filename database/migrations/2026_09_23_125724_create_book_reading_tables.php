<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        */
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Books
        |--------------------------------------------------------------------------
        */
        Schema::create('books', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->foreignId('author_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover')->nullable();
            $table->string('language')->default('vi');
            $table->string('status')->default('ongoing');
            $table->unsignedBigInteger('view_count')->default(0);

            $table->timestamps();

            $table->index('author_id');
            $table->index('category_id');
        });

        /*
        |--------------------------------------------------------------------------
        | Chapters
        |--------------------------------------------------------------------------
        */
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnDelete();

            $table->string('title');
            $table->unsignedInteger('chapter_number');
            $table->longText('content');

            $table->timestamps();

            $table->unique(['book_id', 'chapter_number']);
        });

        /*
        |--------------------------------------------------------------------------
        | Favorites
        |--------------------------------------------------------------------------
        */
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnDelete();

            $table->timestamps();

            // Một user không thể favorite cùng một sách 2 lần
            $table->unique(['user_id', 'book_id']);
        });

        /*
        |--------------------------------------------------------------------------
        | Bookmarks
        |--------------------------------------------------------------------------
        */
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnDelete();

            $table->foreignId('chapter_id')
                ->constrained('chapters')
                ->cascadeOnDelete();

            $table->unsignedInteger('position')->default(0);
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'book_id']);
        });

        /*
        |--------------------------------------------------------------------------
        | Reading Progress
        |--------------------------------------------------------------------------
        */
        Schema::create('reading_progress', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('book_id')
                ->constrained('books')
                ->cascadeOnDelete();

            $table->foreignId('chapter_id')
                ->constrained('chapters')
                ->cascadeOnDelete();

            $table->decimal('progress', 5, 2)->default(0);
            $table->timestamp('last_read_at')->nullable();

            $table->timestamps();

            // Mỗi user chỉ có 1 record tiến độ cho mỗi sách
            $table->unique(['user_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_progress');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('books');
        Schema::dropIfExists('categories');
    }
};

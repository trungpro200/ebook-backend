<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('reader_preferences')->nullable();
        });

        Schema::table('bookmarks', function (Blueprint $table): void {
            $table->decimal('percent', 5, 2)->default(0)->after('position');
            $table->longText('quote')->nullable()->after('percent');
        });
    }

    public function down(): void
    {
        Schema::table('bookmarks', function (Blueprint $table): void {
            $table->dropColumn(['percent', 'quote']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('reader_preferences');
        });
    }
};

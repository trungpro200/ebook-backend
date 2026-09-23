<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // =========================
        // Users
        // =========================
        $author = DB::table('users')->insertGetId([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'avatar' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // =========================
        // Categories
        // =========================
        $fantasy = DB::table('categories')->insertGetId([
            'name' => 'Fantasy',
            'description' => 'Truyện kỳ ảo, phép thuật và thế giới giả tưởng.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $action = DB::table('categories')->insertGetId([
            'name' => 'Action',
            'description' => 'Truyện hành động và phiêu lưu.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $romance = DB::table('categories')->insertGetId([
            'name' => 'Romance',
            'description' => 'Truyện tình cảm.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // =========================
        // Books
        // =========================
        $book1 = DB::table('books')->insertGetId([
            'category_id' => $fantasy,
            'author_id' => $author,
            'title' => 'The Last Mage',
            'description' => 'Một pháp sư trẻ phải khám phá bí mật cuối cùng của thế giới phép thuật.',
            'cover' => null,
            'language' => 'vi',
            'status' => 'ongoing',
            'view_count' => 1250,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $book2 = DB::table('books')->insertGetId([
            'category_id' => $action,
            'author_id' => $author,
            'title' => 'Beyond the Battlefield',
            'description' => 'Một chiến binh trẻ bước vào hành trình tìm kiếm sự thật.',
            'cover' => null,
            'language' => 'vi',
            'status' => 'completed',
            'view_count' => 3420,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $book3 = DB::table('books')->insertGetId([
            'category_id' => $romance,
            'author_id' => $author,
            'title' => 'A Summer Promise',
            'description' => 'Một lời hứa mùa hè dẫn hai người xa lạ đến với nhau.',
            'cover' => null,
            'language' => 'vi',
            'status' => 'ongoing',
            'view_count' => 870,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // =========================
        // Chapters
        // =========================

        DB::table('chapters')->insert([
            [
                'book_id' => $book1,
                'title' => 'Người kế thừa',
                'chapter_number' => 1,
                'content' => 'Đây là nội dung chương đầu tiên của The Last Mage...',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'book_id' => $book1,
                'title' => 'Cánh cửa bí ẩn',
                'chapter_number' => 2,
                'content' => 'Một cánh cửa bí ẩn xuất hiện trong khu rừng...',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'book_id' => $book1,
                'title' => 'Sức mạnh thức tỉnh',
                'chapter_number' => 3,
                'content' => 'Sức mạnh bên trong cậu bắt đầu thức tỉnh...',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'book_id' => $book2,
                'title' => 'Ngày đầu tiên',
                'chapter_number' => 1,
                'content' => 'Chiến tranh đã kết thúc, nhưng hành trình của anh mới bắt đầu...',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'book_id' => $book2,
                'title' => 'Kẻ sống sót',
                'chapter_number' => 2,
                'content' => 'Anh gặp một người sống sót khác trong vùng đất hoang...',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'book_id' => $book3,
                'title' => 'Cuộc gặp đầu tiên',
                'chapter_number' => 1,
                'content' => 'Họ gặp nhau vào một buổi chiều mùa hè...',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'book_id' => $book3,
                'title' => 'Lời hứa',
                'chapter_number' => 2,
                'content' => 'Một lời hứa tưởng như đơn giản lại thay đổi mọi thứ...',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bookmark extends Model
{
    protected $fillable = ['user_id', 'book_id', 'chapter_id', 'position', 'percent', 'quote', 'note'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'percent' => 'float'];
    }
}

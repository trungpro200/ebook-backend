<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReadingProgress extends Model
{
    protected $fillable = ['user_id', 'book_id', 'chapter_id', 'progress', 'last_read_at'];

    protected function casts(): array
    {
        return ['progress' => 'float', 'last_read_at' => 'datetime'];
    }
}

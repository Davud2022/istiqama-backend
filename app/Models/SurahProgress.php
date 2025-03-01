<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurahProgress extends Model
{
    protected $fillable = [
        'user_id', 'surah_number', 'ayahs', 'progress',
    ];

    // Relationship
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

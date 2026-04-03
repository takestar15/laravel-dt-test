<?php

namespace App\Models;

use Database\Factories\TranslationTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TranslationTag extends Model
{
    /** @use HasFactory<TranslationTagFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function translations(): BelongsToMany
    {
        return $this->belongsToMany(Translation::class);
    }
}

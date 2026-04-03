<?php

namespace App\Models;

use Database\Factories\TranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Translation extends Model
{
    /** @use HasFactory<TranslationFactory> */
    use HasFactory;

    protected $fillable = [
        'locale',
        'key',
        'value',
    ];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TranslationTag::class)
            ->orderBy('name');
    }

    #[Scope]
    protected function forLocale(Builder $query, string $locale): void
    {
        $query->where('locale', $locale);
    }
}

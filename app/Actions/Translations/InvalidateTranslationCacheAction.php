<?php

namespace App\Actions\Translations;

use Illuminate\Support\Facades\Cache;

class InvalidateTranslationCacheAction
{
    public const VERSION_CACHE_KEY = 'translations.export.version';

    public function handle(): int
    {
        $nextVersion = $this->currentVersion() + 1;

        Cache::forever(self::VERSION_CACHE_KEY, $nextVersion);

        return $nextVersion;
    }

    public function currentVersion(): int
    {
        return (int) Cache::get(self::VERSION_CACHE_KEY, 1);
    }
}

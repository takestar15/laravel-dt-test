<?php

namespace App\Console\Commands;

use App\Models\Translation;
use App\Models\TranslationTag;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('translations:seed {--count=100000 : Number of translations to create} {--chunk=1000 : Insert chunk size}')]
#[Description('Seed the translations table with a large dataset for scalability testing')]
class SeedTranslations extends Command
{
    public function handle(): int
    {
        $count = max((int) $this->option('count'), 1);
        $chunkSize = max((int) $this->option('chunk'), 100);
        $locales = ['en', 'fr', 'es', 'de'];
        $tags = collect(['web', 'mobile', 'desktop', 'marketing', 'checkout', 'email']);

        $tagIds = $tags->mapWithKeys(function (string $tag): array {
            $model = TranslationTag::query()->firstOrCreate(['name' => $tag]);

            return [$tag => $model->id];
        });

        $inserted = 0;

        while ($inserted < $count) {
            $batchSize = min($chunkSize, $count - $inserted);
            $now = now();
            $rows = [];

            for ($index = 0; $index < $batchSize; $index++) {
                $sequence = $inserted + $index + 1;
                $tagName = $tags[$sequence % $tags->count()];

                $rows[] = [
                    'locale' => $locales[$sequence % count($locales)],
                    'key' => sprintf('feature.%s.label_%d', $tagName, $sequence),
                    'value' => sprintf('Seeded translation value %d', $sequence),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            Translation::query()->insert($rows);

            $latestTranslations = Translation::query()
                ->latest('id')
                ->limit($batchSize)
                ->get(['id', 'key']);

            $pivotRows = $latestTranslations
                ->map(function (Translation $translation) use ($tagIds): array {
                    $tagName = str_contains($translation->key, '.mobile.')
                        ? 'mobile'
                        : (str_contains($translation->key, '.desktop.') ? 'desktop' : 'web');

                    return [
                        'translation_id' => $translation->id,
                        'translation_tag_id' => $tagIds[$tagName],
                    ];
                })
                ->all();

            if ($pivotRows !== []) {
                DB::table('translation_translation_tag')->insertOrIgnore($pivotRows);
            }

            $inserted += $batchSize;
            $this->output->write('.');
        }

        $this->newLine();
        $this->info("Seeded {$count} translations.");

        return self::SUCCESS;
    }
}

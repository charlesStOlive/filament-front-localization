<?php

namespace CharlesStOlive\FilamentFrontLocalization\Console;

use CharlesStOlive\FilamentFrontLocalization\Catalog\TextCatalog;
use CharlesStOlive\FilamentFrontLocalization\Console\Concerns\ShowsChanges;
use CharlesStOlive\FilamentFrontLocalization\Models\FrontTextOverride;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Exporte les textes modifiés (ceux du catalogue) en JSON : en production, pour les amener en dev
 * (front-texts:import), où front-texts:write les fera passer dans les fichiers.
 *
 *     php artisan front-texts:export                       # storage/app/front-texts/front-texts-2026-11-02-1430.json
 *     php artisan front-texts:export - > textes.json        # sur la sortie standard
 */
class ExportFrontTextsCommand extends Command
{
    use ShowsChanges;

    public const FORMAT = 1;

    protected $signature = 'front-texts:export
        {file? : Fichier JSON à écrire (- pour la sortie standard) ; par défaut storage/app/front-texts/…}
        {--group=* : Groupes à exporter, jokers permis (des/*, mail-access/*) ; tous par défaut}
        {--only= : lang (fichiers de langue) ou config (textes en config, comme MailAccess)}';

    protected $description = 'Exporte en JSON les textes modifiés dans le B.O., pour front-texts:import';

    public function handle(TextCatalog $catalog): int
    {
        if (($only = $this->onlyOption()) === false) {
            $this->error('--only attend lang ou config.');

            return self::FAILURE;
        }

        $texts = [];

        foreach ($catalog->select($this->groupPatterns(), $only) as $group) {
            $overrides = FrontTextOverride::query()
                ->where(['namespace' => $group->namespace, 'group' => $group->group])
                ->orderBy('key')
                ->get();

            foreach ($overrides as $override) {
                if ($group->definition($override->key) !== null && $override->text !== []) {
                    $texts[] = [
                        'group' => $group->id(),
                        'key' => $override->key,
                        'text' => $override->text,
                        'updated_at' => $override->updated_at?->toIso8601String(),
                    ];
                }
            }
        }

        $json = json_encode([
            'format' => self::FORMAT,
            'app' => config('app.name'),
            'environment' => app()->environment(),
            'exported_at' => now()->toIso8601String(),
            'texts' => $texts,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $file = $this->argument('file');

        if ($file === '-') {
            $this->output->writeln($json, \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        $file ??= storage_path('app/front-texts/front-texts-'.now()->format('Y-m-d-Hi').'.json');
        File::ensureDirectoryExists(dirname($file));
        File::put($file, $json."\n");

        $this->info(count($texts)." texte(s) exporté(s) : {$file}");

        return self::SUCCESS;
    }
}

<?php

namespace CharlesStOlive\FilamentFrontLocalization\Console;

use CharlesStOlive\FilamentFrontLocalization\Catalog\TextCatalog;
use CharlesStOlive\FilamentFrontLocalization\Console\Concerns\ShowsChanges;
use CharlesStOlive\FilamentFrontLocalization\FrontTexts;
use CharlesStOlive\FilamentFrontLocalization\TextOverrides;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use JsonException;

/**
 * Importe un export de front-texts:export (la production) dans la base de dev, avant front-texts:write. Chaque texte est
 * revalidé contre les fichiers d'ici (paramètres, pluriels, balises) ; une clé hors catalogue ou invalide est écartée et
 * signalée. Pour une clé du fichier, ses langues remplacent celles de la base ; avec --replace, les groupes importés
 * reprennent exactement le fichier (les textes d'ici absents du fichier sont retirés).
 *
 * Interdite en production : un import y écraserait les textes que les rédacteurs y ont modifiés.
 */
class ImportFrontTextsCommand extends Command
{
    use ShowsChanges;

    protected $signature = 'front-texts:import
        {file : Fichier JSON de front-texts:export}
        {--group=* : Groupes à importer, jokers permis (des/*, mail-access/*) ; tous par défaut}
        {--only= : lang (fichiers de langue) ou config (textes en config, comme MailAccess)}
        {--replace : Les groupes importés reprennent exactement le fichier (les textes d\'ici absents du fichier sont retirés)}
        {--dry-run : Montrer ce qui serait importé, sans rien toucher}';

    protected $description = 'Importe en dev les textes exportés par front-texts:export (avant front-texts:write)';

    public function handle(TextCatalog $catalog, FrontTexts $texts, TextOverrides $overrides): int
    {
        if (app()->isProduction()) {
            $this->error('Interdit en production : l\'import écraserait les textes modifiés ici par les rédacteurs.');

            return self::FAILURE;
        }

        if (($only = $this->onlyOption()) === false) {
            $this->error('--only attend lang ou config.');

            return self::FAILURE;
        }

        try {
            $export = json_decode((string) @file_get_contents($this->argument('file')), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->error('Fichier illisible ou JSON invalide : '.$this->argument('file'));

            return self::FAILURE;
        }

        if (($export['format'] ?? null) !== ExportFrontTextsCommand::FORMAT || ! is_array($export['texts'] ?? null)) {
            $this->error('Ce fichier ne vient pas de front-texts:export (format inconnu).');

            return self::FAILURE;
        }

        $this->line(sprintf('Export de %s (%s), %s.', $export['app'] ?? '?', $export['environment'] ?? '?', $export['exported_at'] ?? '?'));

        $groups = collect($catalog->select($this->groupPatterns(), $only))->keyBy(fn ($group) => $group->id());
        $rows = [];
        $imported = [];

        foreach ($export['texts'] as $entry) {
            $group = $groups[$entry['group'] ?? ''] ?? null;
            $key = (string) ($entry['key'] ?? '');
            $values = array_filter((array) ($entry['text'] ?? []), 'is_string');

            if ($group === null) {
                // Hors des groupes choisis : ni importé, ni signalé, sauf s'il n'est pas du tout au catalogue.
                if (! isset($catalog->groups()[$entry['group'] ?? ''])) {
                    $rows[] = [$entry['group'] ?? '?', $key, '', 'écarté : groupe hors catalogue'];
                }

                continue;
            }

            $status = 'importé';

            if ($group->definition($key) === null) {
                $status = 'écarté : clé hors catalogue';
            } elseif ($errors = $texts->errors($group->id(), $key, $values)) {
                // Revalidé contre les fichiers d'ici : un texte d'origine a pu changer depuis l'export.
                $status = 'écarté : '.implode(' ', $errors);
            } elseif (! $this->option('dry-run')) {
                // Avec --replace, une langue absente de l'export reprend son texte d'origine.
                $payload = $this->option('replace')
                    ? array_replace(array_fill_keys(array_keys($texts->locales()), null), $values)
                    : $values;
                $texts->save($group->id(), $key, $payload, null, TextOverrides::REASON_IMPORTED);
                $imported[$group->id()][] = $key;
            }

            foreach ($values as $locale => $value) {
                $rows[] = [$group->qualifiedKey($key), $locale, Str::limit($value, 60), $status];
            }
        }

        $this->table(['Texte', 'Langue', 'Texte importé', 'Suite'], $rows);

        if ($this->option('dry-run')) {
            $this->comment('Essai : rien n\'a été importé.');

            return self::SUCCESS;
        }

        if ($this->option('replace')) {
            $removed = 0;

            foreach ($groups as $group) {
                foreach (array_keys($overrides->group($group->namespace, $group->group)) as $key) {
                    if (! in_array($key, $imported[$group->id()] ?? [], true)) {
                        $locales = array_keys($overrides->values($group->namespace, $group->group, $key));
                        $overrides->put($group->namespace, $group->group, $key, array_fill_keys($locales, null), null, TextOverrides::REASON_IMPORTED);
                        $removed++;
                    }
                }
            }

            $this->info("{$removed} texte(s) d'ici absent(s) du fichier retiré(s).");
        }

        $this->info(collect($imported)->flatten()->count().' texte(s) importé(s). Ensuite : front-texts:write.');

        return self::SUCCESS;
    }
}

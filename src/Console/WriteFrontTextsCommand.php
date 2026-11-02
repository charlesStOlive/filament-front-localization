<?php

namespace CharlesStOlive\FilamentFrontLocalization\Console;

use CharlesStOlive\FilamentFrontLocalization\Console\Concerns\ShowsChanges;
use CharlesStOlive\FilamentFrontLocalization\TextOverrides;
use CharlesStOlive\FilamentFrontLocalization\Writing\Change;
use CharlesStOlive\FilamentFrontLocalization\Writing\FrontTextsWriter;
use Illuminate\Console\Command;

/**
 * Fait passer les textes modifiés dans le B.O. dans le code : fichiers de langue, ou fichier de config pour un groupe
 * `config:` (MailAccess). Les textes écrits sortent ensuite de la base.
 *
 * À lancer là où vivent les fichiers suivis par git (en dev), puis : relire le diff, committer, déployer, et lancer
 * front-texts:prune en production pour y retirer les mêmes textes. Interdite en production : les fichiers écrits y
 * seraient écrasés au prochain déploiement.
 */
class WriteFrontTextsCommand extends Command
{
    use ShowsChanges;

    protected $signature = 'front-texts:write
        {--group=* : Groupes à écrire, jokers permis (des/*, mail-access/*) ; tous par défaut}
        {--only= : lang (fichiers de langue) ou config (textes en config, comme MailAccess)}
        {--dry-run : Montrer ce qui serait écrit, sans rien toucher}
        {--keep : Écrire les fichiers sans retirer les textes de la base}';

    protected $description = 'Écrit les textes modifiés dans le B.O. dans les fichiers de langue (ou la config), puis les retire de la base';

    public function handle(FrontTextsWriter $writer): int
    {
        if (app()->isProduction()) {
            $this->error('Interdit en production : les fichiers écrits seraient écrasés au prochain déploiement, et les textes retirés de la base perdus.');
            $this->line('En production : front-texts:export. En dev : front-texts:import, front-texts:write, commit, déploiement. Puis ici : front-texts:prune.');

            return self::FAILURE;
        }

        if (($only = $this->onlyOption()) === false) {
            $this->error('--only attend lang ou config.');

            return self::FAILURE;
        }

        $changes = $writer->plan($this->groupPatterns(), $only);

        if ($changes === []) {
            $this->info('Aucun texte modifié dans ces groupes.');

            return self::SUCCESS;
        }

        $this->showChanges($changes);

        if ($this->option('dry-run')) {
            $this->comment('Essai : rien n\'a été écrit ni retiré.');

            return self::SUCCESS;
        }

        $written = $writer->write($changes);
        $files = array_unique(array_map(fn (Change $change): string => (string) $change->file, $written));
        $this->info(count($written).' texte(s) écrit(s) dans '.count($files).' fichier(s).');

        if (! $this->option('keep')) {
            $already = array_filter($changes, fn (Change $change): bool => $change->status === Change::ALREADY_IN_FILE);
            $removed = $writer->prune($written, TextOverrides::REASON_WRITTEN) + $writer->prune($already);
            $this->info("{$removed} texte(s) retiré(s) de la base : le fichier les porte.");
        }

        $left = array_filter($changes, fn (Change $change): bool => in_array($change->status, [Change::MISSING, Change::PLUGIN], true));
        if ($left !== []) {
            $this->warn(count($left).' texte(s) restent en base : à reporter à la main (voir « Suite »).');
        }

        if ($files !== []) {
            $this->line('Ensuite : relire le diff (git diff lang config), committer, déployer, puis front-texts:prune en production.');
        }

        return self::SUCCESS;
    }
}

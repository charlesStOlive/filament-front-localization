<?php

namespace CharlesStOlive\FilamentFrontLocalization\Console;

use CharlesStOlive\FilamentFrontLocalization\Console\Concerns\ShowsChanges;
use CharlesStOlive\FilamentFrontLocalization\Writing\FrontTextsWriter;
use Illuminate\Console\Command;

/**
 * Retire de la base les textes modifiés que les fichiers portent déjà à l'identique : en production, après le
 * déploiement des fichiers écrits par front-texts:write. Un texte modifié depuis (différent du fichier) reste.
 */
class PruneFrontTextsCommand extends Command
{
    use ShowsChanges;

    protected $signature = 'front-texts:prune
        {--group=* : Groupes à nettoyer, jokers permis (des/*, mail-access/*) ; tous par défaut}
        {--only= : lang (fichiers de langue) ou config (textes en config, comme MailAccess)}
        {--dry-run : Montrer ce qui serait retiré, sans rien toucher}';

    protected $description = 'Retire de la base les textes modifiés que les fichiers de langue (ou la config) portent déjà';

    public function handle(FrontTextsWriter $writer): int
    {
        if (($only = $this->onlyOption()) === false) {
            $this->error('--only attend lang ou config.');

            return self::FAILURE;
        }

        $changes = $writer->prunable($this->groupPatterns(), $only);

        if ($changes === []) {
            $this->info('Aucun texte modifié n\'est déjà dans les fichiers.');

            return self::SUCCESS;
        }

        $this->showChanges($changes);

        if ($this->option('dry-run')) {
            $this->comment('Essai : rien n\'a été retiré.');

            return self::SUCCESS;
        }

        $this->info($writer->prune($changes).' texte(s) retiré(s) de la base : le fichier les porte.');

        if (app()->configurationIsCached()) {
            $this->line('La config est en cache : si un texte de config a changé dans ce déploiement, php artisan config:cache.');
        }

        return self::SUCCESS;
    }
}

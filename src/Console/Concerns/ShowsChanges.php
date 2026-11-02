<?php

namespace CharlesStOlive\FilamentFrontLocalization\Console\Concerns;

use CharlesStOlive\FilamentFrontLocalization\Writing\Change;
use Illuminate\Support\Str;

/** Le tableau des textes modifiés que montrent front-texts:write et front-texts:prune. */
trait ShowsChanges
{
    /** @return array<int, string> les groupes demandés (--group), jokers permis */
    protected function groupPatterns(): array
    {
        return array_values(array_filter((array) $this->option('group')));
    }

    /** `lang`, `config` ou null ; false si la valeur n'est pas reconnue. */
    protected function onlyOption(): string|false|null
    {
        $only = $this->option('only');

        return in_array($only, [null, 'lang', 'config'], true) ? $only : false;
    }

    /** @param  array<int, Change>  $changes */
    protected function showChanges(array $changes): void
    {
        $this->table(
            ['Texte', 'Langue', 'Fichier', 'Dans le fichier', 'Modifié', 'Suite'],
            array_map(fn (Change $change): array => [
                $change->qualifiedKey(),
                $change->locale,
                $change->file ? Str::after($change->file, base_path().DIRECTORY_SEPARATOR) : '—',
                Str::limit((string) $change->old, 45),
                Str::limit($change->new, 45),
                match ($change->status) {
                    Change::WRITABLE => 'écrit dans le fichier',
                    Change::ALREADY_IN_FILE => 'déjà dans le fichier',
                    Change::MISSING => 'à reporter à la main (pas de chaîne simple à cet endroit)',
                    Change::PLUGIN => 'à reporter dans le plugin',
                },
            ], $changes),
        );
    }
}

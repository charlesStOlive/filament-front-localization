<?php

namespace CharlesStOlive\FilamentFrontLocalization\Writing;

use CharlesStOlive\FilamentFrontLocalization\Catalog\TextCatalog;
use CharlesStOlive\FilamentFrontLocalization\Catalog\TextGroup;
use CharlesStOlive\FilamentFrontLocalization\TextOverrides;
use Throwable;

/**
 * Fait passer les textes modifiés dans le code : le fichier de langue (`lang/{langue}/{groupe}.php`), ou le fichier de
 * config d'un groupe `config:` (`config/mail-access.php`, `…messages.page_title.fr`). Puis retire de la base les textes
 * que le code porte désormais : le fichier redevient la seule source.
 *
 * - write() modifie les fichiers chirurgicalement (PhpArrayFile), relit chacun, et remet tout en place au moindre écart.
 * - prune() retire de la base les textes identiques à ceux des fichiers : après write() en dev, ou en production après
 *   le déploiement des fichiers. Il ne retire jamais un texte que le fichier n'a pas.
 *
 * Non écrits (la base les garde) : les textes d'un plugin (leur fichier est dans le plugin), une clé absente du fichier
 * de cette langue, une valeur qui n'y est pas une chaîne simple.
 */
class FrontTextsWriter
{
    public function __construct(
        protected TextCatalog $catalog,
        protected TextOverrides $overrides,
        protected string $langPath,
        protected string $configPath,
    ) {}

    /**
     * Les textes modifiés des groupes choisis, et ce qu'il adviendrait de chacun.
     *
     * @param  array<int, string>  $patterns  identifiants de groupes, jokers permis (`des/*`) ; vide : tous
     * @param  ?string  $only  `lang` ou `config`
     * @return array<int, Change>
     */
    public function plan(array $patterns = [], ?string $only = null): array
    {
        $changes = [];

        foreach ($this->catalog->select($patterns, $only) as $group) {
            foreach ($this->overrides->group($group->namespace, $group->group) as $key => $texts) {
                if ($group->definition($key) === null) {
                    continue;
                }

                foreach ($texts as $locale => $text) {
                    $changes[] = $this->change($group, $key, $locale, $text);
                }
            }
        }

        return $changes;
    }

    /**
     * Écrit les changements faisables, fichier par fichier : rien n'est enregistré si un fichier ne se relit pas comme
     * attendu (tous les fichiers déjà écrits reprennent leur contenu d'avant).
     *
     * @param  array<int, Change>  $changes
     * @return array<int, Change> ceux qui ont été écrits
     */
    public function write(array $changes): array
    {
        $writable = array_values(array_filter($changes, fn (Change $change): bool => $change->status === Change::WRITABLE));
        $byFile = [];

        foreach ($writable as $change) {
            $byFile[$change->file][] = $change;
        }

        $originals = [];

        try {
            foreach ($byFile as $file => $fileChanges) {
                $document = PhpArrayFile::open($file);
                $originals[$file] = $document->code();

                foreach ($fileChanges as $change) {
                    $document->replace($change->path, $change->new);
                }

                file_put_contents($file, $document->code());
            }

            foreach ($writable as $change) {
                if ($this->fileValue($change->file, $change->path) !== $change->new) {
                    throw new \RuntimeException("{$change->file} : la valeur de ".implode('.', $change->path).' ne se relit pas à l\'identique.');
                }
            }
        } catch (Throwable $exception) {
            foreach ($originals as $file => $code) {
                file_put_contents($file, $code);
            }

            throw $exception;
        }

        return $writable;
    }

    /**
     * Les textes modifiés que les fichiers portent déjà : ceux que prune() retirerait.
     *
     * @return array<int, Change>
     */
    public function prunable(array $patterns = [], ?string $only = null): array
    {
        return array_values(array_filter($this->plan($patterns, $only), fn (Change $change): bool => $change->status === Change::ALREADY_IN_FILE));
    }

    /**
     * Retire de la base les textes que leur fichier porte à l'identique (relu sur le disque, pas en mémoire).
     *
     * @param  array<int, Change>  $changes
     * @return int le nombre de textes retirés
     */
    public function prune(array $changes, string $reason = TextOverrides::REASON_PRUNED): int
    {
        $count = 0;

        foreach ($changes as $change) {
            if ($change->file === null || $this->fileValue($change->file, $change->path) !== $change->new) {
                continue;
            }

            $this->overrides->put($change->group->namespace, $change->group->group, $change->key, [$change->locale => null], null, $reason);
            $count++;
        }

        return $count;
    }

    protected function change(TextGroup $group, string $key, string $locale, string $text): Change
    {
        if ($group->namespace !== '*' && ! $group->fromConfig()) {
            return new Change($group, $key, $locale, $text, null, [], null, Change::PLUGIN);
        }

        [$file, $path] = $group->fromConfig()
            ? $this->configLocation($group, $key, $locale)
            : [$this->langPath.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.$group->group.'.php', explode('.', $key)];

        if (! is_file($file)) {
            return new Change($group, $key, $locale, $text, null, $path, $file, Change::MISSING);
        }

        $current = PhpArrayFile::open($file)->stringAt($path);

        $status = match (true) {
            $current === null => Change::MISSING,
            $current === $text => Change::ALREADY_IN_FILE,
            default => Change::WRITABLE,
        };

        return new Change($group, $key, $locale, $text, $current, $path, $file, $status);
    }

    /**
     * `config:mail-access.services.sponsors-des-access` + `messages.page_title` + `fr` : config/mail-access.php, chemin
     * services → sponsors-des-access → messages → page_title → fr.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    protected function configLocation(TextGroup $group, string $key, string $locale): array
    {
        $segments = explode('.', (string) $group->configPath);
        $file = $this->configPath.DIRECTORY_SEPARATOR.array_shift($segments).'.php';

        return [$file, [...$segments, ...explode('.', $key), $locale]];
    }

    /** La valeur relue sur le disque (le fichier tel qu'il est maintenant). */
    protected function fileValue(string $file, array $path): ?string
    {
        return is_file($file) ? PhpArrayFile::open($file)->stringAt($path) : null;
    }
}

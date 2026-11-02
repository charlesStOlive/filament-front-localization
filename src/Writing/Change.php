<?php

namespace CharlesStOlive\FilamentFrontLocalization\Writing;

use CharlesStOlive\FilamentFrontLocalization\Catalog\TextGroup;

/** Un texte modifié d'une langue, et ce que front-texts:write en ferait. */
final class Change
{
    /** Le fichier a une autre valeur à cet endroit : elle sera remplacée. */
    public const WRITABLE = 'writable';

    /** Le fichier a déjà ce texte : la base peut le rendre (prune). */
    public const ALREADY_IN_FILE = 'already';

    /** La clé n'est pas une chaîne simple du fichier de cette langue (absente, expression) : à reporter à la main. */
    public const MISSING = 'missing';

    /** Un texte de plugin : son fichier est dans le plugin. */
    public const PLUGIN = 'plugin';

    /**
     * @param  array<int, string>  $path  le chemin de clés dans le fichier
     */
    public function __construct(
        public readonly TextGroup $group,
        public readonly string $key,
        public readonly string $locale,
        public readonly string $new,
        public readonly ?string $old,
        public readonly array $path,
        public readonly ?string $file,
        public readonly string $status,
    ) {}

    public function qualifiedKey(): string
    {
        return $this->group->qualifiedKey($this->key);
    }
}

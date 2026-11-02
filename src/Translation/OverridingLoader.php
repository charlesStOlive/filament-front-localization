<?php

namespace CharlesStOlive\FilamentFrontLocalization\Translation;

use CharlesStOlive\FilamentFrontLocalization\Catalog\TextCatalog;
use CharlesStOlive\FilamentFrontLocalization\TextOverrides;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Translation\Loader;

/**
 * Le chargeur des traductions de l'application, décoré : les fichiers d'abord (chemins, namespaces des paquets,
 * surcharges lang/vendor : rien ne change), puis, pour un groupe du catalogue seulement, les textes modifiés en base dans
 * la langue demandée. Les autres groupes ne coûtent aucune requête.
 */
class OverridingLoader implements Loader
{
    public function __construct(
        protected Loader $files,
        protected Container $app,
    ) {}

    public function load($locale, $group, $namespace = null): array
    {
        $lines = $this->files->load($locale, $group, $namespace);
        $namespace ??= '*';

        // Les traductions JSON (groupe `*`) ne sont pas au catalogue.
        if ($group === '*' || ! $this->app->make(TextCatalog::class)->hasGroup($namespace, $group)) {
            return $lines;
        }

        return array_replace_recursive($lines, $this->app->make(TextOverrides::class)->linesFor($namespace, $group, $locale));
    }

    /** Les lignes du fichier seul, sans les textes modifiés : la référence que montre l'écran. */
    public function fileLines(string $locale, string $group, string $namespace = '*'): array
    {
        return $this->files->load($locale, $group, $namespace === '*' ? null : $namespace);
    }

    public function addNamespace($namespace, $hint)
    {
        $this->files->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->files->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->files->namespaces();
    }

    /** Le reste (addPath(), paths()…) va au chargeur de fichiers. */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->files->{$method}(...$parameters);
    }
}

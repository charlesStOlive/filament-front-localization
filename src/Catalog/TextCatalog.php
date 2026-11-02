<?php

namespace CharlesStOlive\FilamentFrontLocalization\Catalog;

use Filament\Facades\Filament;
use Throwable;

/**
 * Le catalogue des textes modifiables : ceux que l'application déclare (`filament-front-localization.texts`) et ceux que
 * les plugins des panels déclarent par une méthode statique `frontTexts()`, sans dépendre de ce paquet. L'application
 * l'emporte sur un plugin pour un même groupe. Une clé absente du catalogue n'est pas modifiable, et un groupe absent ne
 * coûte aucune requête (le chargeur ne demande la base que pour les groupes du catalogue).
 */
class TextCatalog
{
    /** @var array<string, TextGroup>|null */
    private ?array $groups = null;

    /** @return array<string, TextGroup> par identifiant (`des/programme`, `filament-map::front`) */
    public function groups(): array
    {
        return $this->groups ??= $this->load();
    }

    public function group(string $namespace, string $group): ?TextGroup
    {
        return $this->groups()[$namespace === '*' ? $group : "{$namespace}::{$group}"] ?? null;
    }

    public function hasGroup(string $namespace, string $group): bool
    {
        return $this->group($namespace, $group) !== null;
    }

    public function definition(string $namespace, string $group, string $key): ?TextDefinition
    {
        return $this->group($namespace, $group)?->definition($key);
    }

    /** Après un changement de config (tests) : le catalogue se relit. */
    public function flush(): void
    {
        $this->groups = null;
    }

    /** @return array<string, TextGroup> */
    private function load(): array
    {
        $declared = (array) config('filament-front-localization.texts', []);

        foreach ($this->pluginDeclarations() as $declaration) {
            $declared += $declaration;
        }

        $groups = [];
        foreach ($declared as $id => $definition) {
            $group = TextGroup::fromArray((string) $id, (array) $definition);
            $groups[$group->id()] = $group;
        }

        return $groups;
    }

    /** @return array<int, array<string, array>> */
    private function pluginDeclarations(): array
    {
        if (! class_exists(Filament::class)) {
            return [];
        }

        $declarations = [];

        try {
            foreach (Filament::getPanels() as $panel) {
                foreach ($panel->getPlugins() as $plugin) {
                    if (method_exists($plugin, 'frontTexts')) {
                        $declarations[] = (array) $plugin::frontTexts();
                    }
                }
            }
        } catch (Throwable) {
            // Pas encore de panel (console, démarrage) : seuls les textes de l'application comptent.
        }

        return $declarations;
    }
}

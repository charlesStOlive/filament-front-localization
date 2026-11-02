<?php

namespace CharlesStOlive\FilamentFrontLocalization\Catalog;

/**
 * Une partie de l'application dont des textes sont modifiables : un groupe Laravel (un fichier de langue, `des/programme`)
 * et son namespace (`*` pour l'application, `filament-map` pour un plugin). Ses valeurs de référence viennent des fichiers
 * de langue, ou d'un chemin de config (`source` : `config:mail-access.services.x`).
 */
final class TextGroup
{
    /**
     * @param  array<string, TextDefinition>  $keys
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $group,
        public readonly string|array|null $label,
        public readonly ?string $configPath,
        public readonly array $keys,
    ) {}

    /** `des/programme` ou `filament-map::front` : l'identifiant du catalogue. */
    public static function fromArray(string $id, array $definition): self
    {
        [$namespace, $group] = str_contains($id, '::') ? explode('::', $id, 2) : ['*', $id];
        $source = $definition['source'] ?? null;

        $keys = [];
        foreach ((array) ($definition['keys'] ?? []) as $key => $keyDefinition) {
            $keys[(string) $key] = TextDefinition::fromArray((string) $key, (array) $keyDefinition);
        }

        return new self(
            namespace: $namespace,
            group: $group,
            label: $definition['label'] ?? null,
            configPath: is_string($source) && str_starts_with($source, 'config:') ? substr($source, 7) : null,
            keys: $keys,
        );
    }

    public function id(): string
    {
        return $this->namespace === '*' ? $this->group : "{$this->namespace}::{$this->group}";
    }

    /** La clé telle que l'écrit le code : `des/programme.thanks.title`, `filament-map::front.search`. */
    public function qualifiedKey(string $key): string
    {
        return "{$this->id()}.{$key}";
    }

    public function definition(string $key): ?TextDefinition
    {
        return $this->keys[$key] ?? null;
    }

    public function fromConfig(): bool
    {
        return $this->configPath !== null;
    }
}

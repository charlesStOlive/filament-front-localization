<?php

namespace CharlesStOlive\FilamentFrontLocalization\Catalog;

/**
 * Une clé modifiable : son libellé et son aide dans le B.O. (clé de traduction ou tableau par langue), et son format
 * (`text` : aucune balise ; `html` : les balises de `html_tags`).
 */
final class TextDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string|array|null $label = null,
        public readonly string|array|null $help = null,
        public readonly string $format = 'text',
    ) {}

    public static function fromArray(string $key, array $definition): self
    {
        return new self(
            key: $key,
            label: $definition['label'] ?? null,
            help: $definition['help'] ?? null,
            format: ($definition['format'] ?? 'text') === 'html' ? 'html' : 'text',
        );
    }

    public function isHtml(): bool
    {
        return $this->format === 'html';
    }
}

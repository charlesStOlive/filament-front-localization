<?php

namespace CharlesStOlive\FilamentFrontLocalization;

use CharlesStOlive\FilamentFrontLocalization\Filament\Pages\FrontTextsPage;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * L'écran « Textes du front » dans un panel :
 *
 *     ->plugin(FilamentFrontLocalizationPlugin::make())
 *
 * Le menu se règle par `filament-front-localization.navigation` (groupe, ordre). Une application qui range ses textes
 * dans ses propres formulaires (Forms\FrontText, dans une page de réglages) s'en passe : `->textsPage(false)`.
 */
class FilamentFrontLocalizationPlugin implements Plugin
{
    protected bool $hasTextsPage = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-front-localization';
    }

    public function textsPage(bool $condition = true): static
    {
        $this->hasTextsPage = $condition;

        return $this;
    }

    public function hasTextsPage(): bool
    {
        return $this->hasTextsPage;
    }

    public function register(Panel $panel): void
    {
        if ($this->hasTextsPage) {
            $panel->pages([FrontTextsPage::class]);
        }
    }

    public function boot(Panel $panel): void {}
}

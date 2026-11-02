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
 * Le menu se règle par `filament-front-localization.navigation` (groupe, ordre).
 */
class FilamentFrontLocalizationPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-front-localization';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([FrontTextsPage::class]);
    }

    public function boot(Panel $panel): void {}
}

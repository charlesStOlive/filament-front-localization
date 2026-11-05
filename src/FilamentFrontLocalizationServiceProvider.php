<?php

namespace CharlesStOlive\FilamentFrontLocalization;

use CharlesStOlive\FilamentFrontLocalization\Catalog\TextCatalog;
use CharlesStOlive\FilamentFrontLocalization\Console\ExportFrontTextsCommand;
use CharlesStOlive\FilamentFrontLocalization\Console\ImportFrontTextsCommand;
use CharlesStOlive\FilamentFrontLocalization\Console\PruneFrontTextsCommand;
use CharlesStOlive\FilamentFrontLocalization\Console\WriteFrontTextsCommand;
use CharlesStOlive\FilamentFrontLocalization\Forms\LocaleSwitcher;
use CharlesStOlive\FilamentFrontLocalization\Forms\Localized;
use CharlesStOlive\FilamentFrontLocalization\Translation\OverridingLoader;
use CharlesStOlive\FilamentFrontLocalization\Writing\FrontTextsWriter;
use Filament\Schemas\Components\Section;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use Illuminate\Support\Js;
use Illuminate\Support\ServiceProvider;

class FilamentFrontLocalizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-front-localization.php', 'filament-front-localization');

        $this->app->singleton(TextCatalog::class);
        $this->app->singleton(TextOverrides::class);
        $this->app->singleton(FrontTexts::class);
        $this->app->bind(FrontTextsWriter::class, fn ($app): FrontTextsWriter => new FrontTextsWriter(
            $app->make(TextCatalog::class),
            $app->make(TextOverrides::class),
            $app->langPath(),
            $app->configPath(),
        ));

        // Le chargeur de Laravel garde ses chemins et ses namespaces ; il reçoit en plus les textes modifiés des groupes
        // du catalogue. Le traducteur, lui, reste celui de Laravel.
        $this->app->extend('translation.loader', fn (Loader $loader, $app): Loader => $loader instanceof OverridingLoader
            ? $loader
            : new OverridingLoader($loader, $app));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-front-localization');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-front-localization');

        $this->registerLocalizedSections();

        FilamentAsset::register([
            Css::make('localized-fields', __DIR__.'/../resources/css/localized-fields.css'),
        ], 'charlesstolive/filament-front-localization');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExportFrontTextsCommand::class,
                ImportFrontTextsCommand::class,
                WriteFrontTextsCommand::class,
                PruneFrontTextsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/filament-front-localization.php' => config_path('filament-front-localization.php'),
            ], 'filament-front-localization-config');
        }
    }

    /**
     * `Section::make()->localized()` : des pastilles de langue dans l'en-tête, que suivent tous les champs Localized de la
     * section (Repeater compris). Les autres champs restent visibles quelle que soit la langue. À appeler après un
     * éventuel afterHeader(), qui sinon remplacerait les pastilles.
     */
    protected function registerLocalizedSections(): void
    {
        Section::macro('localized', function (bool $condition = true): Section {
            /** @var Section $this */
            if (! $condition) {
                return $this;
            }

            $this->extraAttributes([
                Localized::SCOPE_ATTRIBUTE => 'true',
                'x-data' => '{ contentLocale: '.Js::from(app(FrontTexts::class)->defaultLocale()).' }',
            ], merge: true);

            $afterHeader = $this->childComponents[Section::AFTER_HEADER_SCHEMA_KEY] ?? null;

            return $this->afterHeader(fn (Section $component): array => [
                LocaleSwitcher::make(),
                ...Arr::wrap($component->evaluate($afterHeader)),
            ]);
        });
    }
}

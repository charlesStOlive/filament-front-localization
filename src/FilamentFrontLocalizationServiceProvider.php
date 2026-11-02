<?php

namespace CharlesStOlive\FilamentFrontLocalization;

use CharlesStOlive\FilamentFrontLocalization\Catalog\TextCatalog;
use CharlesStOlive\FilamentFrontLocalization\Translation\OverridingLoader;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\ServiceProvider;

class FilamentFrontLocalizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-front-localization.php', 'filament-front-localization');

        $this->app->singleton(TextCatalog::class);
        $this->app->singleton(TextOverrides::class);
        $this->app->singleton(FrontTexts::class);

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

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filament-front-localization.php' => config_path('filament-front-localization.php'),
            ], 'filament-front-localization-config');
        }
    }
}

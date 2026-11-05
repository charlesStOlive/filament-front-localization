<?php

namespace CharlesStOlive\FilamentFrontLocalization\Forms;

use CharlesStOlive\FilamentFrontLocalization\FrontTexts;
use Filament\Schemas\Components\Component;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Illuminate\Support\Js;

/**
 * Les pastilles de langue [FR] [EN] :
 *
 * - dans l'en-tête d'une section `->localized()` : elles changent la langue de tous ses champs par langue ;
 * - à côté du libellé d'un champ Localized seul : elles changent la langue de ce champ, et montrent les langues vides ;
 * - à côté du libellé d'un champ Localized dans une section : la langue de la saisie, sans bouton.
 *
 * Une pastille dont la langue a une erreur de validation passe en rouge. Le changement de langue se fait dans le
 * navigateur (Alpine, `contentLocale`), sans requête.
 */
class LocaleSwitcher extends Component implements HasEmbeddedView
{
    /** La langue de la saisie qui porte ces pastilles (dans un champ Localized), null dans un en-tête de section. */
    protected ?string $locale = null;

    final public function __construct() {}

    public static function make(): static
    {
        $static = app(static::class);
        $static->configure();

        return $static;
    }

    public function forLocale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocalized(): ?Localized
    {
        $component = $this;

        while ($parent = $component->getContainer()->getParentComponent()) {
            if ($parent instanceof Localized) {
                return $parent;
            }

            $component = $parent;
        }

        return null;
    }

    public function toEmbeddedHtml(): string
    {
        $localized = $this->getLocalized();

        if ($localized === null) {
            return $this->renderSectionSwitcher();
        }

        if ($localized->getLocaleScope() !== null) {
            return $this->renderMarker($localized);
        }

        return $this->renderFieldSwitcher($localized);
    }

    /** Dans un champ d'une section `->localized()` : la langue de cette saisie. */
    protected function renderMarker(Localized $localized): string
    {
        $locale = (string) $this->locale;
        $name = $localized->getLocales()[$locale] ?? $locale;
        $classes = 'ffl-locale-marker'.(in_array($locale, $localized->getLocalesWithErrors(), true) ? ' ffl-has-error' : '');

        return '<span class="'.$classes.'" title="'.e($name).'">'.e($this->code($locale)).'</span>';
    }

    protected function renderFieldSwitcher(Localized $localized): string
    {
        $paths = array_map(fn ($field): string => (string) $field->getStatePath(), $localized->getLocaleFields());

        return $this->renderBadges($localized->getLocales(), $localized->getLocalesWithErrors(), $paths, 'ffl-field-switcher');
    }

    /** Dans l'en-tête d'une section : ses langues, et les erreurs de tous ses champs par langue. */
    protected function renderSectionSwitcher(): string
    {
        $scope = $this->getContainer()->getParentComponent();
        $locales = app(FrontTexts::class)->locales();
        $errors = [];

        foreach ($scope?->getChildSchemas() ?? [] as $schema) {
            foreach ($schema->getFlatComponents(withActions: false) as $component) {
                if ($component instanceof Localized) {
                    $errors = [...$errors, ...$component->getLocalesWithErrors()];
                }
            }
        }

        return $this->renderBadges($locales, array_unique($errors), [], 'ffl-section-switcher');
    }

    /**
     * @param  array<string, string>  $locales
     * @param  array<int, string>  $errors
     * @param  array<string, string>  $paths  langue => chemin d'état, pour montrer les langues vides
     */
    protected function renderBadges(array $locales, array $errors, array $paths, string $class): string
    {
        $label = __('filament-front-localization::front-texts.localized.switcher');

        ob_start(); ?>

        <div
            class="ffl-locale-switcher <?= e($class) ?>"
            role="group"
            aria-label="<?= e($label) ?>"
            <?php if ($paths !== []) { ?>
                x-data="{ isLocaleEmpty(path) { const value = $wire.$get(path); return (value === null) || (value === undefined) || (value === '') || ((typeof value === 'object') && (Object.keys(value).length === 0)) } }"
            <?php } ?>
        >
            <?php foreach ($locales as $locale => $name) { ?>
                <?php
                $locale = (string) $locale;
                $js = e(Js::from($locale));
                $hasError = in_array($locale, $errors, true);
                $path = $paths[$locale] ?? null;
                ?>
                <button
                    type="button"
                    class="ffl-locale-badge<?= $hasError ? ' ffl-has-error' : '' ?>"
                    title="<?= e($name) ?>"
                    x-on:click.stop="contentLocale = <?= $js ?>"
                    x-bind:class="{ 'ffl-active': contentLocale === <?= $js ?><?php if ($path !== null) { ?>, 'ffl-empty': isLocaleEmpty(<?= e(Js::from($path)) ?>)<?php } ?> }"
                    x-bind:aria-pressed="contentLocale === <?= $js ?>"
                ><?= e($this->code($locale)) ?><?php if ($hasError) { ?><span class="ffl-locale-badge-error" aria-hidden="true">!</span><?php } ?></button>
            <?php } ?>
        </div>

        <?php return ob_get_clean();
    }

    protected function code(string $locale): string
    {
        return strtoupper(str_replace('_', '-', $locale));
    }
}

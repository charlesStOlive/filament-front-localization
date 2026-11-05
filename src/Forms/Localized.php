<?php

namespace CharlesStOlive\FilamentFrontLocalization\Forms;

use CharlesStOlive\FilamentFrontLocalization\FrontTexts;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

/**
 * Un champ par langue, sous un seul nom : `Localized::make(TextInput::make('donate_url'))` édite `donate_url.fr`,
 * `donate_url.en`… Toutes les langues sont dans l'état du formulaire en même temps ; on n'en voit qu'une, et on change
 * de langue sans requête.
 *
 * - Seul, le champ porte ses pastilles de langue à côté de son libellé ([FR] [EN]).
 * - Dans une section `->localized()`, il suit les pastilles de la section et n'affiche que la langue de sa saisie.
 *
 * L'état est un tableau par langue, partout où un tableau se range : une colonne JSON (cast array), un élément de
 * Repeater ou de Builder, un Group avec statePath(), une propriété `array` de spatie/laravel-settings, un attribut
 * traduisible de spatie/laravel-translatable (lu par getTranslations(), enregistré par setTranslations()). Le champ
 * enveloppé peut être n'importe quel Field, un Repeater compris (une liste par langue).
 *
 * Validation : les règles du champ valent pour chaque langue ; requiredDefault() / requiredAll() / requiredLocales()
 * décident des langues obligatoires. Un message nomme la langue (« lien de don (English) »).
 */
class Localized extends Component implements HasEmbeddedView
{
    /** L'attribut que pose une section `->localized()` : les champs par langue qu'elle contient la suivent. */
    public const SCOPE_ATTRIBUTE = 'data-ffl-locale-scope';

    protected Field $field;

    /** @var array<int, string> | Closure | null */
    protected array|Closure|null $onlyLocales = null;

    /** @var array<int, string> | string | Closure | null 'default', 'all', une liste de langues ; null : comme le champ */
    protected array|string|Closure|null $requiredLocales = null;

    protected bool|Closure $hasFallbackPlaceholder = true;

    final public function __construct(Field $field)
    {
        $this->field = $field;
    }

    public static function make(Field $field): static
    {
        $static = app(static::class, ['field' => $field]);
        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Sans entrée par défaut, Filament ne parcourt pas les schémas des langues (état, validation, rendu).
        $this->childComponents([]);
    }

    /** Seulement ces langues pour ce champ (parmi celles de FrontTexts::locales()). */
    public function locales(array|Closure|null $locales): static
    {
        $this->onlyLocales = $locales;

        return $this;
    }

    /** La langue de référence est obligatoire, les autres non : le front reprend la référence quand elles sont vides. */
    public function requiredDefault(): static
    {
        $this->requiredLocales = 'default';

        return $this;
    }

    public function requiredAll(): static
    {
        $this->requiredLocales = 'all';

        return $this;
    }

    /** @param  array<int, string> | Closure  $locales */
    public function requiredLocales(array|Closure $locales): static
    {
        $this->requiredLocales = $locales;

        return $this;
    }

    /** Une langue vide montre en grisé (placeholder) la valeur de la langue de référence : ce que le front affichera. */
    public function fallbackPlaceholder(bool|Closure $condition = true): static
    {
        $this->hasFallbackPlaceholder = $condition;

        return $this;
    }

    public function getField(): Field
    {
        return $this->field;
    }

    /** @return array<string, string> code => nom */
    public function getLocales(): array
    {
        $all = app(FrontTexts::class)->locales();
        $only = $this->evaluate($this->onlyLocales);

        return is_array($only) && $only !== [] ? Arr::only($all, $only) : $all;
    }

    public function getDefaultLocale(): string
    {
        $default = app(FrontTexts::class)->defaultLocale();
        $codes = array_map('strval', array_keys($this->getLocales()));

        return in_array($default, $codes, true) ? $default : $codes[0];
    }

    /** Le chemin du champ enveloppé, relatif à son conteneur : `donate_url`. */
    public function getBaseStatePath(): string
    {
        return (string) $this->field->getStatePath(isAbsolute: false);
    }

    public function isLocaleRequired(string $locale): ?bool
    {
        $required = $this->evaluate($this->requiredLocales);

        return match (true) {
            $required === null => null,
            $required === 'default' => $locale === $this->getDefaultLocale(),
            $required === 'all' => true,
            default => in_array($locale, (array) $required, true),
        };
    }

    public function hasFallbackPlaceholder(): bool
    {
        return (bool) $this->evaluate($this->hasFallbackPlaceholder);
    }

    /** @return array<string, Schema> une par langue, le champ dedans */
    public function getDefaultChildSchemas(): array
    {
        $schemas = [];

        foreach (array_keys($this->getLocales()) as $locale) {
            $locale = (string) $locale;

            $schemas[$locale] = $this->makeChildSchema($locale)->components([$this->makeLocaleField($locale)]);
        }

        return $schemas;
    }

    protected function makeLocaleField(string $locale): Field
    {
        $base = $this->getBaseStatePath();
        $default = $this->getDefaultLocale();
        $localeName = $this->getLocales()[$locale] ?? $locale;
        $field = $this->field->getClone();

        $field->statePath("{$base}.{$locale}");

        if (($required = $this->isLocaleRequired($locale)) !== null) {
            $field->required($required);
        }

        $ownAttribute = (fn () => $this->validationAttribute)->call($field);
        $field->validationAttribute(function (Field $component) use ($ownAttribute, $localeName): string {
            $attribute = $component->evaluate($ownAttribute);

            if (blank($attribute)) {
                $label = $component->getLabel();
                $attribute = Str::lcfirst($label instanceof Htmlable ? $component->getDefaultLabel() : (string) $label);
            }

            return "{$attribute} ({$localeName})";
        });

        // Ce que le champ mettait déjà après son libellé, puis les pastilles (ou la langue de la saisie, dans une section).
        $afterLabel = (fn () => $this->childComponents[Field::AFTER_LABEL_SCHEMA_KEY] ?? null)->call($field);
        $field->afterLabel(fn (Field $component): array => [
            ...Arr::wrap($component->evaluate($afterLabel)),
            LocaleSwitcher::make()->forLocale($locale),
        ]);

        if (($locale !== $default) && method_exists($field, 'placeholder') && $this->hasFallbackPlaceholder()
            && blank((fn () => $this->placeholder ?? null)->call($field))) {
            $field->placeholder(function (Get $get) use ($base, $default): ?string {
                $fallback = $get("{$base}.{$default}");

                return is_scalar($fallback) && filled($fallback) ? (string) $fallback : null;
            });
        }

        $this->configureLocaleField($field, $locale);

        return $field;
    }

    /** Pour une sous-classe : ce qu'il faut de plus au champ d'une langue (FrontText : le texte d'origine, ses règles). */
    protected function configureLocaleField(Field $field, string $locale): void {}

    /** Le champ de chaque langue : code => champ. @return array<string, Field> */
    public function getLocaleFields(): array
    {
        $fields = [];

        foreach ($this->getChildSchemas(withHidden: true) as $locale => $schema) {
            $field = $schema->getComponents(withActions: false, withHidden: true)[0] ?? null;

            if ($field instanceof Field) {
                $fields[(string) $locale] = $field;
            }
        }

        return $fields;
    }

    /** Les langues dont la saisie a une erreur de validation (elle-même ou ce qu'elle contient : un Repeater par langue). */
    public function getLocalesWithErrors(): array
    {
        $keys = $this->getLivewire()->getErrorBag()->keys();

        if ($keys === []) {
            return [];
        }

        $locales = [];

        foreach ($this->getLocaleFields() as $locale => $field) {
            $path = $field->getStatePath();

            foreach ($keys as $key) {
                if (($key === $path) || str_starts_with($key, "{$path}.")) {
                    $locales[] = $locale;

                    break;
                }
            }
        }

        return $locales;
    }

    /** La section (ou un autre composant) `->localized()` qui choisit la langue de ce champ, s'il y en a une. */
    public function getLocaleScope(): ?Component
    {
        $component = $this;

        while ($parent = $component->getContainer()->getParentComponent()) {
            if (filled($parent->getExtraAttributes()[self::SCOPE_ATTRIBUTE] ?? null)) {
                return $parent;
            }

            $component = $parent;
        }

        return null;
    }

    /**
     * Un attribut traduisible de spatie/laravel-translatable arrive comme une chaîne (la langue en cours) : on le
     * remplace par toutes ses langues avant que les champs le lisent. Seulement au niveau du modèle lui-même, pas dans
     * un élément de Repeater JSON ou un Group à statePath, qui ont leur propre tableau.
     */
    public function hydrateState(?array &$hydratedDefaultState, bool $shouldCallHydrationHooks = true, bool $shouldApplyStateCasts = true, array &$appliedStateCastPaths = []): void
    {
        if ($hydratedDefaultState === null) {
            $this->loadTranslationsFromRecord() || $this->wrapUnlocalizedState();
        }

        parent::hydrateState($hydratedDefaultState, $shouldCallHydrationHooks, $shouldApplyStateCasts, $appliedStateCastPaths);
    }

    /** Le chemin absolu du tableau par langue : `data.donate_url`. */
    public function getLocalizedStatePath(): string
    {
        $container = $this->getContainer()->getStatePath();

        return filled($container) ? "{$container}.{$this->getBaseStatePath()}" : $this->getBaseStatePath();
    }

    protected function loadTranslationsFromRecord(): bool
    {
        $attribute = $this->getBaseStatePath();
        $record = $this->getTranslatableRecord();

        if (($record === null) || str_contains($attribute, '.') || (! $record->isTranslatableAttribute($attribute))) {
            return false;
        }

        $path = $this->getLocalizedStatePath();
        $livewire = $this->getLivewire();

        if ($this->isLocalizedValue(data_get($livewire, $path))) {
            return true;
        }

        $values = [];

        foreach (array_keys($this->getLocales()) as $locale) {
            $value = $record->getTranslation($attribute, (string) $locale, false);
            $values[(string) $locale] = filled($value) ? $value : null;
        }

        data_set($livewire, $path, $values);

        return true;
    }

    /**
     * Une valeur enregistrée avant que le champ passe par langue (une chaîne, ou la liste d'un Repeater) devient celle de
     * la langue de référence, au lieu d'être perdue au prochain enregistrement.
     */
    protected function wrapUnlocalizedState(): void
    {
        $path = $this->getLocalizedStatePath();
        $livewire = $this->getLivewire();
        $value = data_get($livewire, $path);

        if (blank($value) || $this->isLocalizedValue($value)) {
            return;
        }

        $values = array_fill_keys(array_map('strval', array_keys($this->getLocales())), null);
        $values[$this->getDefaultLocale()] = $value;

        data_set($livewire, $path, $values);
    }

    /** Un tableau dont les clés sont des langues. */
    protected function isLocalizedValue(mixed $value): bool
    {
        return is_array($value)
            && array_intersect(array_map('strval', array_keys($value)), array_map('strval', array_keys($this->getLocales()))) !== [];
    }

    /** Le modèle traduisible dont ce champ édite directement un attribut, sans statePath entre les deux. */
    protected function getTranslatableRecord(): ?Model
    {
        $schema = $this->getContainer();

        while (true) {
            $record = $schema->getRecord(withParentComponentRecord: false);

            if ($record instanceof Model) {
                return method_exists($record, 'isTranslatableAttribute') && method_exists($record, 'getTranslation') ? $record : null;
            }

            if (filled($schema->getStatePath(isAbsolute: false)) || ! ($parent = $schema->getParentComponent())) {
                return null;
            }

            if (filled($parent->getStatePath(isAbsolute: false))) {
                return null;
            }

            $schema = $parent->getContainer();
        }
    }

    public function toEmbeddedHtml(): string
    {
        $isScoped = $this->getLocaleScope() !== null;

        $attributes = (new FilamentComponentAttributeBag)
            ->merge(['id' => $this->getId()], escape: false)
            ->merge($isScoped ? [] : ['x-data' => '{ contentLocale: '.Js::from($this->getDefaultLocale()).' }'], escape: false)
            ->merge($this->getExtraAttributes(), escape: false)
            ->class(['ffl-localized']);

        ob_start(); ?>

        <div <?= $attributes->toHtml() ?>>
            <?php foreach ($this->getChildSchemas() as $locale => $schema) { ?>
                <div
                    data-ffl-locale="<?= e($locale) ?>"
                    x-show="contentLocale === <?= e(Js::from((string) $locale)) ?>"
                    x-on:expand="contentLocale = <?= e(Js::from((string) $locale)) ?>"
                >
                    <?= $schema->toHtml() ?>
                </div>
            <?php } ?>
        </div>

        <?php return ob_get_clean();
    }
}

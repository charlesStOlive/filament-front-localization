<?php

namespace CharlesStOlive\FilamentFrontLocalization\Forms;

use CharlesStOlive\FilamentFrontLocalization\Catalog\TextDefinition;
use CharlesStOlive\FilamentFrontLocalization\Catalog\TextGroup;
use CharlesStOlive\FilamentFrontLocalization\FrontTexts;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Un texte du front dans un formulaire : `FrontText::make('des/programme', 'thanks.title')`. Il s'affiche comme un champ
 * Localized (pastilles de langue, section `->localized()`), mais ce n'est pas une donnée du formulaire :
 *
 * - le fichier de langue (ou la config d'un groupe `config:`) reste la référence, montrée en grisé ; un champ vide
 *   affiche le texte d'origine ;
 * - la valeur saisie est la surcharge de `front_text_overrides`, validée comme dans l'écran « Textes du front »
 *   (paramètres, pluriels, format) et écrite par FrontTexts::save() avec son historique ;
 * - elle ne part pas avec les données de la page : celle-ci appelle FrontText::saveAll() après son propre enregistrement,
 *   dans la même transaction (Filament\Concerns\SavesFrontTexts).
 *
 * Le catalogue (`filament-front-localization.texts`) reste la liste des clés modifiables : le formulaire ne fait que les
 * citer ; libellé, aide et format viennent de lui.
 */
class FrontText extends Localized
{
    /** La racine de l'état des textes dans le formulaire, à retirer des données que la page enregistre. */
    public const STATE_KEY = 'front_texts';

    protected string $textGroupId;

    protected string $textKey;

    /** @param  string  $field  le groupe du catalogue (`des/programme`) */
    public static function make(Field|string $field, ?string $key = null): static
    {
        if (! is_string($field) || blank($key)) {
            throw new InvalidArgumentException("FrontText::make('des/programme', 'thanks.title') : un groupe et une clé du catalogue.");
        }

        [$group, $definition] = static::resolveText($field, $key);

        $static = app(static::class, ['field' => static::makeField($group, $definition)]);
        $static->textGroupId = $group->id();
        $static->textKey = $key;
        $static->configure();

        return $static;
    }

    /**
     * Les textes d'un groupe du catalogue, dans son ordre. `$only` : des clés ou des motifs (`collecte.*`).
     *
     * @param  array<int, string>  $only
     * @return array<int, static>
     */
    public static function group(string $groupId, array $only = []): array
    {
        $group = app(FrontTexts::class)->catalog()->groups()[$groupId]
            ?? throw new InvalidArgumentException("Le groupe « {$groupId} » n'est pas dans le catalogue (filament-front-localization.texts).");

        $keys = array_map('strval', array_keys($group->keys));

        if ($only !== []) {
            $keys = array_values(array_filter($keys, fn (string $key): bool => Str::is($only, $key)));
        }

        return array_map(fn (string $key): static => static::make($groupId, $key), $keys);
    }

    /**
     * Les textes d'un formulaire (ceux qu'on voit : un onglet non ouvert compte, un champ hidden() non).
     *
     * @return array<int, self>
     */
    public static function in(Schema $schema, bool $withHidden = false): array
    {
        return array_values(array_filter(
            $schema->getFlatComponents(withActions: false, withHidden: $withHidden),
            fn ($component): bool => $component instanceof self,
        ));
    }

    /** Enregistre les textes modifiés d'un formulaire (ceux qui ont changé seulement : l'historique ne note que ça). */
    public static function saveAll(Schema $schema, ?int $userId = null): void
    {
        foreach (static::in($schema) as $text) {
            if (! $text->isDisabled()) {
                $text->saveText($userId ?? auth()->id());
            }
        }
    }

    /** Le texte qui contient ce composant (son champ d'une langue, une action ou une étiquette près du libellé). */
    public static function closest(Component $component): ?self
    {
        while ($parent = $component->getContainer()->getParentComponent()) {
            if ($parent instanceof self) {
                return $parent;
            }

            $component = $parent;
        }

        return null;
    }

    /** @return array{0: TextGroup, 1: TextDefinition} */
    protected static function resolveText(string $groupId, string $key): array
    {
        $group = app(FrontTexts::class)->catalog()->groups()[$groupId] ?? null;
        $definition = $group?->definition($key);

        if ($definition === null) {
            throw new InvalidArgumentException("« {$groupId}.{$key} » n'est pas dans le catalogue (filament-front-localization.texts).");
        }

        return [$group, $definition];
    }

    /** Un champ texte, ou une zone de texte pour un texte long, sur plusieurs lignes ou en html (`input` du catalogue sinon). */
    protected static function makeField(TextGroup $group, TextDefinition $definition): Field
    {
        $texts = app(FrontTexts::class);
        $path = self::STATE_KEY.'.'.str_replace(['::', '/'], ['--', '__'], $group->id()).'.'.$definition->key;
        $reference = (string) $texts->reference($group, $definition->key, $texts->defaultLocale());
        $input = $definition->input
            ?? ((mb_strlen($reference) > 80 || str_contains($reference, "\n") || $definition->isHtml()) ? 'textarea' : 'text');

        $field = $input === 'textarea' ? Textarea::make($path)->rows(2)->autosize() : TextInput::make($path);

        return $field
            ->label($texts->label($definition->label) ?? $definition->key)
            ->afterLabel([
                Text::make(__('filament-front-localization::front-texts.modified'))
                    ->badge()
                    ->color('warning')
                    ->visible(fn (Component $component): bool => (bool) static::closest($component)?->isModified()),
                Action::make('resetFrontText')
                    ->label(__('filament-front-localization::front-texts.actions.reset'))
                    ->tooltip(__('filament-front-localization::front-texts.localized.reset_hint'))
                    ->iconButton()
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn (Component $component): bool => (bool) static::closest($component)?->hasValues())
                    ->action(fn (Component $component) => static::closest($component)?->clearValues()),
                Action::make('frontTextHistory')
                    ->label(__('filament-front-localization::front-texts.actions.history'))
                    ->tooltip(__('filament-front-localization::front-texts.actions.history'))
                    ->iconButton()
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->modalHeading(fn (Component $component): ?string => static::closest($component)?->getTextLabel())
                    ->modalContent(fn (Component $component) => view('filament-front-localization::history', [
                        'changes' => static::closest($component)?->getHistory() ?? collect(),
                        'locales' => $texts->locales(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament-actions::modal.actions.close.label')),
            ]);
    }

    public function getTextGroup(): TextGroup
    {
        return static::resolveText($this->textGroupId, $this->textKey)[0];
    }

    public function getTextGroupId(): string
    {
        return $this->textGroupId;
    }

    public function getTextKey(): string
    {
        return $this->textKey;
    }

    public function getDefinition(): TextDefinition
    {
        return static::resolveText($this->textGroupId, $this->textKey)[1];
    }

    public function getTextLabel(): string
    {
        return app(FrontTexts::class)->label($this->getDefinition()->label) ?? $this->textKey;
    }

    /** Le texte d'origine montré en grisé : c'est lui qu'on voit quand la valeur reste vide. */
    public function hasFallbackPlaceholder(): bool
    {
        return false;
    }

    protected function configureLocaleField(Field $field, string $locale): void
    {
        $texts = app(FrontTexts::class);
        $definition = $this->getDefinition();
        $reference = $texts->reference($this->getTextGroup(), $this->textKey, $locale);
        $params = $texts->params((string) $reference);

        $hints = array_filter([
            $texts->label($definition->help),
            $reference === null ? __('filament-front-localization::front-texts.form.reference_missing') : null,
            $params !== [] ? __('filament-front-localization::front-texts.form.params', ['params' => implode(', ', $params)]) : null,
            str_contains((string) $reference, '|') ? __('filament-front-localization::front-texts.form.plural') : null,
        ]);

        // Une phrase par aide : « À garder tels quels : :name » n'a pas de point final.
        $hints = array_map(fn (string $hint): string => preg_match('/[.!?…]$/u', $hint) ? $hint : "{$hint}.", $hints);

        $field
            ->dehydrated(false)
            ->required(false)
            ->helperText($hints === [] ? null : implode(' ', $hints))
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($texts, $definition, $reference): void {
                if (filled($value) && ($error = $texts->validate($definition, $reference, trim((string) $value)))) {
                    $fail($error);
                }
            });

        if (method_exists($field, 'placeholder')) {
            $field->placeholder($reference);
        }
    }

    /** Après les champs (qui n'ont rien dans les données de la page) : les textes modifiés en base, langue par langue. */
    public function hydrateState(?array &$hydratedDefaultState, bool $shouldCallHydrationHooks = true, bool $shouldApplyStateCasts = true, array &$appliedStateCastPaths = []): void
    {
        parent::hydrateState($hydratedDefaultState, $shouldCallHydrationHooks, $shouldApplyStateCasts, $appliedStateCastPaths);

        $livewire = $this->getLivewire();
        data_set($livewire, $this->getLocalizedStatePath(), $this->getStoredValues());
    }

    /** @return array<string, ?string> langue => texte modifié en base (null : le texte d'origine) */
    public function getStoredValues(): array
    {
        $locales = array_map('strval', array_keys($this->getLocales()));
        $stored = app(FrontTexts::class)->values($this->getTextGroup(), $this->textKey);

        return array_replace(array_fill_keys($locales, null), array_intersect_key($stored, array_flip($locales)));
    }

    /** Un texte modifié en base, dans au moins une langue. */
    public function isModified(): bool
    {
        return array_filter($this->getStoredValues(), filled(...)) !== [];
    }

    /** @return array<string, ?string> langue => ce qui est saisi (null : vide, le texte d'origine) */
    public function getFormValues(): array
    {
        $state = data_get($this->getLivewire(), $this->getLocalizedStatePath());
        $values = [];

        foreach (array_keys($this->getLocales()) as $locale) {
            $value = is_array($state) && is_string($state[$locale] ?? null) ? trim($state[$locale]) : '';
            $values[(string) $locale] = $value === '' ? null : $value;
        }

        return $values;
    }

    public function hasValues(): bool
    {
        return array_filter($this->getFormValues(), filled(...)) !== [];
    }

    /** Vide toutes les langues dans le formulaire : le texte d'origine revient à l'enregistrement. */
    public function clearValues(): void
    {
        $livewire = $this->getLivewire();
        data_set($livewire, $this->getLocalizedStatePath(), array_fill_keys(array_keys($this->getFormValues()), null));
    }

    public function saveText(?int $userId = null): void
    {
        $values = $this->getFormValues();

        if ($values === $this->getStoredValues()) {
            return;
        }

        $texts = app(FrontTexts::class);

        if (array_filter($values, filled(...)) === []) {
            $texts->reset($this->textGroupId, $this->textKey, $userId);

            return;
        }

        $texts->save($this->textGroupId, $this->textKey, $values, $userId);
    }

    public function getHistory(): iterable
    {
        return app(FrontTexts::class)->history($this->textGroupId, $this->textKey);
    }
}

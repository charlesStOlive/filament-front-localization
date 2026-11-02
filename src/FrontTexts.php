<?php

namespace CharlesStOlive\FilamentFrontLocalization;

use CharlesStOlive\FilamentFrontLocalization\Catalog\TextCatalog;
use CharlesStOlive\FilamentFrontLocalization\Catalog\TextDefinition;
use CharlesStOlive\FilamentFrontLocalization\Catalog\TextGroup;
use CharlesStOlive\FilamentFrontLocalization\Models\FrontTextChange;
use CharlesStOlive\FilamentFrontLocalization\Translation\OverridingLoader;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;

/**
 * Les textes du front modifiables : ce que lit l'écran (référence, valeur affichée, journal), ce qu'il écrit (après
 * validation), et ce que demande le code qui lit ses textes ailleurs que dans les fichiers de langue (override()).
 */
class FrontTexts
{
    public function __construct(
        protected TextCatalog $catalog,
        protected TextOverrides $overrides,
    ) {}

    /** @return array<string, string> code => nom, les langues éditées */
    public function locales(): array
    {
        $configured = config('filament-front-localization.locales');

        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        $front = config('livewire-localization.locales');

        return is_array($front) && $front !== [] ? $front : [App::getLocale() => App::getLocale()];
    }

    public function catalog(): TextCatalog
    {
        return $this->catalog;
    }

    /**
     * Le texte modifié d'une clé du catalogue dans une langue (la langue en cours par défaut), ou null. Pour le code dont
     * les textes ne passent pas par les fichiers de langue (une config par langue, comme MailAccess) :
     *
     *     $text = app(FrontTexts::class)->override('mail-access/sponsors-des-access', 'messages.page_title') ?? $configValue;
     */
    public function override(string $group, string $key, ?string $locale = null, string $namespace = '*'): ?string
    {
        if ($this->catalog->definition($namespace, $group, $key) === null) {
            return null;
        }

        return $this->overrides->value($namespace, $group, $key, $locale ?? App::getLocale());
    }

    /** Le texte d'origine d'une clé dans une langue : le fichier de langue, ou la config pour un groupe `config:`. */
    public function reference(TextGroup $group, string $key, string $locale): ?string
    {
        if ($group->fromConfig()) {
            $value = data_get(config($group->configPath), $key);

            if (is_array($value)) {
                $value = $value[$locale] ?? null;
            }

            return is_string($value) ? $value : null;
        }

        $loader = app('translation.loader');
        $lines = $loader instanceof OverridingLoader
            ? $loader->fileLines($locale, $group->group, $group->namespace)
            : $loader->load($locale, $group->group, $group->namespace === '*' ? null : $group->namespace);

        $value = Arr::get($lines, $key);

        return is_string($value) ? $value : null;
    }

    /** Ce que voit le visiteur : le texte modifié, sinon l'origine. */
    public function effective(TextGroup $group, string $key, string $locale): ?string
    {
        return $this->overrides->value($group->namespace, $group->group, $key, $locale) ?? $this->reference($group, $key, $locale);
    }

    /** @return array<string, string> langue => texte modifié */
    public function values(TextGroup $group, string $key): array
    {
        return $this->overrides->values($group->namespace, $group->group, $key);
    }

    /**
     * Les lignes de l'écran : une par clé du catalogue.
     *
     * @return array<string, array<string, mixed>>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ($this->catalog->groups() as $group) {
            $values = $this->overrides->group($group->namespace, $group->group);

            foreach ($group->keys as $key => $definition) {
                $row = [
                    'id' => $group->qualifiedKey($key),
                    'group_id' => $group->id(),
                    'key' => $key,
                    'group_label' => $this->label($group->label) ?? $group->id(),
                    'label' => $this->label($definition->label) ?? $key,
                    'help' => $this->label($definition->help),
                    'modified' => ($values[$key] ?? []) !== [],
                ];

                foreach (array_keys($this->locales()) as $locale) {
                    $row["value_{$locale}"] = $values[$key][$locale] ?? $this->reference($group, $key, $locale);
                    $row["modified_{$locale}"] = isset($values[$key][$locale]);
                }

                $rows[$row['id']] = $row;
            }
        }

        return $rows;
    }

    /**
     * Enregistre les textes d'une clé du catalogue, langue par langue (vide : le texte d'origine). Refuse une clé hors
     * catalogue et une valeur invalide, même par un appel direct.
     *
     * @param  array<string, ?string>  $values  langue => texte
     *
     * @throws ValidationException
     */
    public function save(string $groupId, string $key, array $values, ?int $userId = null): void
    {
        [$group, $definition] = $this->resolve($groupId, $key);
        $errors = [];
        $clean = [];

        foreach ($this->locales() as $locale => $name) {
            if (! array_key_exists($locale, $values)) {
                continue;
            }

            $value = is_string($values[$locale]) ? trim($values[$locale]) : null;

            if ($value !== null && $value !== '' && ($error = $this->validate($definition, $this->reference($group, $key, $locale), $value))) {
                $errors["values.{$locale}"] = $error;
            }

            $clean[$locale] = $value === '' ? null : $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->overrides->put($group->namespace, $group->group, $key, $clean, $userId);
    }

    /** Rend toutes les langues d'une clé du catalogue au texte d'origine. */
    public function reset(string $groupId, string $key, ?int $userId = null): void
    {
        [$group] = $this->resolve($groupId, $key);

        $this->overrides->forget($group->namespace, $group->group, $key, $userId);
    }

    /** @return Collection<int, FrontTextChange> les derniers changements d'une clé */
    public function history(string $groupId, string $key, int $limit = 30): Collection
    {
        [$group] = $this->resolve($groupId, $key);

        return FrontTextChange::query()
            ->with('user')
            ->where(['namespace' => $group->namespace, 'group' => $group->group, 'key' => $key])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Ce qui ne va pas dans un texte, ou null : les paramètres (:name) et le nombre de formes du pluriel (|) du texte
     * d'origine, aucun paramètre inconnu, pas de balise (format text) ou seulement celles de `html_tags` (format html).
     */
    public function validate(TextDefinition $definition, ?string $reference, string $value): ?string
    {
        $expected = $this->params((string) $reference);
        $given = $this->params($value);

        if ($missing = array_diff($expected, $given)) {
            return __('filament-front-localization::front-texts.validation.params', ['params' => implode(', ', $missing)]);
        }

        if ($unknown = array_diff($given, $expected)) {
            return __('filament-front-localization::front-texts.validation.unknown_params', ['params' => implode(', ', $unknown)]);
        }

        if ($reference !== null && substr_count($reference, '|') !== substr_count($value, '|')) {
            return __('filament-front-localization::front-texts.validation.plural', ['count' => substr_count($reference, '|') + 1]);
        }

        if (! $definition->isHtml() && preg_match('/<\s*\/?\s*[a-z!]/i', $value)) {
            return __('filament-front-localization::front-texts.validation.no_html');
        }

        $tags = (string) config('filament-front-localization.html_tags', '');

        if ($definition->isHtml() && strip_tags($value, $tags) !== $value) {
            return __('filament-front-localization::front-texts.validation.html_tags', ['tags' => $tags]);
        }

        return null;
    }

    /** @return array<int, string> les paramètres d'un texte (`:name`), dans l'ordre, sans doublon */
    public function params(string $text): array
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $text, $matches);

        return array_values(array_unique(array_map(fn (string $name): string => ":{$name}", $matches[1])));
    }

    /** Un libellé du catalogue : un tableau par langue (la langue du panel, sinon la première), ou une clé de traduction. */
    public function label(string|array|null $label): ?string
    {
        if (is_array($label)) {
            return $label[App::getLocale()] ?? (reset($label) ?: null);
        }

        return $label === null ? null : __($label);
    }

    /**
     * @return array{0: TextGroup, 1: TextDefinition}
     *
     * @throws ValidationException
     */
    protected function resolve(string $groupId, string $key): array
    {
        $group = $this->catalog->groups()[$groupId] ?? null;
        $definition = $group?->definition($key);

        if ($group === null || $definition === null) {
            throw ValidationException::withMessages(['key' => __('filament-front-localization::front-texts.validation.not_editable')]);
        }

        return [$group, $definition];
    }
}

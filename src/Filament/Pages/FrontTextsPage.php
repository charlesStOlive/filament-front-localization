<?php

namespace CharlesStOlive\FilamentFrontLocalization\Filament\Pages;

use BackedEnum;
use CharlesStOlive\FilamentFrontLocalization\Catalog\TextGroup;
use CharlesStOlive\FilamentFrontLocalization\FrontTexts;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * « Textes du front » : les clés du catalogue, leur texte dans chaque langue, modifiables langue par langue (un champ vide
 * reprend le texte d'origine), avec retour au texte d'origine et journal.
 *
 * Une seule permission, celle de la page : avec filament-permission-manager, `pages.fronttexts.viewany` (déclarée par
 * $requiresPermission, sans dépendance) ; sans lui, tout utilisateur du panel. Chaque action la revérifie.
 */
class FrontTextsPage extends Page implements HasTable
{
    use InteractsWithTable;

    public static bool $requiresPermission = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $slug = 'front-texts';

    public static function canAccess(): bool
    {
        return ! Gate::has(static::class) || Gate::allows(static::class);
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-front-localization::front-texts.navigation');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = config('filament-front-localization.navigation.group');

        return is_string($group) ? __($group) : $group;
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-front-localization.navigation.sort');
    }

    public function getTitle(): string
    {
        return __('filament-front-localization::front-texts.title');
    }

    public function getSubheading(): ?string
    {
        return __('filament-front-localization::front-texts.description');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $texts = app(FrontTexts::class);
        $locales = $texts->locales();

        $columns = [
            TextColumn::make('group_label')
                ->label(__('filament-front-localization::front-texts.columns.group'))
                ->badge()
                ->color('gray'),
            TextColumn::make('label')
                ->label(__('filament-front-localization::front-texts.columns.text'))
                ->weight('medium')
                ->description(fn (array $record): ?string => $record['help'])
                ->wrap(),
        ];

        foreach ($locales as $code => $name) {
            $columns[] = TextColumn::make("value_{$code}")
                ->label(Str::upper($code))
                ->tooltip($name)
                ->limit(90)
                ->wrap()
                ->color(fn (array $record): ?string => $record["modified_{$code}"] ? 'primary' : null)
                ->icon(fn (array $record): ?Heroicon => $record["modified_{$code}"] ? Heroicon::OutlinedPencilSquare : null);
        }

        return $table
            ->records(fn (?string $search, array $filters): Collection => $this->records($search, $filters))
            ->searchable()
            ->columns($columns)
            ->filters([
                SelectFilter::make('group')
                    ->label(__('filament-front-localization::front-texts.filters.group'))
                    ->options(fn (): array => collect($texts->catalog()->groups())
                        ->mapWithKeys(fn ($group): array => [$group->id() => $texts->label($group->label) ?? $group->id()])
                        ->all()),
                Filter::make('modified')
                    ->label(__('filament-front-localization::front-texts.filters.modified'))
                    ->toggle(),
            ])
            ->recordActions([
                $this->editAction(),
                $this->resetAction(),
                $this->historyAction(),
            ])
            ->paginated(false);
    }

    /** Les lignes visibles : tout le catalogue (il est sous la permission de la page), filtré et cherché. */
    protected function records(?string $search, array $filters): Collection
    {
        abort_unless(static::canAccess(), 403);

        $locales = array_keys(app(FrontTexts::class)->locales());

        return collect(app(FrontTexts::class)->rows())
            ->when(filled($filters['group']['value'] ?? null), fn (Collection $rows): Collection => $rows
                ->where('group_id', $filters['group']['value']))
            ->when($filters['modified']['isActive'] ?? false, fn (Collection $rows): Collection => $rows
                ->where('modified', true))
            ->when(filled($search), fn (Collection $rows): Collection => $rows->filter(function (array $row) use ($search, $locales): bool {
                $haystack = [$row['id'], $row['label'], $row['group_label'], ...array_map(fn (string $code): string => (string) $row["value_{$code}"], $locales)];

                return Str::contains(Str::lower(implode(' ', $haystack)), Str::lower((string) $search));
            }));
    }

    protected function editAction(): Action
    {
        return Action::make('edit')
            ->label(__('filament-front-localization::front-texts.actions.edit'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(fn (array $record): string => $record['label'])
            ->modalDescription(fn (array $record): string => trim(($record['help'] ? $record['help'].' — ' : '')
                .__('filament-front-localization::front-texts.form.technical_key', ['key' => $record['id']])))
            ->fillForm(fn (array $record): array => ['values' => $this->texts()->values($this->group($record), $record['key'])])
            ->schema(fn (array $record): array => [$this->editTabs($record)])
            ->action(function (array $record, array $data): void {
                abort_unless(static::canAccess(), 403);

                $this->texts()->save($record['group_id'], $record['key'], (array) ($data['values'] ?? []), auth()->id());

                Notification::make()->success()->title(__('filament-front-localization::front-texts.notifications.saved'))->send();
            });
    }

    /** Un onglet par langue : le texte affiché (vide : celui d'origine), l'origine en rappel, les paramètres à garder. */
    protected function editTabs(array $record): Tabs
    {
        $texts = $this->texts();
        $group = $this->group($record);
        $definition = $group->definition($record['key']);
        $tabs = [];

        foreach ($texts->locales() as $code => $name) {
            $reference = $texts->reference($group, $record['key'], $code);
            $hints = [
                $reference === null
                    ? __('filament-front-localization::front-texts.form.reference_missing')
                    : __('filament-front-localization::front-texts.form.reference', ['value' => $reference]),
                __('filament-front-localization::front-texts.form.empty_keeps_reference'),
            ];

            if ($params = $texts->params((string) $reference)) {
                $hints[] = __('filament-front-localization::front-texts.form.params', ['params' => implode(', ', $params)]);
            }

            if (str_contains((string) $reference, '|')) {
                $hints[] = __('filament-front-localization::front-texts.form.plural');
            }

            $tabs[] = Tab::make($name)
                ->id("locale-{$code}")
                ->schema([
                    Textarea::make("values.{$code}")
                        ->label(__('filament-front-localization::front-texts.form.value'))
                        ->placeholder($reference)
                        ->helperText(implode(' ', $hints))
                        ->rows(3)
                        ->autosize()
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($texts, $definition, $reference): void {
                            if (filled($value) && ($error = $texts->validate($definition, $reference, trim((string) $value)))) {
                                $fail($error);
                            }
                        }),
                ]);
        }

        return Tabs::make('locales')->tabs($tabs);
    }

    protected function resetAction(): Action
    {
        return Action::make('reset')
            ->label(__('filament-front-localization::front-texts.actions.reset'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (array $record): bool => $record['modified'])
            ->requiresConfirmation()
            ->modalDescription(__('filament-front-localization::front-texts.actions.reset_confirm'))
            ->action(function (array $record): void {
                abort_unless(static::canAccess(), 403);

                $this->texts()->reset($record['group_id'], $record['key'], auth()->id());

                Notification::make()->success()->title(__('filament-front-localization::front-texts.notifications.reset'))->send();
            });
    }

    protected function historyAction(): Action
    {
        return Action::make('history')
            ->label(__('filament-front-localization::front-texts.actions.history'))
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->modalHeading(fn (array $record): string => $record['label'])
            ->modalContent(fn (array $record) => view('filament-front-localization::history', [
                'changes' => $this->texts()->history($record['group_id'], $record['key']),
                'locales' => $this->texts()->locales(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament-actions::modal.actions.close.label'));
    }

    protected function texts(): FrontTexts
    {
        return app(FrontTexts::class);
    }

    protected function group(array $record): TextGroup
    {
        $group = $this->texts()->catalog()->groups()[$record['group_id']] ?? null;

        abort_if($group === null, 404);

        return $group;
    }
}

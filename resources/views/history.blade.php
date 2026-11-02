{{-- Le journal d'un texte : les derniers changements, langue par langue. --}}
<div class="space-y-3 text-sm">
    @forelse ($changes as $change)
        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('filament-front-localization::front-texts.history.line', [
                    'date' => $change->created_at?->format('d/m/Y H:i'),
                    'user' => $change->user?->name ?? __('filament-front-localization::front-texts.history.unknown_user'),
                    'locale' => $locales[$change->locale] ?? $change->locale,
                ]) }}
            </p>
            <p class="mt-1 text-gray-500 line-through dark:text-gray-400">
                {{ $change->old_value ?? __('filament-front-localization::front-texts.history.original') }}
            </p>
            <p class="mt-1 text-gray-950 dark:text-white">
                {{ $change->new_value ?? __('filament-front-localization::front-texts.history.original') }}
            </p>
        </div>
    @empty
        <p class="text-gray-500 dark:text-gray-400">{{ __('filament-front-localization::front-texts.history.empty') }}</p>
    @endforelse
</div>

<?php

namespace CharlesStOlive\FilamentFrontLocalization;

use CharlesStOlive\FilamentFrontLocalization\Models\FrontTextChange;
use CharlesStOlive\FilamentFrontLocalization\Models\FrontTextOverride;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les textes modifiés en base, groupe par groupe : une requête et une entrée de cache par groupe (toutes langues), vidée
 * après chaque écriture validée. Une langue sans valeur ne compte pas : le fichier de cette langue s'applique, sans repli
 * sur une autre langue. Avant la migration (pas de table), il n'y a simplement rien ; toute autre erreur de base remonte.
 */
class TextOverrides
{
    public const CACHE_PREFIX = 'filament-front-localization.';

    /** @return array<string, array<string, string>> clé => [langue => texte] */
    public function group(string $namespace, string $group): array
    {
        $cached = Cache::get($cacheKey = $this->cacheKey($namespace, $group));

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $texts = FrontTextOverride::query()
                ->where('namespace', $namespace)
                ->where('group', $group)
                ->get(['key', 'text'])
                ->mapWithKeys(fn (FrontTextOverride $override): array => [$override->key => $this->clean((array) $override->text)])
                ->all();
        } catch (QueryException $exception) {
            // Pas encore migré : rien de modifié, et rien en cache (la table arrivera).
            if (! Schema::hasTable((new FrontTextOverride)->getTable())) {
                return [];
            }

            throw $exception;
        }

        Cache::forever($cacheKey, $texts);

        return $texts;
    }

    /** Les lignes d'un groupe dans une langue, en tableau imbriqué comme un fichier de langue. */
    public function linesFor(string $namespace, string $group, string $locale): array
    {
        $lines = [];

        foreach ($this->group($namespace, $group) as $key => $texts) {
            if (isset($texts[$locale])) {
                Arr::set($lines, $key, $texts[$locale]);
            }
        }

        return $lines;
    }

    public function value(string $namespace, string $group, string $key, string $locale): ?string
    {
        return $this->group($namespace, $group)[$key][$locale] ?? null;
    }

    /** @return array<string, string> langue => texte */
    public function values(string $namespace, string $group, string $key): array
    {
        return $this->group($namespace, $group)[$key] ?? [];
    }

    /**
     * Pose les textes d'une clé, langue par langue : une valeur null ou vide rend la langue au fichier. Chaque langue qui
     * change entre au journal. Les valeurs doivent déjà être validées (FrontTexts::save()).
     *
     * @param  array<string, ?string>  $values  langue => texte
     */
    public function put(string $namespace, string $group, string $key, array $values, ?int $userId = null): void
    {
        DB::transaction(function () use ($namespace, $group, $key, $values, $userId): void {
            $override = FrontTextOverride::query()
                ->where(['namespace' => $namespace, 'group' => $group, 'key' => $key])
                ->lockForUpdate()
                ->first();

            $before = $this->clean((array) ($override?->text ?? []));
            $after = $before;

            foreach ($values as $locale => $value) {
                if (is_string($value) && $value !== '') {
                    $after[$locale] = $value;
                } else {
                    unset($after[$locale]);
                }
            }

            foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $locale) {
                if (($before[$locale] ?? null) !== ($after[$locale] ?? null)) {
                    FrontTextChange::query()->create([
                        'namespace' => $namespace,
                        'group' => $group,
                        'key' => $key,
                        'locale' => $locale,
                        'old_value' => $before[$locale] ?? null,
                        'new_value' => $after[$locale] ?? null,
                        'user_id' => $userId,
                        'created_at' => now(),
                    ]);
                }
            }

            if ($after === []) {
                $override?->delete();
            } elseif ($override) {
                $override->update(['text' => $after, 'updated_by' => $userId]);
            } else {
                FrontTextOverride::query()->create([
                    'namespace' => $namespace,
                    'group' => $group,
                    'key' => $key,
                    'text' => $after,
                    'updated_by' => $userId,
                ]);
            }

            DB::afterCommit(fn () => $this->flush($namespace, $group));
        });
    }

    /** Rend toutes les langues d'une clé au fichier. */
    public function forget(string $namespace, string $group, string $key, ?int $userId = null): void
    {
        $this->put($namespace, $group, $key, array_fill_keys(array_keys($this->values($namespace, $group, $key)), null), $userId);
    }

    /**
     * Vide le cache du groupe et les traductions déjà chargées pendant cette requête : la suite de la requête lit les
     * nouveaux textes. Un worker de file d'attente garde les siennes jusqu'à son redémarrage (queue:restart).
     */
    public function flush(string $namespace, string $group): void
    {
        Cache::forget($this->cacheKey($namespace, $group));

        if (app()->resolved('translator') && method_exists($translator = app('translator'), 'setLoaded')) {
            $translator->setLoaded([]);
        }
    }

    private function cacheKey(string $namespace, string $group): string
    {
        return self::CACHE_PREFIX."{$namespace}.{$group}";
    }

    /** @return array<string, string> les seules valeurs utilisables : des textes non vides */
    private function clean(array $texts): array
    {
        return array_filter($texts, fn (mixed $text): bool => is_string($text) && $text !== '');
    }
}

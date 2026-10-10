<?php

namespace Melasistema\MelaCms;

use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Values an admin changes from the panel, stored in the `mela_settings`
 * table. All of them are read with one query, once per request.
 */
class Settings
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $values = null;

    /**
     * A dot reaches inside a value: `site.contact.email`.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key) ?? $default;
    }

    /**
     * A value that is saved per language, in the language of the request. An
     * empty translation falls back to the default language of the site.
     */
    public function translated(string $key, mixed $default = null): mixed
    {
        $translations = Arr::wrap($this->get($key));

        return array_find(
            [$translations[app()->getLocale()] ?? null, $translations[app(Languages::class)->default()] ?? null],
            fn (mixed $value) => filled($value),
        ) ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        DB::table('mela_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'updated_at' => now()],
        );

        $this->values = null;
    }

    /**
     * Save an array over the one already saved. What the new one does not
     * mention is kept, such as the translation in a language that is no
     * longer a site language.
     *
     * @param  array<string, mixed>  $value
     */
    public function merge(string $key, array $value): void
    {
        $this->set($key, static::mergeKeys(Arr::wrap($this->get($key)), $value));
    }

    /**
     * @param  array<mixed>  $saved
     * @param  array<mixed>  $new
     * @return array<mixed>
     */
    private static function mergeKeys(array $saved, array $new): array
    {
        // A list is replaced as a whole, so an item removed from it is gone.
        if (array_is_list($new) || array_is_list($saved)) {
            return $new;
        }

        foreach ($new as $key => $value) {
            $saved[$key] = is_array($value) && is_array($saved[$key] ?? null)
                ? static::mergeKeys($saved[$key], $value)
                : $value;
        }

        return $saved;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        try {
            return $this->values = DB::table('mela_settings')
                ->pluck('value', 'key')
                ->map(fn (?string $value) => $value === null ? null : json_decode($value, true))
                ->all();
        } catch (QueryException) {
            // The table does not exist before the migrations have run. The
            // result is not kept, so the next read tries again.
            return [];
        }
    }
}

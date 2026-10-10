<?php

namespace Melasistema\MelaCms;

use Illuminate\Database\QueryException;
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

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
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

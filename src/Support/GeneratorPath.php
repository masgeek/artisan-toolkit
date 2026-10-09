<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Support;

use Illuminate\Support\Str;

/**
 * Resolves configured generator paths into both absolute file paths and the
 * namespaces that generated classes must declare.
 *
 * Keeps path configuration in one place so generators never hardcode app/.
 */
final class GeneratorPath
{
    /**
     * Absolute path for a `paths.*` config key.
     */
    public static function to(string $key, string $default): string
    {
        return base_path(self::relative($key, $default));
    }

    /**
     * Relative (base_path-relative) path for a `paths.*` config key.
     */
    public static function relative(string $key, string $default): string
    {
        $configured = config("artisan-toolkit.paths.{$key}");

        return is_string($configured) && $configured !== ''
            ? trim($configured, '/\\')
            : $default;
    }

    /**
     * Derive the namespace for a `paths.*` config key.
     *
     * Each segment is studly-cased, so `app/Http/Resources` becomes
     * `App\Http\Resources` and `app/Domain/Repos` becomes `App\Domain\Repos`.
     * Paths outside app/ map from their first segment, e.g. `src/Domain`
     * becomes `Src\Domain`.
     */
    public static function namespaceFor(string $key, string $default): string
    {
        $segments = array_filter(explode('/', str_replace('\\', '/', self::relative($key, $default))));

        return implode('\\', array_map(
            static fn (string $segment): string => Str::studly($segment),
            $segments,
        ));
    }

    /**
     * Namespace for a model class name, honouring model_base_namespace.
     */
    public static function modelNamespace(string $model): string
    {
        $base = config('artisan-toolkit.model_base_namespace', 'App\\Models');
        $base = is_string($base) && $base !== '' ? trim($base, '\\') : 'App\\Models';

        return $base.'\\'.ltrim($model, '\\');
    }

    /**
     * Fully qualified model class for a short name, without a leading
     * separator so it can be used directly in `use` statements and `extends`.
     */
    public static function modelClass(string $model): string
    {
        return self::modelNamespace($model);
    }
}

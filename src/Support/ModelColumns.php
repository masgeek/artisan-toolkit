<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Support;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Normalises a model's table columns into metadata used by generators.
 *
 * Centralises the schema introspection so make:dto, make:request and
 * make:api-scaffold derive types and rules the same way.
 */
final class ModelColumns
{
    /**
     * Column metadata for a model's table, or an empty list when unavailable.
     *
     * @return list<array{name: string, type: string, type_name: string, nullable: bool, length: int|null}>
     */
    public static function forModel(string $modelClass): array
    {
        if (! class_exists($modelClass)) {
            return [];
        }

        try {
            $instance = app($modelClass);

            if (! $instance instanceof Model) {
                return [];
            }

            $schema = $instance->getConnection()->getSchemaBuilder();
            $table = $instance->getTable();

            if (! $schema->hasTable($table)) {
                return [];
            }

            $rows = $schema->getColumns($table);
        } catch (Throwable) {
            return [];
        }

        return array_map(static function (array $row): array {
            $type = strtolower((string) ($row['type'] ?? $row['type_name'] ?? 'string'));
            $length = null;

            if (preg_match('/\((\d+)/', $type, $matches) === 1) {
                $length = (int) $matches[1];
            }

            return [
                'name' => (string) $row['name'],
                'type' => $type,
                'type_name' => strtolower((string) ($row['type_name'] ?? $type)),
                'nullable' => (bool) ($row['nullable'] ?? true),
                'length' => $length,
            ];
        }, $rows);
    }

    /**
     * PHP property type for a column, including `|null` when nullable.
     *
     * @param  array{name: string, type: string, type_name: string, nullable: bool, length: int|null}  $column
     */
    public static function phpType(array $column): string
    {
        $type = self::basePhpType((string) ($column['type_name'] ?? $column['type']));

        return $column['nullable'] ? $type.'|null' : $type;
    }

    /**
     * Validation rules inferred from a column's SQL type and nullability.
     *
     * @param  array{name: string, type: string, type_name: string, nullable: bool, length: int|null}  $column
     * @return list<string>
     */
    public static function rules(array $column): array
    {
        $type = (string) ($column['type_name'] ?? $column['type']);
        $rules = [$column['nullable'] ? 'nullable' : 'required'];

        $rule = match (true) {
            str_contains($type, 'int') => 'integer',
            str_contains($type, 'bool') => 'boolean',
            str_contains($type, 'float'),
            str_contains($type, 'double'),
            str_contains($type, 'real'),
            str_contains($type, 'decimal'),
            str_contains($type, 'numeric') => 'numeric',
            str_contains($type, 'json'), str_contains($type, 'array') => 'array',
            str_contains($type, 'datetime'), str_contains($type, 'date') => 'date',
            str_contains($type, 'time') => 'date_format:H:i:s',
            default => 'string',
        };

        $rules[] = $rule;

        if ($rule === 'string' && isset($column['length']) && $column['length'] !== null) {
            $rules[] = 'max:'.$column['length'];
        }

        return $rules;
    }

    private static function basePhpType(string $type): string
    {
        return match (true) {
            str_contains($type, 'int') => 'int',
            str_contains($type, 'bool') => 'bool',
            str_contains($type, 'float'),
            str_contains($type, 'double'),
            str_contains($type, 'real'),
            str_contains($type, 'decimal'),
            str_contains($type, 'numeric') => 'float',
            str_contains($type, 'json'), str_contains($type, 'array') => 'array',
            default => 'string',
        };
    }
}

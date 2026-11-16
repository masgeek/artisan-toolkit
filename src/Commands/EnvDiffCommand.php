<?php

/**
 * Copyright (c) 2026 Munywele Consulting LTD. All rights reserved.
 * https://munywele.co.ke
 */

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;

final class EnvDiffCommand extends Command
{
    protected $signature = 'env:diff
                            {--base= : Reference env file (defaults to .env.example)}
                            {--target= : Env file to compare against the reference (defaults to .env)}
                            {--fail : Exit non-zero when keys are missing from the target}
                            {--json : Emit results as JSON instead of a table}';

    protected $description = 'Compare two env files by key, without printing any values';

    public function handle(): int
    {
        $base = $this->resolvePath($this->option('base') ?: '.env.example');
        $target = $this->resolvePath($this->option('target') ?: '.env');

        foreach (['base' => $base, 'target' => $target] as $label => $path) {
            if (! is_file($path)) {
                $this->error("The {$label} env file does not exist: {$path}");

                return self::FAILURE;
            }
        }

        $baseParsed = $this->parse($base);
        $targetParsed = $this->parse($target);

        $baseKeys = array_keys($baseParsed['keys']);
        $targetKeys = array_keys($targetParsed['keys']);

        $missing = $this->sorted(array_diff($baseKeys, $targetKeys));
        $extra = $this->sorted(array_diff($targetKeys, $baseKeys));

        $empty = [];
        $duplicates = [];

        foreach ($baseKeys as $key) {
            if (! array_key_exists($key, $targetParsed['keys'])) {
                continue;
            }

            if ($this->isEmptyValue($targetParsed['keys'][$key])) {
                $empty[] = $key;
            }
        }

        // Add any target keys that are empty, even if they are not present in the base.
        foreach ($targetParsed['keys'] as $key => $value) {
            if ($this->isEmptyValue($value)) {
                $empty[] = $key;
            }
        }
        $empty = $this->sorted($empty);

        foreach (['base' => $baseParsed, 'target' => $targetParsed] as $label => $parsed) {
            foreach ($parsed['duplicates'] as $key) {
                $duplicates[$key] = $label;
            }
        }

        $summary = [
            'missing' => count($missing),
            'extra' => count($extra),
            'empty' => count($empty),
            'duplicates' => count($duplicates),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode(
                [
                    'base' => $base,
                    'target' => $target,
                    'missing' => $missing,
                    'extra' => $extra,
                    'empty' => $empty,
                    'duplicates' => $duplicates,
                    'summary' => $summary,
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return $this->exitCode($missing);
        }

        $this->render($base, $target, $missing, $extra, $empty, $duplicates, $summary);

        return $this->exitCode($missing);
    }

    /**
     * Parse env keys without loading values into the process environment.
     *
     * Only keys and whether values are empty are ever used, so secrets are
     * never rendered.
     *
     * @return array{keys: array<string, string>, duplicates: list<string>}
     */
    private function parse(string $path): array
    {
        $keys = [];
        $duplicates = [];

        foreach (preg_split('/\r\n|\r|\n/', (string) file_get_contents($path)) ?: [] as $line) {
            if (preg_match('/^\s*(?:#\s*)?(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches) !== 1) {
                continue;
            }

            $name = $matches[1];

            if (array_key_exists($name, $keys)) {
                $duplicates[] = $name;
            }

            $keys[$name] = trim($matches[2]);
        }

        return ['keys' => $keys, 'duplicates' => array_values(array_unique($duplicates))];
    }

    private function isEmptyValue(string $value): bool
    {
        $value = trim($value);

        return $value === '' || preg_match('/^([\'"])\1$/', $value) === 1;
    }

    private function exitCode(array $missing): int
    {
        if ($this->option('fail') && $missing !== []) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $missing
     * @param  list<string>  $extra
     * @param  list<string>  $empty
     * @param  array<string, string>  $duplicates
     * @param  array{missing: int, extra: int, empty: int, duplicates: int}  $summary
     */
    private function render(
        string $base,
        string $target,
        array $missing,
        array $extra,
        array $empty,
        array $duplicates,
        array $summary,
    ): void {
        $this->newLine();
        $this->line(" <fg=gray>Base:</> {$base}");
        $this->line(" <fg=gray>Target:</> {$target}");
        $this->newLine();

        if (array_sum($summary) === 0) {
            $this->info(' Env files define the same keys and values are not empty.');

            return;
        }

        $rows = [];

        foreach ($missing as $key) {
            $rows[] = [$key, 'missing', 'present in base (.env) only'];
        }

        foreach ($empty as $key) {
            $rows[] = [$key, 'empty', 'target value is empty'];
        }

        foreach ($extra as $key) {
            $rows[] = [$key, 'extra', 'present in target only'];
        }

        foreach ($duplicates as $key => $file) {
            $rows[] = [$key, 'duplicate', "defined more than once in the {$file} file"];
        }

        $this->table(['Key', 'Status', 'Detail'], $rows);

        $this->newLine();
        $this->line(' <options=bold>'.array_sum($summary).' difference(s):</> '
            ."{$summary['missing']} missing, {$summary['extra']} extra, "
            ."{$summary['empty']} empty, {$summary['duplicates']} duplicate(s)");

        if ($missing !== []) {
            $this->warn(' Missing keys can break the application; add them to the target file.');
        }

        $this->newLine();
    }

    /**
     * @param  array<int, string>  $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        $values = array_values($values);
        sort($values);

        return $values;
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}

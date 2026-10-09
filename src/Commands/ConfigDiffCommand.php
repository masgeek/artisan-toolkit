<?php

/**
 * Copyright (c) Munywele Consulting LTD. All rights reserved.
 * https://munywele.co.ke
 */

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Terminal;

final class ConfigDiffCommand extends Command
{
    /** Hard cap so the table never overflows narrow terminals. */
    private const MAX_WIDTH = 120;

    private const STATUS_WIDTH = 10;

    /** Keys whose string values are masked in output. */
    private const SENSITIVE = '/(^|_)(key|keys|secret|secrets|token|tokens|password|passwords|passwd|credential|credentials)$/i';

    /**
     * Keys that look sensitive but hold non-secret data (e.g. a file path) and
     * must stay visible in the diff.
     */
    private const SENSITIVE_EXEMPT = ['key_storage_path'];

    /** @var array<string, string> status => console color */
    private const STATUS_COLORS = [
        'added' => 'green',
        'extended' => 'green',
        'modified' => 'yellow',
        'reordered' => 'yellow',
        'trimmed' => 'red',
        'disabled' => 'red',
        'unset' => 'red',
    ];

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> */
    private array $rows = [];

    /** @var array<string, int> */
    private array $counts = [];

    protected $signature = 'config:diff {--fail : Exit with a failure code when differences are found}';

    protected $description = 'Compare published config with package defaults';

    public function handle(): int
    {
        $this->newLine();
        $this->line(' <options=bold;fg=cyan>Toolkit Configuration Diff</>');
        $this->line(' Comparing <comment>config/artisan-toolkit.php</comment> with package defaults.');
        $this->newLine();

        // Load the shipped defaults directly so this command can never drift
        // from config/artisan-toolkit.php.
        $defaultsFile = __DIR__.'/../../config/artisan-toolkit.php';
        $defaults = is_file($defaultsFile) ? require $defaultsFile : null;
        $userConfig = config('artisan-toolkit');

        if (! is_array($defaults)) {
            $this->error('Package default config could not be loaded.');

            return self::FAILURE;
        }

        if (! is_array($userConfig)) {
            $this->error('Config not found. Publish it first: php artisan vendor:publish --tag=artisan-toolkit-config');

            return self::FAILURE;
        }

        $this->rows = [];
        $this->counts = [];

        $this->diffMap([], $defaults, $userConfig);

        if ($this->rows === []) {
            $this->line(' <fg=green>✔ Config is identical to defaults.</>');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->renderTable();
        $this->renderSummary();

        return $this->option('fail') ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Diff two associative maps key by key, including keys that exist on only one side.
     *
     * @param  list<string>  $path
     * @param  array<array-key, mixed>  $default
     * @param  array<array-key, mixed>  $user
     */
    private function diffMap(array $path, array $default, array $user): void
    {
        foreach (array_keys($default + $user) as $key) {
            $this->diff(
                [...$path, (string) $key],
                $default[$key] ?? null,
                $user[$key] ?? null,
                array_key_exists($key, $default),
                array_key_exists($key, $user),
            );
        }
    }

    /**
     * @param  list<string>  $path
     */
    private function diff(array $path, mixed $default, mixed $user, bool $defaultExists, bool $userExists): void
    {
        // Associative maps on both sides: recurse so nested settings get their own row.
        if (
            is_array($default) && is_array($user)
            && ! array_is_list($default) && ! array_is_list($user)
        ) {
            $this->diffMap($path, $default, $user);

            return;
        }

        if ($defaultExists && $userExists && $default === $user) {
            return;
        }

        $status = $this->status($default, $user, $defaultExists, $userExists);
        $key = (string) end($path);

        $this->counts[$status] = ($this->counts[$status] ?? 0) + 1;
        $this->rows[] = [
            $this->badge($status),
            $this->label($path),
            $defaultExists ? $this->format($default, $key) : '<fg=gray>—</>',
            $userExists ? $this->format($user, $key) : '<fg=gray>—</>',
        ];
    }

    private function status(mixed $default, mixed $user, bool $defaultExists, bool $userExists): string
    {
        if (! $defaultExists || $default === null) {
            return 'added';
        }

        if (! $userExists || $user === null) {
            return 'unset';
        }

        if ($user === false && $default !== false) {
            return 'disabled';
        }

        if (is_array($default) && is_array($user) && array_is_list($default) && array_is_list($user)) {
            if ($default === []) {
                return 'added';
            }

            if ($user === []) {
                return 'trimmed';
            }

            $added = array_udiff($user, $default, $this->compare(...));
            $removed = array_udiff($default, $user, $this->compare(...));

            return match (true) {
                $added !== [] && $removed === [] => 'extended',
                $added === [] && $removed !== [] => 'trimmed',
                $added === [] && $removed === [] => 'reordered',
                default => 'modified',
            };
        }

        return 'modified';
    }

    private function compare(mixed $a, mixed $b): int
    {
        return $this->fingerprint($a) <=> $this->fingerprint($b);
    }

    private function fingerprint(mixed $value): string
    {
        return (string) json_encode($value, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    private function badge(string $status): string
    {
        $color = self::STATUS_COLORS[$status] ?? 'default';

        return "<fg={$color}>{$status}</>";
    }

    /**
     * @param  list<string>  $path
     */
    private function label(array $path): string
    {
        $segments = array_map(
            fn (string $segment): string => OutputFormatter::escape($this->shortName($segment)),
            $path,
        );

        $last = array_pop($segments);
        $parent = $segments === [] ? '' : '<fg=gray>'.implode('.', $segments).'.</>';

        return $parent.'<options=bold>'.$last.'</>';
    }

    /**
     * Shorten fully qualified class names (App\Models\User) to their basename.
     */
    private function shortName(string $value): string
    {
        return str_contains($value, '\\') && ! str_contains($value, ' ')
            ? class_basename($value)
            : $value;
    }

    /**
     * Render the table within a readable maximum width.
     *
     * Only max widths are set (never fixed widths) so Symfony auto-sizes columns
     * to their content and shrinks instead of overflowing the terminal.
     */
    private function renderTable(): void
    {
        $columns = 4;
        $chrome = $columns * 3 + 1; // cell padding + borders
        $usable = min((new Terminal)->getWidth(), self::MAX_WIDTH) - $chrome - self::STATUS_WIDTH;

        $labelWidth = max(12, (int) round($usable * 0.28));
        $valueWidth = max(12, (int) floor(($usable - $labelWidth) / 2));

        $table = new Table($this->output);
        $table->setStyle('box');
        $table->setHeaders(['Status', 'Setting', 'Default', 'Yours']);

        foreach ([self::STATUS_WIDTH, $labelWidth, $valueWidth, $valueWidth] as $index => $width) {
            $table->setColumnMaxWidth($index, $width);
        }

        $previousGroup = null;

        foreach ($this->rows as $row) {
            $group = $this->groupOf($row);

            // Only break between top-level settings; a separator after every row
            // would swamp multi-line JSON cells in borders.
            if ($previousGroup !== null && $group !== $previousGroup) {
                $table->addRow(new TableSeparator);
            }

            $table->addRow($row);
            $previousGroup = $group;
        }

        $table->render();
    }

    /**
     * The top-level setting a row belongs to (the first segment of its path).
     *
     * @param  array{0: string, 1: string, 2: string, 3: string}  $row
     */
    private function groupOf(array $row): string
    {
        // Label format is "<parent>.<bold>leaf</>"; strip tags to get the root.
        $plain = strip_tags($row[1]);

        return str_contains($plain, '.') ? substr($plain, 0, strpos($plain, '.')) : $plain;
    }

    private function renderSummary(): void
    {
        ksort($this->counts);

        $parts = [];
        foreach ($this->counts as $status => $count) {
            $parts[] = "{$count} {$status}";
        }

        $this->newLine();
        $this->line(' <options=bold>'.array_sum($this->counts).' difference(s)</> <fg=gray>('.implode(', ', $parts).')</>');
        $this->newLine();
    }

    /**
     * Format a value. Arrays render as an indented JSON-style block that flows
     * downward so nesting and item order stay readable.
     */
    private function format(mixed $value, string $key = ''): string
    {
        if (! is_array($value)) {
            return $this->formatScalar($value, $key);
        }

        if ($value === []) {
            return '<fg=gray>[]</>';
        }

        $isList = array_is_list($value);
        $pad = '  ';
        $lines = [$isList ? '<fg=gray>[</>' : '<fg=gray>{</>'];

        foreach ($value as $itemKey => $item) {
            $rendered = str_replace("\n", "\n".$pad, $this->format($item, (string) $itemKey));
            $prefix = $isList
                ? ''
                : '<fg=cyan>'.OutputFormatter::escape($this->shortName((string) $itemKey)).'</>: ';

            $lines[] = $pad.$prefix.$rendered.',';
        }

        $lines[] = $isList ? '<fg=gray>]</>' : '<fg=gray>}</>';

        return implode("\n", $lines);
    }

    /**
     * Only treat a string as a class reference when it looks like one, so plain
     * config values never trigger the autoloader.
     */
    private function looksLikeClass(string $value): bool
    {
        return class_exists($value) && preg_match('/^\\\\?[A-Za-z_\x80-\xff][\w\x80-\xff]*(\\\\[A-Za-z_\x80-\xff][\w\x80-\xff]*)+$/', $value) === 1;
    }

    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        if (in_array($key, self::SENSITIVE_EXEMPT, true)) {
            return false;
        }

        return preg_match(self::SENSITIVE, $key) === 1;
    }

    private function formatScalar(mixed $value, string $key): string
    {
        if (is_string($value) && $value !== '' && $this->isSensitive($key)) {
            return '<fg=gray>••••••••</>';
        }

        return match (true) {
            $value === null => '<fg=gray>null</>',
            $value === true => '<fg=green>true</>',
            $value === false => '<fg=red>false</>',
            is_int($value), is_float($value) => "<fg=blue>{$value}</>",
            $value instanceof \UnitEnum => '<fg=cyan>'.OutputFormatter::escape($value::class.'::'.$value->name).'</>',
            $value instanceof \Closure => '<fg=gray>Closure</>',
            is_object($value) => '<fg=cyan>'.OutputFormatter::escape(class_basename($value)).'</>',
            is_string($value) && $this->looksLikeClass($value) => '<fg=cyan>'.OutputFormatter::escape(class_basename($value)).'</>',
            is_string($value) => '"'.OutputFormatter::escape(str_replace("\n", '\n', $value)).'"',
            default => '<fg=gray>'.get_debug_type($value).'</>',
        };
    }
}

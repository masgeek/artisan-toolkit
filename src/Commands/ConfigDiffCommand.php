<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Terminal;

final class ConfigDiffCommand extends Command
{
    /** Hard cap so the table never overflows narrow terminals. */
    private const MAX_WIDTH = 120;

    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    protected $signature = 'config:diff';

    protected $description = 'Compare published config with package defaults';

    public function handle(): int
    {
        $this->newLine();
        $this->info(' <fg=cyan>Toolkit Configuration Diff</>');
        $this->line('Comparing <comment>config/artisan-toolkit.php</comment> with package defaults.');
        $this->newLine();

        // Load the package's shipped defaults directly so this command can never
        // drift from config/artisan-toolkit.php.
        $defaults = require __DIR__.'/../../config/artisan-toolkit.php';

        $userConfig = config('artisan-toolkit');

        if (! is_array($userConfig)) {
            $this->error('Config not found. Publish it first: php artisan vendor:publish --tag=artisan-toolkit-config');

            return self::FAILURE;
        }

        $this->rows = [];

        foreach ($defaults as $key => $defaultValue) {
            $this->diff((string) $key, $defaultValue, $userConfig[$key] ?? null);
        }

        if (empty($this->rows)) {
            $this->info('Config is identical to defaults. <fg=green>OK</>');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->renderTable();
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Recursively diff a value so nested maps and list items each get their own row.
     */
    private function diff(string $path, mixed $default, mixed $user): void
    {
        if (is_array($default)) {
            $this->diffArray($path, $default, $user);

            return;
        }

        if ($default === $user) {
            return;
        }

        $status = 'Modified';

        if ($user === null) {
            $status = '<fg=red>Unset</>';
        } elseif ($user === false) {
            $status = '<fg=red>Disabled</>';
        } elseif (! is_array($default) && ! array_key_exists($this->lastSegment($path), (array) $user)) {
            $status = '<fg=green>Added</>';
        }

        $this->rows[] = [$this->label($path, $status), $this->format($default), $this->format($user)];
    }

    /**
     * @param  array<mixed, mixed>  $default
     */
    private function diffArray(string $path, array $default, mixed $user): void
    {
        $user = is_array($user) ? $user : [];

        // Associative map: recurse per key so nested settings are broken out.
        if (! array_is_list($default) && ! array_is_list($user)) {
            foreach (array_unique(array_merge(array_keys($default), array_keys($user))) as $key) {
                $this->diff($path.'.'.$key, $default[$key] ?? null, $user[$key] ?? null);
            }

            return;
        }

        // Lists are shown as a whole, rendered as an indented JSON block that
        // flows downward, so item order and nesting stay readable.
        if ($default === $user) {
            return;
        }

        $status = match (true) {
            ! is_array($user) => '<fg=red>Replaced</>',
            count($user) > count($default) => '<fg=green>Added to</>',
            count($user) < count($default) => '<fg=red>Removed from</>',
            default => 'Modified',
        };

        $this->rows[] = [$this->label($path, $status), $this->format($default), $this->format($user)];
    }

    private function label(string $path, string $status): string
    {
        // Shorten any fully qualified class name segment to its basename so
        // paths such as encrypted_models.App\Models\User stay readable.
        $segments = array_map(
            static fn (string $segment): string => str_ends_with($segment, '::class')
                ? class_basename($segment)
                : $segment,
            explode('.', $path),
        );

        return implode('.', $segments)." <fg=gray>({$status})</>";
    }

    private function lastSegment(string $path): string
    {
        return str_contains($path, '.') ? substr($path, strrpos($path, '.') + 1) : $path;
    }

    /**
     * Render the table, constraining it to a readable maximum width.
     *
     * Only max widths are set (never fixed widths) so Symfony auto-sizes columns
     * to their content and shrinks gracefully instead of overflowing the terminal.
     */
    private function renderTable(): void
    {
        $available = min((new Terminal)->getWidth(), self::MAX_WIDTH);
        $labelWidth = (int) round($available * 0.34);
        $valueWidth = (int) round($available * 0.33);

        $table = new Table($this->output);
        $table->setHeaders(['Setting', 'Default Value', 'Your Value']);

        foreach ([$labelWidth, $valueWidth, $valueWidth] as $index => $width) {
            $table->setColumnMaxWidth($index, $width);
        }

        foreach ($this->rows as $row) {
            $table->addRow($row);
        }

        $table->render();
    }

    /**
     * Format a value. Arrays and maps are rendered as an indented, JSON-style
     * block that flows downward so nesting and item order stay readable.
     */
    private function format(mixed $value): string
    {
        if (! is_array($value)) {
            return $this->formatScalar($value);
        }

        if ($value === []) {
            return '<fg=gray>[]</>';
        }

        $isList = array_is_list($value);
        $lines = [$isList ? '<fg=gray>[' : '<fg=gray>{</>'];
        $items = [];

        foreach ($value as $key => $item) {
            $rendered = $this->format($item);

            if ($isList) {
                $items[] = '    '.$rendered.',';

                continue;
            }

            // Config maps command names to fully qualified class names; the
            // namespace adds noise, so show only the short class name.
            $label = str_ends_with((string) $key, '::class')
                ? class_basename((string) $key)
                : (string) $key;

            $items[] = '    <fg=cyan>'.$label.'</>: '.$rendered.',';
        }

        $lines = array_merge($lines, $items, [$isList ? '<fg=gray>]</>' : '<fg=gray>}</>']);

        return implode("\n", $lines);
    }

    private function formatScalar(mixed $value): string
    {
        return match (true) {
            $value === null => '<fg=gray>null</>',
            $value === false => '<fg=red>false</>',
            $value === true => '<fg=green>true</>',
            is_array($value) => '<fg=gray>(nested)</>',
            is_string($value) && str_ends_with($value, '::class') => '<fg=cyan>'.class_basename($value).'</>',
            is_string($value) => str_replace("\n", ' ', $value),
            default => (string) $value,
        };
    }
}
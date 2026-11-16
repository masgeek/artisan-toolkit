<?php

/**
 * Command to fix env files based on a reference .env.example.
 *
 * - Missing keys are added to the target as commented lines (`# KEY=`).
 * - Duplicate keys in the target are commented out (all but the first instance).
 * - Empty values in the target are commented out.
 *
 * This command is safe – it never exposes values and only writes comments.
 */

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;

final class EnvFixCommand extends Command
{
    protected $signature = 'env:fix
                            {--base= : Reference env file (defaults to .env.example)}
                            {--target= : Env file to fix (defaults to .env)}
                            {--dry-run : Show what would be changed without writing files}';

    protected $description = 'Fix an env file by adding missing keys, commenting duplicates and empty values.';

    public function handle(): int
    {
        $basePath = $this->resolvePath($this->option('base') ?: '.env.example');
        $targetPath = $this->resolvePath($this->option('target') ?: '.env');

        foreach (['base' => $basePath, 'target' => $targetPath] as $label => $path) {
            if (! is_file($path)) {
                $this->error("The {$label} env file does not exist: {$path}");
                return self::FAILURE;
            }
        }

        $baseParsed = $this->parse($basePath);
        $targetParsed = $this->parse($targetPath);

        $baseKeys = array_keys($baseParsed['keys']);
        $targetKeys = array_keys($targetParsed['keys']);

        $missing = $this->sorted(array_diff($baseKeys, $targetKeys));
        $duplicates = []; // will be filled below

        // Load raw lines of target file for in‑place modifications
        $lines = file($targetPath, FILE_IGNORE_NEW_LINES);
        $seen = [];
        $emptied = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*(?:#\s*)?(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $m) !== 1) {
                continue;
            }
            $key = $m[1];
            $value = $m[2];
            // Detect duplicate keys (second or later occurrence)
            if (in_array($key, $seen, true)) {
                // Comment out duplicate line unless already commented
                if (strpos(ltrim($line), '#') !== 0) {
                    $lines[$i] = '# ' . $line;
                }
                $duplicates[$key] = 'target';
                continue;
            }
            $seen[] = $key;
                // Comment out empty values
                if ($this->isEmptyValue($value)) {
                    if (strpos(ltrim($line), '#') !== 0) {
                        $lines[$i] = '# ' . $line;
                    }
                    $emptied[] = $key;
                }
        }

        // Append missing keys as commented lines at the end of the file
        foreach ($missing as $key) {
            $lines[] = '# ' . $key . '='; // no value
        }

        $newContent = implode(PHP_EOL, $lines) . PHP_EOL;

        if ($this->option('dry-run')) {
            // Summarize actions without revealing values.
            $rows = [];
            foreach ($missing as $key) {
                $rows[] = [$key, 'add', 'missing key added as comment'];
            }
            foreach ($duplicates as $key => $_) {
                $rows[] = [$key, 'comment', 'duplicate key commented'];
            }
            foreach ($emptied as $key) {
                $rows[] = [$key, 'comment', 'empty value commented'];
            }
            if (! empty($rows)) {
                $this->table(['Key', 'Action', 'Detail'], $rows);
            } else {
                $this->info('No changes needed.');
            }
            return self::SUCCESS;
        }


        file_put_contents($targetPath, $newContent);

        $this->info('Env file fixed.');
        $this->line('Missing keys added as comments: ' . implode(', ', $missing));
        $this->line('Duplicates commented out: ' . implode(', ', array_keys($duplicates)));
        return self::SUCCESS;
    }

    // Re‑use parsing logic from EnvDiffCommand (copying for simplicity)
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

    private function resolvePath(string $path): string
    {
        return $this->isAbsolutePath($path) ? $path : base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function isEmptyValue(string $value): bool
    {
        $value = trim($value);
        return $value === '' || preg_match('/^([\'\"])\1$/', $value) === 1;
    }

    private function sorted(array $list): array
    {
        sort($list);
        return $list;
    }


}

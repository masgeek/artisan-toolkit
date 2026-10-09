<?php

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Masgeek\ArtisanToolkit\Support\GeneratorPath;

class MakeEnumCommand extends Command
{
    protected $signature = 'make:enum
                                {name? : Enum class name, optionally namespaced (e.g. UserRole or Auth/UserRole)}
                                {--backed= : Backing type: string or int (omit for a pure enum)}
                                {--cases= : Comma-separated case names to stub out}
                                {--table= : Generate cases from distinct values of a DB column (e.g. users.role)}
                                {--force : Overwrite if the file already exists}';

    protected $description = 'Create a new PHP enum in the configured paths.enums directory';

    public function handle(Filesystem $files): int
    {
        $name = $this->argument('name');

        if (! $name) {
            $name = $this->ask('What is the name of the enum?');
            if (! $name) {
                $this->error('Enum name is required.');
                return self::FAILURE;
            }
        }

        $backed = $this->option('backed');

        if ($backed !== null && ! in_array($backed, ['string', 'int'], true)) {
            $this->components->error("--backed must be 'string' or 'int', got '{$backed}'.");

            return self::FAILURE;
        }

        [$namespace, $className, $filePath] = $this->resolvePaths($name);

        if ($files->exists($filePath) && ! $this->option('force')) {
            $this->components->error("Enum [{$filePath}] already exists. Use --force to overwrite.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($filePath));
        $files->put($filePath, $this->buildContent($namespace, $className, $backed));

        $this->components->info("Enum [{$filePath}] created successfully.");

        return self::SUCCESS;
    }


    /** @return array{string, string, string} [namespace, className, absoluteFilePath] */
    private function resolvePaths(string $name): array
    {
        $name = str_replace('/', '\\', trim($name, '/\\'));
        $parts = explode('\\', $name);
        $className = array_pop($parts);
        $sub = implode('\\', $parts);

        // Namespace follows the configured directory so moving paths.enums also
        // moves the namespace, keeping the file resolvable by the autoloader.
        $baseNamespace = GeneratorPath::namespaceFor('enums', 'app/Enums');

        $namespace = $baseNamespace.($sub ? '\\'.$sub : '');
        $relativeDir = GeneratorPath::relative('enums', 'app/Enums').($sub ? '/'.str_replace('\\', '/', $sub) : '');
        $filePath = base_path($relativeDir.'/'.$className.'.php');

        return [$namespace, $className, $filePath];
    }

    private function buildContent(string $namespace, string $className, ?string $backed): string
    {
        $backingDecl = $backed ? ": {$backed}" : '';
        $caseBlock = $this->buildCases($backed);

        return implode("\n", [
            '<?php',
            '',
            "namespace {$namespace};",
            '',
            "enum {$className}{$backingDecl}",
            '{',
            $caseBlock,
            '}',
            '',
        ]);
    }

    private function buildCases(?string $backed): string
    {
        $tableCol = $this->option('table');

        if ($tableCol) {
            return $this->buildCasesFromTable($tableCol, $backed);
        }

        $raw = $this->option('cases');

        if (! $raw) {
            return '    //';
        }

        $names = array_filter(array_map('trim', explode(',', $raw)));
        $lines = [];
        $index = 1;

        foreach ($names as $case) {
            $lines[] = match ($backed) {
                'string' => "    case {$case} = '".$this->toSnakeCase($case)."';",
                'int' => "    case {$case} = {$index};",
                default => "    case {$case};",
            };
            $index++;
        }

        return implode("\n", $lines);
    }

    private function buildCasesFromTable(string $tableCol, ?string $backed): string
    {
        [$table, $column] = str_contains($tableCol, '.') 
            ? explode('.', $tableCol) 
            : [config('database.connections.mysql.database'), $tableCol];

        try {
            $values = DB::table($table)->distinct()->pluck($column)->filter()->toArray();
        } catch (\Throwable $e) {
            $this->error("Failed to fetch values from {$table}.{$column}: {$e->getMessage()}");
            return '    // Error fetching table values';
        }

        $lines = [];
        $index = 1;

        foreach ($values as $value) {
            $caseName = $this->toStudlyCase((string)$value);
            $lines[] = match ($backed) {
                'string' => "    case {$caseName} = '{$value}';",
                'int' => "    case {$caseName} = " . (int)$value . ";",
                default => "    case {$caseName};",
            };
            $index++;
        }

        return implode("\n", $lines);
    }

    /**
     * Turn a database value into a valid PHP enum case name.
     *
     * Values are often lower-case or separated by separators ("in-progress",
     * "2fa"), which are not valid case identifiers on their own.
     */
    private function toStudlyCase(string $value): string
    {
        $studly = Str::studly($value);

        // Fall back to a positional name if the value has no usable characters
        // (e.g. a numeric or symbol-only column) or collides with PHP keywords.
        if ($studly === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $studly)) {
            return 'Value'.substr(md5($value), 0, 6);
        }

        return $studly;
    }

    private function toSnakeCase(string $name): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
    }
}

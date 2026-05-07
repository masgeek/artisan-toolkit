<?php

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeEnumCommand extends Command
{
    protected $signature = 'make:enum
                            {name : Enum class name, optionally namespaced (e.g. UserRole or Auth/UserRole)}
                            {--backed= : Backing type: string or int (omit for a pure enum)}
                            {--cases= : Comma-separated case names to stub out}
                            {--force : Overwrite if the file already exists}';

    protected $description = 'Create a new PHP enum in app/Enums/';

    public function handle(Filesystem $files): int
    {
        $backed = $this->option('backed');

        if ($backed !== null && ! in_array($backed, ['string', 'int'], true)) {
            $this->components->error("--backed must be 'string' or 'int', got '{$backed}'.");

            return self::FAILURE;
        }

        [$namespace, $className, $filePath] = $this->resolvePaths($this->argument('name'));

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

        $namespace = 'App\\Enums' . ($sub ? '\\' . $sub : '');
        $relativeDir = 'app/Enums' . ($sub ? '/' . str_replace('\\', '/', $sub) : '');
        $filePath = base_path($relativeDir . '/' . $className . '.php');

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
        $raw = $this->option('cases');

        if (! $raw) {
            return '    //';
        }

        $names = array_filter(array_map('trim', explode(',', $raw)));
        $lines = [];
        $index = 1;

        foreach ($names as $case) {
            $lines[] = match ($backed) {
                'string' => "    case {$case} = '" . $this->toSnakeCase($case) . "';",
                'int'    => "    case {$case} = {$index};",
                default  => "    case {$case};",
            };
            $index++;
        }

        return implode("\n", $lines);
    }

    private function toSnakeCase(string $name): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
    }
}

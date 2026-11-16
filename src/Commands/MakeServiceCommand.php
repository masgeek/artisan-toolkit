<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Masgeek\ArtisanToolkit\Support\GeneratorPath;

final class MakeServiceCommand extends Command
{
    protected $signature = 'make:service 
                            {name : The Service class name} 
                            {--force : Overwrite an existing service} 
                            {--dry-run : Show the file that would be created without writing it}';

    protected $description = 'Create a new Service class';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly($this->argument('name'));
        $namespace = GeneratorPath::namespaceFor('services', 'app/Services');
        $filePath = GeneratorPath::to('services', 'app/Services')."/{$name}Service.php";

        if ($files->exists($filePath) && ! $this->option('force')) {
            $this->error("Service [{$name}Service] already exists. Use --force to overwrite.");
            return self::FAILURE;
        }

        $content = implode("\n", [
            '<?php',
            '',
            "namespace {$namespace};",
            '',
            "final class {$name}Service",
            '{',
            '    public function __construct()',
            '    {',
            '    }',
            '',
            '    public function handle(): void',
            '    {',
            '        // Implement logic here',
            '    }',
            '}',
        ]);

        if ($this->option('dry-run')) {
            $this->line(" <fg=cyan>[dry-run]</> Would create: {$filePath}");

            return self::SUCCESS;
        }

        $files->ensureDirectoryExists(dirname($filePath));
        $files->put($filePath, $content);

        $this->info("Service [{$filePath}] created successfully.");
        return self::SUCCESS;
    }
}

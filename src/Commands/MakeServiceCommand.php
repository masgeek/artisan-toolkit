<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Masgeek\ArtisanToolkit\Support\GeneratorPath;

final class MakeServiceCommand extends Command
{
    protected $signature = 'make:service {name : The Service class name}';

    protected $description = 'Create a new Service class';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly($this->argument('name'));
        $namespace = GeneratorPath::namespaceFor('services', 'app/Services');
        $filePath = GeneratorPath::to('services', 'app/Services')."/{$name}Service.php";

        if ($files->exists($filePath)) {
            $this->error("Service [{$name}Service] already exists.");
            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($filePath));
        
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

        $files->put($filePath, $content);

        $this->info("Service [{$filePath}] created successfully.");
        return self::SUCCESS;
    }
}

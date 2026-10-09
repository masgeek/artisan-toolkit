<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MakeServiceCommand extends Command
{
    protected $signature = 'make:service {name : The Service class name}';

    protected $description = 'Create a new Service class';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly($this->argument('name'));
        $path = config('artisan-toolkit.paths.services', 'app/Services');
        $filePath = base_path($path."/{$name}Service.php");

        if ($files->exists($filePath)) {
            $this->error("Service [{$name}Service] already exists.");
            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($filePath));
        
        $content = implode("\n", [
            '<?php',
            '',
            'namespace App\\Services;',
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

<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;

final class AppStatusCommand extends Command
{
    protected $signature = 'app:status';

    protected $description = 'Display application environment and toolkit status';

    public function handle(): int
    {
        $this->info("Application Status");
        $this->line("PHP Version: " . PHP_VERSION);
        $this->line("Laravel Version: " . app()->version());
        $this->line("Environment: " . app()->environment());
        
        $this->newLine();
        $this->info("Toolkit Status");
        $overrides = config('artisan-toolkit.overrides', []);
        $commands = config('artisan-toolkit.commands', []);
        
        $this->line("Active Overrides: " . count($overrides));
        $this->line("Active Commands: " . count($commands));

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;

final class DbSeedPartialCommand extends Command
{
    protected $signature = 'db:seed-partial {class : Seed class name}';

    protected $description = 'Run a specific seed class';

    public function handle(): int
    {
        $class = $this->argument('class');
        $this->info("Running seed: {$class}...");
        
        try {
            app()->call([$class, 'run']);
            $this->info("Successfully seeded.");
        } catch (\Throwable $e) {
            $this->error("Seeding failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

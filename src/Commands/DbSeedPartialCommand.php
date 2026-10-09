<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Seeder;

final class DbSeedPartialCommand extends Command
{
    protected $signature = 'db:seed-partial {class : Seed class name}';

    protected $description = 'Run a specific seeder class';

    public function handle(): int
    {
        $class = ltrim((string) $this->argument('class'), '\\');

        if (! class_exists($class)) {
            $this->error("Seeding failed: class [{$class}] does not exist.");

            return self::FAILURE;
        }

        if (! is_subclass_of($class, Seeder::class)) {
            $this->error("Seeding failed: [{$class}] must extend ".Seeder::class.'.');

            return self::FAILURE;
        }

        $this->info("Running seed: {$class}...");

        try {
            // Resolve to an instance first: passing the class name string would
            // be dispatched as a static call and fail on a non-static run().
            app()->call([new $class, 'run']);
        } catch (\Throwable $e) {
            $this->error('Seeding failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Successfully seeded.');

        return self::SUCCESS;
    }
}
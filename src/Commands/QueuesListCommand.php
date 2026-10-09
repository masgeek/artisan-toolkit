<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Throwable;

final class QueuesListCommand extends Command
{
    protected $signature = 'queues:list';

    protected $description = 'List every queue declared in the artisan-toolkit config';

    /**
     * @return int
     * @throws Throwable
     */
    public function handle(): int
    {
        $queues = config('artisan-toolkit.queues', []);

        if (empty($queues)) {
            $this->fail('No queues defined in config/artisan-toolkit.php.');
        }

        $this->info('Available queues:');

        foreach ($queues as $queue) {
            $this->line("- {$queue}");
        }

        return self::SUCCESS;
    }
}

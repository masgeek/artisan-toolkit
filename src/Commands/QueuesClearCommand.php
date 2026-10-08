<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Throwable;

final class QueuesClearCommand extends Command
{
    protected $signature = 'queues:clear';

    protected $description = 'Clear every queue declared in the artisan-toolkit config';

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

        foreach ($queues as $queue) {
            $this->call('queue:clear', ['--queue' => $queue, '--force' => true]);
        }

        return self::SUCCESS;
    }
}

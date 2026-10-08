<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Throwable;

final class QueuesListenCommand extends Command
{
    protected $signature = 'queues:listen';

    protected $description = 'Listen on every queue declared in the artisan-toolkit config';

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

        return $this->call('queue:listen', ['--queue' => implode(',', $queues)]);
    }
}

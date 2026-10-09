<?php

namespace Masgeek\ArtisanToolkit\Tests;

class QueuesCommandsTest extends TestCase
{
    public function test_list_prints_every_configured_queue(): void
    {
        config()->set('artisan-toolkit.queues', ['default', 'high', 'low']);

        $this->artisan('queues:list')
            ->assertSuccessful()
            ->expectsOutputToContain('Available queues:')
            ->expectsOutputToContain('- default')
            ->expectsOutputToContain('- high')
            ->expectsOutputToContain('- low');
    }

    public function test_list_fails_when_no_queues_are_configured(): void
    {
        config()->set('artisan-toolkit.queues', []);

        $this->artisan('queues:list')
            ->assertFailed()
            ->expectsOutputToContain('No queues defined');
    }

    public function test_clear_fails_when_no_queues_are_configured(): void
    {
        config()->set('artisan-toolkit.queues', []);

        $this->artisan('queues:clear')
            ->assertFailed()
            ->expectsOutputToContain('No queues defined');
    }

    public function test_listen_fails_when_no_queues_are_configured(): void
    {
        config()->set('artisan-toolkit.queues', []);

        $this->artisan('queues:listen')
            ->assertFailed()
            ->expectsOutputToContain('No queues defined');
    }

    public function test_listen_fails_when_config_is_null(): void
    {
        config()->set('artisan-toolkit.queues', null);

        $this->artisan('queues:listen')
            ->assertFailed()
            ->expectsOutputToContain('No queues defined');
    }
}

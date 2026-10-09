<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Support\Facades\Artisan;

class ConfigDiffCommandTest extends TestCase
{
    private ?string $originalColumns = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The report is a rendered table whose cells wrap to the terminal width.
        // Pin a wide terminal so assertions are not split across lines on CI.
        $this->originalColumns = getenv('COLUMNS');
        putenv('COLUMNS=200');
    }

    protected function tearDown(): void
    {
        if ($this->originalColumns === false || $this->originalColumns === null) {
            putenv('COLUMNS');
        } else {
            putenv('COLUMNS='.$this->originalColumns);
        }

        parent::tearDown();
    }

    /**
     * Run config:diff and return its output.
     *
     * Symfony renders one output line per row, so the buffer is asserted
     * directly rather than through output expectations.
     */
    private function diff(array $options = []): string
    {
        Artisan::call('config:diff', $options);

        return Artisan::output();
    }

    public function test_it_reports_when_config_matches_defaults(): void
    {
        config()->set('artisan-toolkit', require __DIR__.'/../config/artisan-toolkit.php');

        $this->assertStringContainsString('Config is identical to defaults', $this->diff());
    }

    public function test_it_reports_a_modified_setting_with_both_values(): void
    {
        config()->set('artisan-toolkit.api_version', 'v3');

        $output = $this->diff();

        $this->assertStringContainsString('api_version', $output);
        $this->assertStringContainsString('v3', $output);
        $this->assertStringContainsString('modified', $output);
    }

    public function test_it_reports_disabled_commands(): void
    {
        config()->set('artisan-toolkit.commands', ['make:enum' => false]);

        $output = $this->diff();

        $this->assertStringContainsString('disabled', $output);
        $this->assertStringContainsString('make:enum', $output);
    }

    public function test_it_reports_a_changed_list(): void
    {
        config()->set('artisan-toolkit.queues', ['default', 'urgent']);

        $output = $this->diff();

        $this->assertStringContainsString('queues', $output);
        $this->assertStringContainsString('urgent', $output);
    }

    public function test_it_renders_nested_values_and_shortens_class_names(): void
    {
        config()->set('artisan-toolkit.paths', [
            'resources' => 'app/Domain/Resources',
        ]);

        $output = $this->diff();

        $this->assertStringContainsString('app/Domain/Resources', $output);
        // FQCNs are shortened to basenames so the table stays readable.
        $this->assertStringNotContainsString('Masgeek\ArtisanToolkit\Commands', $output);
    }

    public function test_it_exempts_key_storage_path_from_masking(): void
    {
        config()->set('artisan-toolkit.key_storage_path', '/etc/keys.json');

        $output = $this->diff();

        // key_storage_path holds a path, not a secret, so it must stay visible.
        $this->assertStringContainsString('/etc/keys.json', $output);
    }

    public function test_it_summarises_difference_counts(): void
    {
        config()->set('artisan-toolkit.api_version', 'v3');

        $this->assertStringContainsString('difference(s)', $this->diff());
    }

    public function test_it_returns_failure_with_the_fail_flag(): void
    {
        config()->set('artisan-toolkit.api_version', 'v3');

        $this->artisan('config:diff', ['--fail' => true])->assertFailed();
    }

    public function test_it_returns_success_with_the_fail_flag_when_identical(): void
    {
        config()->set('artisan-toolkit', require __DIR__.'/../config/artisan-toolkit.php');

        $this->artisan('config:diff', ['--fail' => true])->assertSuccessful();
    }

    public function test_it_fails_when_config_is_missing(): void
    {
        config()->set('artisan-toolkit', null);

        $this->artisan('config:diff')
            ->assertFailed()
            ->expectsOutputToContain('Config not found');
    }
}

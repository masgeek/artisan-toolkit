<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class EnvDiffCommandTest extends TestCase
{
    private string $tempDir;
    private string $basePath;
    private string $targetPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'env-diff-'.uniqid();
        File::ensureDirectoryExists($this->tempDir);
        $this->basePath = $this->tempDir.'/base.env';
        $this->targetPath = $this->tempDir.'/target.env';
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    private function writeFiles(string $base, string $target): void
    {
        File::put($this->basePath, $base);
        File::put($this->targetPath, $target);
    }

    public function test_it_reports_missing_and_extra_and_empty_and_duplicate_keys(): void
    {
        $base = "APP_NAME=Laravel\nAPP_ENV=local\nAPP_DEBUG=true\nDB_HOST=127.0.0.1\n";
        $target = "APP_NAME=Laravel\nAPP_DEBUG=true\nDB_HOST=127.0.0.1\nEXTRA=1\nEMPTY=\nDUPLICATE=first\nDUPLICATE=second\n";
        $this->writeFiles($base, $target);

        $this->artisan('env:diff', [
            '--base' => $this->basePath,
            '--target' => $this->targetPath,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('missing')
            ->expectsOutputToContain('extra')
            ->expectsOutputToContain('empty')
            ->expectsOutputToContain('duplicate');
    }

    public function test_json_output_structure(): void
    {
        $base = "APP_NAME=Laravel\nAPP_ENV=local\n";
        $target = "APP_NAME=Laravel\nEXTRA=1\n";
        $this->writeFiles($base, $target);

        Artisan::call('env:diff', [
            '--base' => $this->basePath,
            '--target' => $this->targetPath,
            '--json' => true,
        ]);
        $data = json_decode(Artisan::output(), true);
        $this->assertEquals($this->basePath, $data['base']);
        $this->assertEquals($this->targetPath, $data['target']);
        $this->assertContains('APP_ENV', $data['missing']);
        $this->assertContains('EXTRA', $data['extra']);
    }

    public function test_fail_flag_causes_failure_when_missing_keys(): void
    {
        $base = "APP_NAME=Laravel\nAPP_ENV=local\n";
        $target = "APP_NAME=Laravel\n";
        $this->writeFiles($base, $target);

        $this->artisan('env:diff', [
            '--base' => $this->basePath,
            '--target' => $this->targetPath,
            '--fail' => true,
        ])->assertFailed();
    }
}

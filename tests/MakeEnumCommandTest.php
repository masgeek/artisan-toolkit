<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Support\Facades\File;

class MakeEnumCommandTest extends TestCase
{
    private string $enumsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enumsPath = app_path('Enums');
        File::deleteDirectory($this->enumsPath);
        File::makeDirectory($this->enumsPath, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->enumsPath);
        parent::tearDown();
    }

    public function test_it_creates_a_pure_enum(): void
    {
        $this->artisan('make:enum', ['name' => 'UserRole'])
            ->assertSuccessful();

        $this->assertFileExists($this->enumsPath . '/UserRole.php');

        $content = File::get($this->enumsPath . '/UserRole.php');
        $this->assertStringContainsString('namespace App\Enums;', $content);
        $this->assertStringContainsString('enum UserRole', $content);
        $this->assertStringContainsString('//', $content);
    }

    public function test_it_creates_a_string_backed_enum(): void
    {
        $this->artisan('make:enum', [
            'name' => 'Status',
            '--backed' => 'string',
        ])->assertSuccessful();

        $content = File::get($this->enumsPath . '/Status.php');
        $this->assertStringContainsString('enum Status: string', $content);
    }

    public function test_it_creates_an_int_backed_enum(): void
    {
        $this->artisan('make:enum', [
            'name' => 'Priority',
            '--backed' => 'int',
        ])->assertSuccessful();

        $content = File::get($this->enumsPath . '/Priority.php');
        $this->assertStringContainsString('enum Priority: int', $content);
    }

    public function test_it_creates_enum_with_cases(): void
    {
        $this->artisan('make:enum', [
            'name' => 'UserRole',
            '--cases' => 'Admin,Editor,Viewer',
        ])->assertSuccessful();

        $content = File::get($this->enumsPath . '/UserRole.php');
        $this->assertStringContainsString('case Admin;', $content);
        $this->assertStringContainsString('case Editor;', $content);
        $this->assertStringContainsString('case Viewer;', $content);
    }

    public function test_it_creates_enum_with_backed_string_cases(): void
    {
        $this->artisan('make:enum', [
            'name' => 'Status',
            '--backed' => 'string',
            '--cases' => 'Pending,InProgress,Completed',
        ])->assertSuccessful();

        $content = File::get($this->enumsPath . '/Status.php');
        $this->assertStringContainsString("case Pending = 'pending';", $content);
        $this->assertStringContainsString("case InProgress = 'in_progress';", $content);
        $this->assertStringContainsString("case Completed = 'completed';", $content);
    }

    public function test_it_creates_enum_with_backed_int_cases(): void
    {
        $this->artisan('make:enum', [
            'name' => 'Priority',
            '--backed' => 'int',
            '--cases' => 'Low,Medium,High',
        ])->assertSuccessful();

        $content = File::get($this->enumsPath . '/Priority.php');
        $this->assertStringContainsString('case Low = 1;', $content);
        $this->assertStringContainsString('case Medium = 2;', $content);
        $this->assertStringContainsString('case High = 3;', $content);
    }

    public function test_it_creates_namespaced_enum(): void
    {
        $this->artisan('make:enum', ['name' => 'Auth/UserRole'])
            ->assertSuccessful();

        $this->assertFileExists($this->enumsPath . '/Auth/UserRole.php');

        $content = File::get($this->enumsPath . '/Auth/UserRole.php');
        $this->assertStringContainsString('namespace App\Enums\Auth;', $content);
        $this->assertStringContainsString('enum UserRole', $content);
    }

    public function test_it_fails_when_enum_already_exists(): void
    {
        File::put($this->enumsPath . '/UserRole.php', '<?php');

        $this->artisan('make:enum', ['name' => 'UserRole'])
            ->assertFailed();
    }

    public function test_it_forces_overwrite_when_flag_given(): void
    {
        File::put($this->enumsPath . '/UserRole.php', '<?php // old');

        $this->artisan('make:enum', [
            'name' => 'UserRole',
            '--force' => true,
        ])->assertSuccessful();

        $content = File::get($this->enumsPath . '/UserRole.php');
        $this->assertStringContainsString('enum UserRole', $content);
    }

    public function test_it_fails_with_invalid_backed_type(): void
    {
        $this->artisan('make:enum', [
            'name' => 'Foo',
            '--backed' => 'boolean',
        ])->assertFailed();
    }
}

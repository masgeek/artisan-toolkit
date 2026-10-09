<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

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

    private function assertPhpSyntax(string $filePath): void
    {
        $output = [];
        $resultCode = 0;
        exec("php -l " . escapeshellarg($filePath), $output, $resultCode);

        $this->assertEquals(0, $resultCode, "PHP syntax error in {$filePath}: " . implode("\n", $output));
    }

    public function test_it_creates_a_pure_enum(): void
    {
        $this->artisan('make:enum', ['name' => 'UserRole'])
            ->assertSuccessful();

        $this->assertFileExists($this->enumsPath.'/UserRole.php');

        $this->assertPhpSyntax($this->enumsPath.'/UserRole.php');

        $content = File::get($this->enumsPath.'/UserRole.php');
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

        $content = File::get($this->enumsPath.'/Status.php');
        $this->assertStringContainsString('enum Status: string', $content);
    }

    public function test_it_creates_an_int_backed_enum(): void
    {
        $this->artisan('make:enum', [
            'name' => 'Priority',
            '--backed' => 'int',
        ])->assertSuccessful();

        $content = File::get($this->enumsPath.'/Priority.php');
        $this->assertStringContainsString('enum Priority: int', $content);
    }

    public function test_it_creates_enum_with_cases(): void
    {
        $this->artisan('make:enum', [
            'name' => 'UserRole',
            '--cases' => 'Admin,Editor,Viewer',
        ])->assertSuccessful();

        $content = File::get($this->enumsPath.'/UserRole.php');
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

        $content = File::get($this->enumsPath.'/Status.php');
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

        $content = File::get($this->enumsPath.'/Priority.php');
        $this->assertStringContainsString('case Low = 1;', $content);
        $this->assertStringContainsString('case Medium = 2;', $content);
        $this->assertStringContainsString('case High = 3;', $content);
    }

    public function test_it_creates_namespaced_enum(): void
    {
        $this->artisan('make:enum', ['name' => 'Auth/UserRole'])
            ->assertSuccessful();

        $this->assertFileExists($this->enumsPath.'/Auth/UserRole.php');

        $content = File::get($this->enumsPath.'/Auth/UserRole.php');
        $this->assertStringContainsString('namespace App\Enums\Auth;', $content);
        $this->assertStringContainsString('enum UserRole', $content);
    }

    public function test_it_fails_when_enum_already_exists(): void
    {
        File::put($this->enumsPath.'/UserRole.php', '<?php');

        $this->artisan('make:enum', ['name' => 'UserRole'])
            ->assertFailed();
    }

    public function test_it_forces_overwrite_when_flag_given(): void
    {
        File::put($this->enumsPath.'/UserRole.php', '<?php // old');

        $this->artisan('make:enum', [
            'name' => 'UserRole',
            '--force' => true,
        ])->assertSuccessful();

        $content = File::get($this->enumsPath.'/UserRole.php');
        $this->assertStringContainsString('enum UserRole', $content);
    }

    public function test_it_fails_with_invalid_backed_type(): void
    {
        $this->artisan('make:enum', [
            'name' => 'Foo',
            '--backed' => 'boolean',
        ])->assertFailed();
    }

    public function test_it_builds_cases_from_a_database_column(): void
    {
        Schema::create('enum_sample_users', function ($table) {
            $table->id();
            $table->string('role');
        });

        Schema::getConnection()->table('enum_sample_users')->insert([
            ['role' => 'admin'],
            ['role' => 'partner'],
            ['role' => 'admin'],
        ]);

        $this->artisan('make:enum', [
            'name' => 'UserRole',
            '--backed' => 'string',
            '--table' => 'enum_sample_users.role',
        ])->assertSuccessful();

        $content = File::get($this->enumsPath.'/UserRole.php');
        $this->assertStringContainsString("case Admin = 'admin';", $content);
        $this->assertStringContainsString("case Partner = 'partner';", $content);
        // Distinct values only.
        $this->assertSame(1, substr_count($content, "case Admin = 'admin';"));

        Schema::dropIfExists('enum_sample_users');
    }

    public function test_it_gracefully_handles_an_unknown_table(): void
    {
        $this->artisan('make:enum', [
            'name' => 'UserRole',
            '--backed' => 'string',
            '--table' => 'no_such_table.column',
        ])->assertSuccessful();

        $content = File::get($this->enumsPath.'/UserRole.php');
        $this->assertStringContainsString('Error fetching table values', $content);
    }

    public function test_it_honours_the_configured_enum_path(): void
    {
        File::deleteDirectory(app_path('Domain'));

        config()->set('artisan-toolkit.paths.enums', 'app/Domain/Enums');

        $this->artisan('make:enum', ['name' => 'UserRole'])->assertSuccessful();

        $file = app_path('Domain/Enums/UserRole.php');
        $this->assertFileExists($file);
        $this->assertStringContainsString('namespace App\Domain\Enums;', File::get($file));

        File::deleteDirectory(app_path('Domain'));
    }
}

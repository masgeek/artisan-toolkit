<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class SchemaDumpCommandTest extends TestCase
{
    private string $migrationsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrationsPath = database_path('migrations');
        File::deleteDirectory($this->migrationsPath);
        File::makeDirectory($this->migrationsPath, 0755, true);

        $this->createMigrationsTable();

        config()->set('database.migrations', 'migrations');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->migrationsPath);
        parent::tearDown();
    }

    private function createMigrationsTable(): void
    {
        if (! Schema::hasTable('migrations')) {
            Schema::create('migrations', function ($table) {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
            });
        }
    }

    public function test_it_can_dump_schema_without_prune(): void
    {
        $this->createMigrationFile('2024_01_01_000001_create_users_table.php');
        $this->createMigrationFile('2024_01_02_000002_create_posts_table.php');

        $this->artisan('schema:dump')
            ->assertSuccessful();

        $this->assertFileExists($this->migrationsPath . '/2024_01_01_000001_create_users_table.php');
        $this->assertFileExists($this->migrationsPath . '/2024_01_02_000002_create_posts_table.php');
    }

    public function test_prune_deletes_only_ran_migrations(): void
    {
        $this->createMigrationFile('2024_01_01_000001_create_users_table.php');
        $this->createMigrationFile('2024_01_02_000002_create_posts_table.php');

        DB::table('migrations')->insert([
            'migration' => '2024_01_01_000001_create_users_table',
            'batch' => 1,
        ]);

        $this->artisan('schema:dump', ['--prune' => true])
            ->assertSuccessful();

        $this->assertFileDoesNotExist($this->migrationsPath . '/2024_01_01_000001_create_users_table.php');
        $this->assertFileExists($this->migrationsPath . '/2024_01_02_000002_create_posts_table.php');
    }

    public function test_prune_keeps_all_pending_migrations(): void
    {
        $this->createMigrationFile('2024_01_01_000001_create_users_table.php');
        $this->createMigrationFile('2024_01_02_000002_create_posts_table.php');

        $this->artisan('schema:dump', ['--prune' => true])
            ->assertSuccessful();

        $this->assertFileExists($this->migrationsPath . '/2024_01_01_000001_create_users_table.php');
        $this->assertFileExists($this->migrationsPath . '/2024_01_02_000002_create_posts_table.php');
    }

    public function test_prune_mixed_ran_and_pending(): void
    {
        $this->createMigrationFile('2024_01_01_000001_create_users_table.php');
        $this->createMigrationFile('2024_01_02_000002_create_posts_table.php');
        $this->createMigrationFile('2024_01_03_000003_create_comments_table.php');

        DB::table('migrations')->insert([
            'migration' => '2024_01_01_000001_create_users_table',
            'batch' => 1,
        ]);

        DB::table('migrations')->insert([
            'migration' => '2024_01_03_000003_create_comments_table',
            'batch' => 1,
        ]);

        $this->artisan('schema:dump', ['--prune' => true])
            ->assertSuccessful();

        $this->assertFileDoesNotExist($this->migrationsPath . '/2024_01_01_000001_create_users_table.php');
        $this->assertFileExists($this->migrationsPath . '/2024_01_02_000002_create_posts_table.php');
        $this->assertFileDoesNotExist($this->migrationsPath . '/2024_01_03_000003_create_comments_table.php');
    }

    public function test_it_respects_array_migrations_config(): void
    {
        config()->set('database.migrations', ['table' => 'migrations', 'update_date_on_run' => true]);

        $this->createMigrationFile('2024_01_01_000001_create_users_table.php');

        DB::table('migrations')->insert([
            'migration' => '2024_01_01_000001_create_users_table',
            'batch' => 1,
        ]);

        $this->artisan('schema:dump', ['--prune' => true])
            ->assertSuccessful();

        $this->assertFileDoesNotExist($this->migrationsPath . '/2024_01_01_000001_create_users_table.php');
    }

    public function test_it_is_prohibited_from_running(): void
    {
        \Masgeek\ArtisanToolkit\Commands\SchemaDumpCommand::prohibit();

        $this->artisan('schema:dump')
            ->assertFailed();
    }

    private function createMigrationFile(string $filename): void
    {
        File::put($this->migrationsPath . '/' . $filename, '<?php');
    }
}

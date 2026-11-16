<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Masgeek\ArtisanToolkit\Commands\SchemaDumpCommand;
use ReflectionMethod;

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

        $this->assertFileExists($this->migrationsPath.'/2024_01_01_000001_create_users_table.php');
        $this->assertFileExists($this->migrationsPath.'/2024_01_02_000002_create_posts_table.php');
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

        $this->assertFileDoesNotExist($this->migrationsPath.'/2024_01_01_000001_create_users_table.php');
        $this->assertFileExists($this->migrationsPath.'/2024_01_02_000002_create_posts_table.php');
    }

    public function test_prune_keeps_all_pending_migrations(): void
    {
        $this->createMigrationFile('2024_01_01_000001_create_users_table.php');
        $this->createMigrationFile('2024_01_02_000002_create_posts_table.php');

        $this->artisan('schema:dump', ['--prune' => true])
            ->assertSuccessful();

        $this->assertFileExists($this->migrationsPath.'/2024_01_01_000001_create_users_table.php');
        $this->assertFileExists($this->migrationsPath.'/2024_01_02_000002_create_posts_table.php');
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

        $this->assertFileDoesNotExist($this->migrationsPath.'/2024_01_01_000001_create_users_table.php');
        $this->assertFileExists($this->migrationsPath.'/2024_01_02_000002_create_posts_table.php');
        $this->assertFileDoesNotExist($this->migrationsPath.'/2024_01_03_000003_create_comments_table.php');
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

        $this->assertFileDoesNotExist($this->migrationsPath.'/2024_01_01_000001_create_users_table.php');
    }

    public function test_it_is_prohibited_from_running(): void
    {
        SchemaDumpCommand::prohibit();

        $this->artisan('schema:dump')
            ->assertFailed();
    }

    public function test_it_strips_unquoted_schema_prefix(): void
    {
        $sql = "CREATE TABLE public.users (\n  id int\n);\n"
            ."ALTER TABLE ONLY public.users ADD CONSTRAINT users_pkey PRIMARY KEY (id);\n"
            ."CREATE SEQUENCE public.users_id_seq;\n";

        $result = $this->stripSchemaPrefix($sql, 'public');

        $this->assertStringNotContainsString('public.', $result);
        $this->assertStringContainsString('CREATE TABLE users (', $result);
        $this->assertStringContainsString('CREATE SEQUENCE users_id_seq;', $result);
    }

    public function test_it_strips_quoted_schema_prefixes_without_touching_others(): void
    {
        $sql = 'CREATE TABLE "public"."users" (id int); CREATE TABLE [dbo].[posts] (id int);';

        $result = $this->stripSchemaPrefix($sql, 'public');

        $this->assertStringContainsString('"users"', $result);
        $this->assertStringNotContainsString('public.', $result);
        $this->assertStringContainsString('[dbo].[posts]', $result);
    }

    public function test_it_removes_create_schema_and_search_path_statements(): void
    {
        $sql = "CREATE SCHEMA fuelrod;\n\n"
            ."SELECT pg_catalog.set_config('search_path', '', false);\n\n"
            ."CREATE TABLE fuelrod.users (\n  id int\n);\n";

        $result = $this->stripSchemaPrefix($sql, 'fuelrod');

        $this->assertStringNotContainsString('CREATE SCHEMA', $result);
        $this->assertStringNotContainsString('set_config', $result);
        $this->assertStringNotContainsString('fuelrod.', $result);
        $this->assertStringContainsString('CREATE TABLE users (', $result);
    }

    public function test_it_removes_set_search_path_statement(): void
    {
        $sql = "SET search_path = fuelrod, pg_catalog;\n"
            .'CREATE TABLE fuelrod.users (id int);';

        $result = $this->stripSchemaPrefix($sql, 'fuelrod');

        $this->assertStringNotContainsString('search_path', $result);
        $this->assertStringContainsString('CREATE TABLE users', $result);
    }

    public function test_it_does_not_strip_schema_like_substrings(): void
    {
        $sql = 'CREATE TABLE app_public.users (id int); CREATE TABLE mypublic.posts (id int);';

        $result = $this->stripSchemaPrefix($sql, 'public');

        $this->assertStringContainsString('app_public.users', $result);
        $this->assertStringContainsString('mypublic.posts', $result);
    }

    public function test_no_schema_override_forces_stripping_even_when_config_keeps_it(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'schema-dump');
        file_put_contents($file, "CREATE TABLE public.users (id int);\n");
        config()->set('artisan-toolkit.schema_dump_include_schema', true);

        $connection = $this->createStub(Connection::class);
        $connection->method('getConfig')->willReturn(['driver' => 'pgsql']);

        $this->invokePrivate('maybeStripSchemaPrefix', [$connection, $file, false]);
        $this->assertStringContainsString('public.users', (string) file_get_contents($file));

        $this->invokePrivate('maybeStripSchemaPrefix', [$connection, $file, true]);
        $this->assertStringNotContainsString('public.users', (string) file_get_contents($file));

        unlink($file);
    }

    public function test_it_resolves_schema_name_per_driver(): void
    {
        $this->assertSame('public', $this->resolveSchemaName(['driver' => 'pgsql']));
        $this->assertSame('dbo', $this->resolveSchemaName(['driver' => 'sqlsrv']));
        $this->assertNull($this->resolveSchemaName(['driver' => 'sqlite']));
        $this->assertSame('custom', $this->resolveSchemaName(['driver' => 'pgsql', 'search_path' => 'custom, public']));
    }

    private function stripSchemaPrefix(string $sql, string $schema): string
    {
        return $this->invokePrivate('stripSchemaPrefix', [$sql, $schema]);
    }

    private function resolveSchemaName(array $config): ?string
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getConfig')->willReturn($config);

        return $this->invokePrivate('resolveSchemaName', [$connection]);
    }

    private function invokePrivate(string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod(SchemaDumpCommand::class, $method);

        return $reflection->invoke(new SchemaDumpCommand, ...$arguments);
    }

    private function createMigrationFile(string $filename): void
    {
        File::put($this->migrationsPath.'/'.$filename, '<?php');
    }
}
